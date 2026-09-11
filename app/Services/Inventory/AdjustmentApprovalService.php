<?php

namespace App\Services\Inventory;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Enums\NotificationDestination;
use App\Enums\NotificationPriority;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\InventoryAdjustment;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\HimsNotificationService;
use App\Services\InventoryAutomationService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdjustmentApprovalService
{
    public function __construct(
        private readonly InventoryAutomationService $automationService,
        private readonly AuditLogger $auditLogger,
        private readonly HimsNotificationService $notifications,
    ) {}

    /**
     * Submit an inventory adjustment request.
     *
     * @param  array<string, mixed>  $data
     */
    public function requestAdjustment(array $data, User $requester): InventoryAdjustment
    {
        $adjustment = DB::transaction(function () use ($data, $requester) {
            $item = InventoryItem::lockForUpdate()->findOrFail($data['item_id']);
            $location = StorageLocation::findOrFail($data['storage_location_id']);
            $batchId = $data['item_batch_id'] ?? null;
            $type = $data['adjustment_type']; // increase, decrease, correction
            $qtyInput = (int) $data['quantity'];

            $currentQty = $this->automationService->availableAt($item->id, $location->id, $batchId);

            $delta = match ($type) {
                'increase' => $qtyInput,
                'decrease' => -$qtyInput,
                'correction' => $qtyInput - $currentQty,
                default => $qtyInput,
            };

            if ($delta === 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Proposed adjustment results in zero net change to current stock.']
                ]);
            }

            if ($delta < 0 && ($currentQty + $delta) < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ["Cannot adjust balance below zero. Current: {$currentQty}, Adjustment: {$delta}."]
                ]);
            }

            $resultingQty = $currentQty + $delta;
            $unitCost = (float) ($item->unit_cost ?? 0);
            $totalValue = round($delta * $unitCost, 2);

            $adjNumber = 'ADJ-' . now()->format('Ymd') . '-' . str_pad((string) (InventoryAdjustment::count() + 1), 4, '0', STR_PAD_LEFT);

            $adjustment = InventoryAdjustment::create([
                'adjustment_number' => $adjNumber,
                'item_id' => $item->id,
                'storage_location_id' => $location->id,
                'item_batch_id' => $batchId,
                'current_quantity' => $currentQty,
                'adjustment_quantity' => $delta,
                'resulting_quantity' => $resultingQty,
                'unit_cost' => $unitCost,
                'total_variance_value' => $totalValue,
                'adjustment_type' => $type,
                'reason_code' => $data['reason_code'] ?? 'data_correction',
                'explanation' => $data['explanation'],
                'status' => 'pending_approval',
                'requested_by_id' => $requester->id,
            ]);

            return $adjustment;
        });

        $this->notifications->sendToPermission(
            Permission::ApproveAdjustment,
            "inventory-adjustment:{$adjustment->id}:tier-1",
            'Stock adjustment approval required',
            "{$adjustment->adjustment_number} is awaiting authorization.",
            abs((float) $adjustment->total_variance_value) > 25000
                ? NotificationPriority::Warning
                : NotificationPriority::Info,
            NotificationDestination::InventoryAdjustments,
            except: $requester,
        );

        return $adjustment;
    }

    /**
     * Approve and post an inventory adjustment.
     * Enforces dual authorization if adjustment variance value exceeds ₱25,000 ($500).
     */
    public function approveAndPost(InventoryAdjustment $adjustment, User $approver): InventoryAdjustment
    {
        $updated = DB::transaction(function () use ($adjustment, $approver) {
            $adj = InventoryAdjustment::lockForUpdate()->with('item')->findOrFail($adjustment->id);

            // Segregation of Duties: Requester cannot approve their own adjustment
            if ($adj->requested_by_id === $approver->id) {
                throw new DomainException('Segregation of Duties Violation: You cannot approve your own adjustment request.');
            }

            if ($adj->status === 'posted') {
                throw new DomainException("Adjustment {$adj->adjustment_number} is already posted.");
            }

            $threshold = 25000.00; // ₱25,000 (~$500)
            $isHighValue = abs((float) $adj->total_variance_value) > $threshold;

            if ($isHighValue) {
                // If high-value adjustment, check if it already has first approval
                if (!$adj->approved_by_id) {
                    // First tier approval (Warehouse Manager)
                    $adj->approved_by_id = $approver->id;
                    $adj->status = 'pending_second_approval';
                    $adj->save();

                    $this->auditLogger->record(
                        AuditAction::ApprovedInventoryAdjustment,
                        actor: $approver,
                        target: $adj,
                        description: "First-tier approval for high-value adjustment {$adj->adjustment_number} (₱" . number_format(abs((float) $adj->total_variance_value), 2) . "). Awaiting Plant Controller second authorization.",
                        newValues: [
                            'adjustment_number' => $adj->adjustment_number,
                            'status' => $adj->status,
                        ]
                    );

                    return $adj;
                } else {
                    // Second tier approval (Plant Controller / Finance Auditor / Super Admin)
                    if ($adj->approved_by_id === $approver->id) {
                        throw new DomainException('Dual Authorization Violation: The second approver must be distinct from the first approver.');
                    }

                    if (!$approver->isSuperAdministrator() && !$approver->isAdministrator()) {
                        throw new DomainException('Plant Controller / Administrator authority required for high-value adjustment second approval.');
                    }

                    $adj->second_approved_by_id = $approver->id;
                }
            } else {
                $adj->approved_by_id = $approver->id;
            }

            // Execute atomic update and movement write
            $item = InventoryItem::lockForUpdate()->findOrFail($adj->item_id);

            $this->automationService->adjustStockLevel(
                $item->id,
                $adj->storage_location_id,
                $adj->item_batch_id,
                $adj->adjustment_quantity
            );

            StockMovement::create([
                'item_id' => $item->id,
                'item_batch_id' => $adj->item_batch_id,
                'movement_type' => MovementType::Adjustment,
                'quantity' => $adj->adjustment_quantity,
                'unit_cost' => $adj->unit_cost,
                'from_location_id' => $adj->adjustment_quantity < 0 ? $adj->storage_location_id : null,
                'to_location_id' => $adj->adjustment_quantity > 0 ? $adj->storage_location_id : null,
                'reference_type' => InventoryAdjustment::class,
                'reference_id' => $adj->id,
                'remarks' => "Approved Adjustment {$adj->adjustment_number} [{$adj->reason_code}]: {$adj->explanation}",
                'moved_at' => now(),
                'user_id' => $approver->id,
            ]);

            $this->automationService->syncItemTotals($item);

            $adj->status = 'posted';
            $adj->posted_at = now();
            $adj->save();

            $this->auditLogger->record(
                AuditAction::PostedInventoryAdjustment,
                actor: $approver,
                target: $adj,
                description: "Posted approved inventory adjustment {$adj->adjustment_number} for {$item->name} (Delta: {$adj->adjustment_quantity})",
                newValues: [
                    'adjustment_number' => $adj->adjustment_number,
                    'delta' => $adj->adjustment_quantity,
                    'resulting_quantity' => $adj->resulting_quantity,
                ]
            );

            return $adj;
        });

        if ($updated->status === 'pending_second_approval') {
            $this->notifications->sendToRoles(
                [UserRole::Administrator],
                "inventory-adjustment:{$updated->id}:tier-2",
                'Second stock adjustment approval required',
                "High-value adjustment {$updated->adjustment_number} requires a distinct second authorization.",
                NotificationPriority::Warning,
                NotificationDestination::InventoryAdjustments,
                except: $approver,
            );
        }

        return $updated;
    }
}
