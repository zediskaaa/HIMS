<?php

namespace App\Services\Inventory;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Models\CycleCountDoc;
use App\Models\CycleCountLine;
use App\Models\InventoryAdjustment;
use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryAutomationService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CycleCountService
{
    public function __construct(
        private readonly InventoryAutomationService $automationService,
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Compute and update ABC classification for all inventory items based on Pareto principle.
     * Class A: Top 80% annual spend value.
     * Class B: Next 15% annual spend value.
     * Class C: Bottom 5% annual spend value.
     */
    public function calculateAbcClasses(): void
    {
        DB::transaction(function () {
            $items = InventoryItem::all()->map(function (InventoryItem $item) {
                // Annual demand or 12x monthly average
                $demand = $item->annual_demand > 0 ? $item->annual_demand : max(120, (int) ($item->quantity_on_hand * 2));
                $annualSpend = (float) $item->unit_cost * $demand;

                return [
                    'item' => $item,
                    'spend' => $annualSpend,
                ];
            })->sortByDesc('spend')->values();

            $totalSpend = $items->sum('spend');

            if ($totalSpend <= 0) {
                return;
            }

            $cumulative = 0.00;
            foreach ($items as $index => $row) {
                $cumulative += $row['spend'];
                $percent = ($cumulative / $totalSpend) * 100;

                $class = 'C';
                if ($index === 0 || $percent <= 80) {
                    $class = 'A';
                } elseif ($percent <= 95) {
                    $class = 'B';
                }

                $row['item']->abc_class = $class;
                $row['item']->save();
            }
        });
    }

    /**
     * Generate a new cycle count document and freeze book stock quantities.
     *
     * @param  string  $countType  ('ABC', 'random', 'location', 'all')
     */
    public function generateCountDocument(
        string $countType,
        ?int $locationId,
        User $scheduler,
        ?int $assignedCounterId = null
    ): CycleCountDoc {
        return DB::transaction(function () use ($countType, $locationId, $scheduler, $assignedCounterId) {
            $docNumber = 'CC-' . now()->format('Ymd') . '-' . str_pad((string) (CycleCountDoc::count() + 1), 4, '0', STR_PAD_LEFT);

            $counterId = $assignedCounterId ?? $scheduler->id;

            $doc = CycleCountDoc::create([
                'document_number' => $docNumber,
                'count_type' => $countType,
                'scheduled_date' => now()->toDateString(),
                'assigned_counter_id' => $counterId,
                'storage_location_id' => $locationId,
                'status' => 'generated',
                'snapshot_timestamp' => now(),
            ]);

            $query = ItemStockLevel::query()->with(['item', 'batch']);

            if ($locationId) {
                $query->where('storage_location_id', $locationId);
            }

            if ($countType === 'ABC') {
                $query->whereHas('item', fn ($q) => $q->where('abc_class', 'A'));
            }

            $stockLevels = $query->get();

            // If no stock levels exist yet, create baseline snapshot for active items
            if ($stockLevels->isEmpty()) {
                $items = InventoryItem::when($countType === 'ABC', fn ($q) => $q->where('abc_class', 'A'))->get();
                $locId = $locationId ?? StorageLocation::where('status', 'active')->value('id') ?? 1;

                foreach ($items as $item) {
                    CycleCountLine::create([
                        'cycle_count_doc_id' => $doc->id,
                        'item_id' => $item->id,
                        'storage_location_id' => $locId,
                        'item_batch_id' => null,
                        'book_quantity_snapshot' => (int) $item->quantity_on_hand,
                        'counted_quantity_blind' => null,
                        'variance_quantity' => 0,
                        'variance_value' => 0.00,
                        'recount_required' => false,
                        'status' => 'pending',
                    ]);
                }
            } else {
                foreach ($stockLevels as $level) {
                    CycleCountLine::create([
                        'cycle_count_doc_id' => $doc->id,
                        'item_id' => $level->item_id,
                        'storage_location_id' => $level->storage_location_id,
                        'item_batch_id' => $level->item_batch_id,
                        'book_quantity_snapshot' => (int) $level->quantity,
                        'counted_quantity_blind' => null,
                        'variance_quantity' => 0,
                        'variance_value' => 0.00,
                        'recount_required' => false,
                        'status' => 'pending',
                    ]);
                }
            }

            $this->auditLogger->record(
                AuditAction::ScheduledCycleCount,
                actor: $scheduler,
                target: $doc,
                description: "Generated Cycle Count Document {$doc->document_number} ({$countType})",
                newValues: [
                    'document_number' => $doc->document_number,
                    'line_count' => $doc->lines()->count(),
                ]
            );

            return $doc;
        });
    }

    /**
     * Counter submits blind count entries without seeing expected numbers.
     *
     * @param  array<int, int>  $counts  [line_id => counted_qty]
     */
    public function submitBlindCounts(CycleCountDoc $doc, array $counts, User $counter): CycleCountDoc
    {
        return DB::transaction(function () use ($doc, $counts, $counter) {
            $cc = CycleCountDoc::lockForUpdate()->with('lines.item')->findOrFail($doc->id);

            $anyRecountNeeded = false;

            foreach ($cc->lines as $line) {
                if (!array_key_exists($line->id, $counts)) {
                    continue;
                }

                $counted = (int) $counts[$line->id];
                $snapshot = $line->book_quantity_snapshot;
                $delta = $counted - $snapshot;
                $unitCost = (float) ($line->item->unit_cost ?? 0);
                $varianceVal = round($delta * $unitCost, 2);

                // Tolerance checks: >2% variance or >₱5,000 value triggers recount
                $pctVariance = $snapshot > 0 ? abs($delta / $snapshot) : ($counted > 0 ? 1.0 : 0.0);
                $recountRequired = ($pctVariance > 0.02) || (abs($varianceVal) > 5000.00);

                if ($recountRequired) {
                    $anyRecountNeeded = true;
                }

                $line->counted_quantity_blind = $counted;
                $line->variance_quantity = $delta;
                $line->variance_value = $varianceVal;
                $line->recount_required = $recountRequired;
                $line->status = $recountRequired ? 'recount_requested' : 'counted';
                $line->save();
            }

            $cc->status = $anyRecountNeeded ? 'recount_pending' : 'completed';
            $cc->completed_at = now();
            $cc->save();

            $this->auditLogger->record(
                AuditAction::RecordedBlindCount,
                actor: $counter,
                target: $cc,
                description: "Recorded blind physical counts for Cycle Count {$cc->document_number} (" . ($anyRecountNeeded ? 'Recounts flagged' : 'Completed') . ")",
                newValues: [
                    'document_number' => $cc->document_number,
                    'status' => $cc->status,
                ]
            );

            return $cc;
        });
    }

    /**
     * Approve cycle count variances and post atomic inventory adjustments.
     * Dual authorization required if any line variance exceeds ₱25,000 ($500).
     */
    public function approveAndPostAdjustments(CycleCountDoc $doc, User $approver): CycleCountDoc
    {
        return DB::transaction(function () use ($doc, $approver) {
            $cc = CycleCountDoc::lockForUpdate()->with('lines.item')->findOrFail($doc->id);

            // Segregation of Duties: Counter cannot approve their own counts
            if ($cc->assigned_counter_id === $approver->id) {
                throw new DomainException('Segregation of Duties Violation: Counter cannot approve their own cycle count.');
            }

            foreach ($cc->lines as $line) {
                if ($line->variance_quantity === 0) {
                    $line->status = 'resolved';
                    $line->save();
                    continue;
                }

                $delta = $line->variance_quantity;
                $varianceVal = $line->variance_value;
                $item = InventoryItem::lockForUpdate()->findOrFail($line->item_id);

                // Enforce dual authorization threshold for > ₱25,000
                if (abs($varianceVal) > 25000.00 && !$approver->isSuperAdministrator() && !$approver->isAdministrator()) {
                    throw new DomainException("Cycle count variance for {$item->name} (₱" . number_format(abs($varianceVal), 2) . ") exceeds ₱25,000 threshold and requires Plant Controller / Administrator authorization.");
                }

                $adjNumber = 'ADJ-' . now()->format('Ymd') . '-' . str_pad((string) (InventoryAdjustment::count() + 1), 4, '0', STR_PAD_LEFT);

                // Create Inventory Adjustment record
                $adjustment = InventoryAdjustment::create([
                    'adjustment_number' => $adjNumber,
                    'item_id' => $item->id,
                    'storage_location_id' => $line->storage_location_id,
                    'item_batch_id' => $line->item_batch_id,
                    'current_quantity' => $line->book_quantity_snapshot,
                    'adjustment_quantity' => $delta,
                    'resulting_quantity' => (int) $line->counted_quantity_blind,
                    'unit_cost' => $item->unit_cost,
                    'total_variance_value' => $varianceVal,
                    'adjustment_type' => 'count_variance',
                    'reason_code' => 'count_variance',
                    'explanation' => "Cycle count variance adjustment for {$cc->document_number}",
                    'status' => 'posted',
                    'requested_by_id' => $cc->assigned_counter_id,
                    'approved_by_id' => $approver->id,
                    'posted_at' => now(),
                ]);

                // Atomically update balance
                $this->automationService->adjustStockLevel($item->id, $line->storage_location_id, $line->item_batch_id, $delta);

                // Write immutable movement ledger
                StockMovement::create([
                    'item_id' => $item->id,
                    'item_batch_id' => $line->item_batch_id,
                    'movement_type' => MovementType::Adjustment,
                    'quantity' => $delta,
                    'unit_cost' => $item->unit_cost,
                    'from_location_id' => $delta < 0 ? $line->storage_location_id : null,
                    'to_location_id' => $delta > 0 ? $line->storage_location_id : null,
                    'reference_type' => InventoryAdjustment::class,
                    'reference_id' => $adjustment->id,
                    'remarks' => "Cycle Count Variance {$cc->document_number}: Delta {$delta} units",
                    'moved_at' => now(),
                    'user_id' => $approver->id,
                ]);

                $this->automationService->syncItemTotals($item);

                $line->inventory_adjustment_id = $adjustment->id;
                $line->status = 'resolved';
                $line->save();

                $this->auditLogger->record(
                    AuditAction::PostedInventoryAdjustment,
                    actor: $approver,
                    target: $adjustment,
                    description: "Posted count variance adjustment {$adjustment->adjustment_number} ({$delta} units) for {$item->name}",
                    newValues: [
                        'adjustment_number' => $adjustment->adjustment_number,
                        'delta' => $delta,
                        'variance_value' => $varianceVal,
                    ]
                );
            }

            $cc->status = 'posted';
            $cc->approved_by_id = $approver->id;
            $cc->approved_at = now();
            $cc->save();

            return $cc;
        });
    }
}
