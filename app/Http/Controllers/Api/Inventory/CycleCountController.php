<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Inventory\CycleCountService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CycleCountController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            new Middleware('can:' . Permission::PerformCycleCount->value, only: ['schedule', 'submitCounts']),
            new Middleware('can:' . Permission::ApproveAdjustment->value, only: ['approve']),
            new Middleware('can:' . Permission::ViewInventory->value, only: ['index', 'show']),
        ];
    }

    public function __construct(private readonly CycleCountService $cycleCountService) {}

    public function index(): JsonResponse
    {
        $docs = CycleCountDoc::with(['assignedCounter', 'approvedBy', 'location'])
            ->latest()
            ->paginate(25);

        return response()->json($docs);
    }

    public function show(CycleCountDoc $cycleCountDoc): JsonResponse
    {
        $cycleCountDoc->load(['assignedCounter', 'approvedBy', 'location', 'lines.item', 'lines.batch', 'lines.location']);

        return response()->json($cycleCountDoc);
    }

    public function schedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'count_type' => ['nullable', 'in:ABC,random,location,all'],
            'storage_location_id' => ['nullable', 'exists:storage_locations,id'],
            'assigned_counter_id' => [
                'nullable',
                'exists:users,id',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! $value) {
                        return;
                    }
                    $user = User::find($value);
                    if (! $user || ! $user->isActive() || ! $user->hasPermission(Permission::PerformCycleCount)) {
                        $fail('The selected assigned counter must be an active staff member authorized to perform cycle counts.');
                    }
                },
            ],
        ]);

        $this->cycleCountService->calculateAbcClasses();

        $doc = $this->cycleCountService->generateCountDocument(
            $validated['count_type'] ?? 'ABC',
            $validated['storage_location_id'] ?? null,
            $request->user(),
            $validated['assigned_counter_id'] ?? null
        );

        return response()->json([
            'message' => "Cycle count document {$doc->document_number} generated with frozen snapshot.",
            'data' => $doc->load('lines.item'),
        ], 201);
    }

    public function submitCounts(Request $request, int $countId): JsonResponse
    {
        $doc = CycleCountDoc::findOrFail($countId);

        $validated = $request->validate([
            'counts' => ['required', 'array'],
            'counts.*' => ['required', 'integer', 'min:0'],
        ]);

        $updated = $this->cycleCountService->submitBlindCounts($doc, $validated['counts'], $request->user());

        return response()->json([
            'message' => "Blind counts submitted for {$updated->document_number}.",
            'data' => $updated->load('lines.item'),
        ]);
    }

    public function approve(Request $request, int $countId): JsonResponse
    {
        $doc = CycleCountDoc::findOrFail($countId);

        try {
            $approved = $this->cycleCountService->approveAndPostAdjustments($doc, $request->user());

            return response()->json([
                'message' => "Cycle count variances approved and posted to inventory ledger.",
                'data' => $approved->load('lines.item'),
            ]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
