<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Services\AiChatTitleGenerator;
use App\Services\AiInventoryAssistantService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DashboardAiAssistantController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware|string>
     */
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
        ];
    }

    /**
     * Authorize user access to AI Inventory Assistant.
     */
    private function authorizeAssistantAccess(Request $request): \App\Models\User
    {
        $user = $request->user();

        if (! $user || (! $user->can(Permission::ViewInventory->value) && ! $user->can(Permission::ViewReports->value))) {
            abort(403, 'Unauthorized. You do not have permission to access the HIMS Inventory Assistant.');
        }

        return $user;
    }

    /**
     * Fetch list of conversations for authenticated user.
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = $this->authorizeAssistantAccess($request);

        $conversations = $user->aiChatConversations()
            ->withCount('messages')
            ->latest('updated_at')
            ->take(40)
            ->get()
            ->map(function (AiChatConversation $conversation) {
                return [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'updated_at' => $conversation->updated_at->toISOString(),
                    'time_ago' => $conversation->updated_at->diffForHumans(),
                    'date_group' => $this->resolveDateGroup($conversation->updated_at),
                    'message_count' => $conversation->messages_count,
                ];
            });

        return response()->json([
            'status' => 'success',
            'conversations' => $conversations,
        ]);
    }

    /**
     * Fetch most recent active conversation with messages.
     */
    public function activeConversation(Request $request): JsonResponse
    {
        $user = $this->authorizeAssistantAccess($request);

        $conversation = $user->aiChatConversations()
            ->latest('updated_at')
            ->first();

        if (! $conversation) {
            return response()->json([
                'status' => 'success',
                'conversation' => null,
            ]);
        }

        $messages = $conversation->messages()
            ->orderBy('id', 'asc')
            ->take(100)
            ->get()
            ->map(fn (AiChatMessage $m) => $m->toClientPayload());

        return response()->json([
            'status' => 'success',
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'messages' => $messages,
            ],
        ]);
    }

    /**
     * Fetch a specific conversation with messages.
     */
    public function showConversation(Request $request, int $id): JsonResponse
    {
        $user = $this->authorizeAssistantAccess($request);

        $conversation = $user->aiChatConversations()->findOrFail($id);

        $messages = $conversation->messages()
            ->orderBy('id', 'asc')
            ->take(100)
            ->get()
            ->map(fn (AiChatMessage $m) => $m->toClientPayload());

        return response()->json([
            'status' => 'success',
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'messages' => $messages,
            ],
        ]);
    }

    /**
     * Process an AI Inventory Assistant chat prompt with grounded HIMS database context.
     */
    public function chat(Request $request, AiInventoryAssistantService $assistant): JsonResponse
    {
        $user = $this->authorizeAssistantAccess($request);

        // Decode JSON-encoded history if sent via multipart/form-data
        if ($request->has('history') && is_string($request->input('history'))) {
            $decoded = json_decode($request->input('history'), true);
            if (is_array($decoded)) {
                $request->merge(['history' => $decoded]);
            }
        }

        $validated = $request->validate([
            'message' => ['required_without:attachment', 'nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,csv,xlsx,docx,txt,jpg,jpeg,png', 'max:35840'],
            'conversation_id' => ['nullable', 'integer'],
            'history' => ['nullable', 'array', 'max:15'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant,model'],
            'history.*.content' => ['required_with:history', 'string', 'max:4000'],
        ]);

        $messageText = trim($validated['message'] ?? '');
        $attachment = $request->file('attachment');
        $conversationId = $validated['conversation_id'] ?? null;

        // Retrieve or create conversation scoped to authenticated user
        if ($conversationId !== null) {
            $conversation = $user->aiChatConversations()->findOrFail($conversationId);
        } else {
            $conversation = $user->aiChatConversations()->create([
                'title' => 'New Chat',
            ]);
        }

        // Store attachment securely in private storage if present
        $storedAttachmentPath = null;
        $attachmentOriginalName = null;
        $attachmentExt = null;

        if ($attachment !== null) {
            $attachmentOriginalName = $attachment->getClientOriginalName();
            $attachmentExt = strtolower($attachment->getClientOriginalExtension());
            $storedAttachmentPath = $attachment->store("ai-chat-attachments/{$user->id}", 'local');
        }

        // Auto-generate title if currently "New Chat" or first message
        if ($conversation->title === 'New Chat' || $conversation->messages()->count() === 0) {
            $generatedTitle = AiChatTitleGenerator::generate($messageText, $attachmentOriginalName);
            $conversation->update(['title' => $generatedTitle]);
        }

        // Gather multi-turn context from database if available, otherwise use client history
        $persistedHistory = $conversation->messages()
            ->orderBy('id', 'desc')
            ->take(10)
            ->get()
            ->reverse()
            ->map(fn (AiChatMessage $m) => [
                'role' => $m->role,
                'content' => $m->content,
            ])
            ->values()
            ->all();

        $conversationHistory = ! empty($persistedHistory)
            ? $persistedHistory
            : ($validated['history'] ?? []);

        try {
            $result = $assistant->respond($user, $messageText, $conversationHistory, $attachment);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => ['attachment' => [$e->getMessage()]],
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred while processing your inventory inquiry. Please try again or check the system logs.',
            ], 500);
        }

        // Persist user message
        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $messageText !== '' ? $messageText : ($attachmentOriginalName ? "Please analyze this attached file ({$attachmentOriginalName})." : ''),
            'attachment_name' => $attachmentOriginalName,
            'attachment_path' => $storedAttachmentPath,
            'attachment_type' => $result['attachment']['type'] ?? null,
            'attachment_extension' => $attachmentExt,
            'attachment_size' => $result['attachment']['size'] ?? null,
            'is_error' => false,
        ]);

        // Persist assistant message
        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result['reply'],
            'source' => $result['source'] ?? 'ai',
            'status_hint' => $result['status_hint'] ?? null,
            'is_error' => false,
        ]);

        // Update conversation timestamp
        $conversation->touch();

        return response()->json([
            'status' => 'success',
            'conversation_id' => $conversation->id,
            'conversation_title' => $conversation->title,
            'reply' => $result['reply'],
            'source' => $result['source'],
            'status_hint' => $result['status_hint'] ?? null,
            'attachment' => $result['attachment'] ?? null,
            'user_message' => $userMessage->toClientPayload(),
            'assistant_message' => $assistantMessage->toClientPayload(),
        ]);
    }

    /**
     * Stream a protected image attachment for the conversation owner only.
     */
    public function attachment(Request $request, int $message): BinaryFileResponse
    {
        $user = $this->authorizeAssistantAccess($request);

        $chatMessage = AiChatMessage::with('conversation')->findOrFail($message);

        if ($chatMessage->conversation->user_id !== $user->id) {
            abort(403, 'Unauthorized access to attachment.');
        }

        if (! $chatMessage->attachment_path || ! Storage::disk('local')->exists($chatMessage->attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        return response()->file(Storage::disk('local')->path($chatMessage->attachment_path));
    }

    /**
     * Categorize a timestamp into Today, Yesterday, Previous 7 Days, or Older.
     */
    private function resolveDateGroup(Carbon $date): string
    {
        if ($date->isToday()) {
            return 'Today';
        }

        if ($date->isYesterday()) {
            return 'Yesterday';
        }

        if ($date->greaterThanOrEqualTo(now()->subDays(7))) {
            return 'Previous 7 Days';
        }

        return 'Older';
    }
}
