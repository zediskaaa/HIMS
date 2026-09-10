<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Models\CycleCountDoc;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\Inventory\CycleCountService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class CycleCountController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:' . Permission::PerformCycleCount->value, only: ['schedule', 'submitCounts', 'calculateAbc']),
            new Middleware('can:' . Permission::ApproveAdjustment->value, only: ['approve']),
            new Middleware('can:' . Permission::PerformCycleCount->value, only: ['index', 'show']),
        ];
    }

    public function __construct(private readonly CycleCountService $cycleCountService) {}

    public function index(): View
    {
        $cycleCounts = CycleCountDoc::with(['assignedCounter', 'approvedBy', 'location'])
            ->latest()
            ->paginate(15);

        $locations = StorageLocation::where('status', 'active')->orderBy('name')->get();

        $eligibleRoles = collect(UserRole::cases())
            ->filter(fn (UserRole $role) => $role->grants(Permission::PerformCycleCount))
            ->map(fn (UserRole $role) => $role->value)
            ->all();

        $counters = User::active()
            ->whereIn('role', $eligibleRoles)
            ->orderBy('name')
            ->get();

        return view('inventory.cycle-counts.index', compact('cycleCounts', 'locations', 'counters'));
    }

    public function show(CycleCountDoc $cycleCountDoc): View
    {
        $cycleCountDoc->load(['assignedCounter', 'approvedBy', 'location', 'lines.item', 'lines.batch', 'lines.location']);

        return view('inventory.cycle-counts.show', compact('cycleCountDoc'));
    }

    public function schedule(Request $request): RedirectResponse
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

        return redirect()->route('inventory.cycle-counts.show', $doc)
            ->with('success', "Cycle count {$doc->document_number} scheduled and inventory snapshot captured.");
    }

    public function submitCounts(Request $request, CycleCountDoc $cycleCountDoc): RedirectResponse
    {
        $validated = $request->validate([
            'counts' => ['required', 'array'],
            'counts.*' => ['required', 'integer', 'min:0'],
        ]);

        $this->cycleCountService->submitBlindCounts($cycleCountDoc, $validated['counts'], $request->user());

        return redirect()->route('inventory.cycle-counts.show', $cycleCountDoc)
            ->with('success', "Blind counts recorded for {$cycleCountDoc->document_number}.");
    }

    public function approve(Request $request, CycleCountDoc $cycleCountDoc): RedirectResponse
    {
        try {
            $this->cycleCountService->approveAndPostAdjustments($cycleCountDoc, $request->user());

            return redirect()->route('inventory.cycle-counts.show', $cycleCountDoc)
                ->with('success', "Cycle count variances approved and posted to inventory ledger.");
        } catch (DomainException $e) {
            return redirect()->back()->withErrors(['approve' => $e->getMessage()]);
        }
    }

    public function calculateAbc(): RedirectResponse
    {
        $this->cycleCountService->calculateAbcClasses();

        return redirect()->route('inventory.cycle-counts.index')
            ->with('success', 'ABC spend stratification recomputed for all inventory items.');
    }
}
