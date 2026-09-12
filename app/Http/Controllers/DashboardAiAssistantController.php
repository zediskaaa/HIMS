<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Services\AiInventoryAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

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
     * Process an AI Inventory Assistant chat prompt with grounded HIMS database context.
     */
    public function chat(Request $request, AiInventoryAssistantService $assistant): JsonResponse
    {
        $user = $request->user();

        // Enforce server-side role and permission boundaries.
        if (! $user || (! $user->can(Permission::ViewInventory->value) && ! $user->can(Permission::ViewReports->value))) {
            abort(403, 'Unauthorized. You do not have permission to access the HIMS Inventory Assistant.');
        }

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
            'history' => ['nullable', 'array', 'max:10'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant,model'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
        ]);

        $history = $validated['history'] ?? [];
        $attachment = $request->file('attachment');

        try {
            $result = $assistant->respond($user, $validated['message'] ?? '', $history, $attachment);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => ['attachment' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'reply' => $result['reply'],
            'source' => $result['source'],
            'status_hint' => $result['status_hint'] ?? null,
            'attachment' => $result['attachment'] ?? null,
        ]);
    }
}
