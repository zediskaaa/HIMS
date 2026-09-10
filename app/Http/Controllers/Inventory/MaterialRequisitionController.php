<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CostCenter;
use App\Models\InventoryItem;
use App\Models\MaterialRequisition;
use App\Services\Inventory\IssuanceEngine;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class MaterialRequisitionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::CreateRequisition->value, only: ['store', 'cancel']),
            new Middleware('can:'.Permission::ApproveRequisition->value, only: ['approve', 'reject']),
            new Middleware('can:'.Permission::IssueStock->value, only: ['issue']),
            new Middleware('can:'.Permission::ViewInventory->value, only: ['index', 'show']),
        ];
    }

    public function __construct(private readonly IssuanceEngine $issuanceEngine) {}

    public function index(): View
    {
        $requisitions = MaterialRequisition::with(['requestingUser', 'approvedBy', 'costCenter', 'lines.item'])
            ->latest()
            ->paginate(15);

        $items = InventoryItem::where('status', '!=', 'discontinued')->orderBy('name')->get();
        $costCenters = CostCenter::where('is_active', true)->orderBy('name')->get();
        $departments = CostCenter::where('is_active', true)
            ->whereNotNull('department')
            ->distinct()
            ->pluck('department')
            ->concat([
                'Emergency Department',
                'Intensive Care Unit (ICU)',
                'Operating Room (OR)',
                'Central Supply',
                'Pharmacy',
                'Laboratory',
                'Inpatient Ward',
                'Outpatient Clinic',
            ])
            ->unique()
            ->values();

        $requisitionMetrics = [
            'total' => MaterialRequisition::count(),
            'pending' => MaterialRequisition::whereIn('status', ['submitted', 'pending_approval'])->count(),
            'approved' => MaterialRequisition::where('status', 'approved')->count(),
        ];
        $approverRoleLabels = $this->approverRoleLabels();

        return view('inventory.requisitions.index', compact('requisitions', 'items', 'costCenters', 'departments', 'requisitionMetrics', 'approverRoleLabels'));
    }

    public function show(MaterialRequisition $requisition): View
    {
        $requisition->load(['requestingUser', 'approvedBy', 'issuedBy', 'acknowledgedBy', 'costCenter', 'lines.item', 'lines.batch', 'lines.location']);
        $pickList = $this->issuanceEngine->generatePickList($requisition);
        $approverRoleLabels = $this->approverRoleLabels();

        return view('inventory.requisitions.show', compact('requisition', 'pickList', 'approverRoleLabels'));
    }

    public function store(Request $request): RedirectResponse
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

            return redirect()->route('inventory.requisitions.index')
                ->with('success', "Store Requisition {$req->requisition_number} submitted successfully.");
        } catch (DomainException $e) {
            return redirect()->back()->withErrors(['requisition' => $e->getMessage()])->withInput();
        }
    }

    public function approve(Request $request, MaterialRequisition $requisition): RedirectResponse
    {
        try {
            $this->issuanceEngine->approveRequisition($requisition, $request->user());

            return redirect()->route('inventory.requisitions.index')
                ->with('success', "Requisition {$requisition->requisition_number} approved and ATP stock reserved.");
        } catch (DomainException $e) {
            return redirect()->back()->withErrors(['approve' => $e->getMessage()]);
        }
    }

    public function issue(Request $request, MaterialRequisition $requisition): RedirectResponse
    {
        $validated = $request->validate([
            'lines' => ['nullable', 'array'],
            'lines.*.line_id' => ['required', 'exists:material_requisition_lines,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.location_id' => ['nullable', 'exists:storage_locations,id'],
            'lines.*.batch_id' => ['nullable', 'exists:item_batches,id'],
        ]);

        try {
            $this->issuanceEngine->issueRequisition($requisition, $validated, $request->user());

            return redirect()->route('inventory.requisitions.show', $requisition)
                ->with('success', "Requisition {$requisition->requisition_number} issued and stock balances updated.");
        } catch (DomainException $e) {
            return redirect()->back()->withErrors(['issue' => $e->getMessage()]);
        }
    }

    public function acknowledge(Request $request, MaterialRequisition $requisition): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->issuanceEngine->acknowledgeHandover($requisition, $request->user(), $validated['notes'] ?? null);

            return redirect()->route('inventory.requisitions.show', $requisition)
                ->with('success', 'Handover acknowledgment recorded.');
        } catch (DomainException $e) {
            return redirect()->back()->withErrors(['acknowledge' => $e->getMessage()]);
        }
    }

    public function reject(Request $request, MaterialRequisition $requisition): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->issuanceEngine->rejectRequisition($requisition, $request->user(), $validated['rejection_reason']);

            return redirect()->route('inventory.requisitions.index')
                ->with('success', "Requisition {$requisition->requisition_number} rejected.");
        } catch (DomainException $e) {
            return redirect()->back()->withErrors(['reject' => $e->getMessage()]);
        }
    }

    public function cancel(Request $request, MaterialRequisition $requisition): RedirectResponse
    {
        $validated = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->issuanceEngine->cancelRequisition($requisition, $request->user(), $validated['cancellation_reason'] ?? null);

            return redirect()->route('inventory.requisitions.index')
                ->with('success', "Requisition {$requisition->requisition_number} cancelled.");
        } catch (DomainException $e) {
            return redirect()->back()->withErrors(['cancel' => $e->getMessage()]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function approverRoleLabels(): array
    {
        return collect(UserRole::cases())
            ->filter(fn (UserRole $role): bool => $role->grants(Permission::ApproveRequisition))
            ->map(fn (UserRole $role): string => $role->label())
            ->values()
            ->all();
    }
}
