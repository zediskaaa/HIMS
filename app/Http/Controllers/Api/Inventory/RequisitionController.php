<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\MaterialRequisition;
use App\Services\Inventory\IssuanceEngine;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RequisitionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            new Middleware('can:'.Permission::CreateRequisition->value, only: ['store']),
            new Middleware('can:'.Permission::ApproveRequisition->value, only: ['approve', 'reject']),
            new Middleware('can:'.Permission::IssueStock->value, only: ['issue', 'picklist']),
            new Middleware('can:'.Permission::ViewInventory->value, only: ['index', 'show']),
        ];
    }

    public function __construct(private readonly IssuanceEngine $issuanceEngine) {}

    public function index(): JsonResponse
    {
        $requisitions = MaterialRequisition::with(['requestingUser', 'approvedBy', 'lines.item'])
            ->latest()
            ->paginate(25);

        return response()->json($requisitions);
    }

    public function show(MaterialRequisition $requisition): JsonResponse
    {
        $requisition->load(['requestingUser', 'approvedBy', 'lines.item', 'lines.batch', 'lines.location']);

        return response()->json($requisition);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department' => ['required', 'string', 'max:100'],
            'cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'required_date' => ['nullable', 'date'],
            'urgency' => ['nullable', 'in:routine,urgent,stat_emergency'],
            'justification' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'exists:inventory_items,id'],
            'lines.*.requested_quantity' => ['required', 'integer', 'min:1'],
            'lines.*.allocation_strategy' => ['nullable', 'in:FEFO,FIFO,MANUAL'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $req = $this->issuanceEngine->createRequisition($validated, $request->user());

            return response()->json([
                'message' => "Store requisition {$req->requisition_number} submitted for approval.",
                'data' => $req->load('lines.item'),
            ], 201);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function approve(Request $request, int $reqId): JsonResponse
    {
        $requisition = MaterialRequisition::findOrFail($reqId);

        try {
            $approved = $this->issuanceEngine->approveRequisition($requisition, $request->user());

            return response()->json([
                'message' => "Requisition {$approved->requisition_number} approved and ATP stock reserved.",
                'data' => $approved->load('lines.item'),
            ]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function reject(Request $request, int $reqId): JsonResponse
    {
        $requisition = MaterialRequisition::findOrFail($reqId);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $rejected = $this->issuanceEngine->rejectRequisition($requisition, $request->user(), $validated['rejection_reason']);

            return response()->json([
                'message' => "Requisition {$rejected->requisition_number} rejected.",
                'data' => $rejected->load('lines.item'),
            ]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function picklist(int $reqId): JsonResponse
    {
        $requisition = MaterialRequisition::with('lines.item')->findOrFail($reqId);
        $pickList = $this->issuanceEngine->generatePickList($requisition);

        return response()->json([
            'requisition' => $requisition,
            'pick_list' => $pickList,
        ]);
    }

    public function issue(Request $request, int $reqId): JsonResponse
    {
        $requisition = MaterialRequisition::findOrFail($reqId);

        $validated = $request->validate([
            'lines' => ['nullable', 'array'],
            'lines.*.line_id' => ['required', 'exists:material_requisition_lines,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.location_id' => ['nullable', 'exists:storage_locations,id'],
            'lines.*.batch_id' => ['nullable', 'exists:item_batches,id'],
        ]);

        try {
            $issued = $this->issuanceEngine->issueRequisition($requisition, $validated, $request->user());

            return response()->json([
                'message' => "Requisition {$issued->requisition_number} issued and stock decremented.",
                'data' => $issued->load('lines.item'),
            ]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
