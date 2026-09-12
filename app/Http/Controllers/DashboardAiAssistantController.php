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

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array', 'max:10'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant,model'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
        ]);

        $history = $validated['history'] ?? [];
        $result = $assistant->respond($user, $validated['message'], $history);

        return response()->json([
            'status' => 'success',
            'reply' => $result['reply'],
            'source' => $result['source'],
        ]);
    }
}
