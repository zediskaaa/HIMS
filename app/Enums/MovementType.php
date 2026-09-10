<?php

namespace App\Enums;

enum MovementType: string
{
    case StockIn = 'stock_in';
    case StockOut = 'stock_out';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';
    case Disposal = 'disposal';
    case Issuance = 'issuance';
    case ReturnToSupplier = 'return_to_supplier';
    case Quarantine = 'quarantine';
    case QualityRelease = 'quality_release';
    case QualityReject = 'quality_reject';
    case TransferDispatch = 'transfer_dispatch';
    case TransferReceipt = 'transfer_receipt';
    case DepartmentReturn = 'department_return';

    public function label(): string
    {
        return match ($this) {
            self::StockIn => 'Stock In',
            self::StockOut => 'Stock Out',
            self::Transfer => 'Transfer',
            self::Adjustment => 'Adjustment',
            self::Disposal => 'Disposal',
            self::Issuance => 'Issuance',
            self::ReturnToSupplier => 'Return to Supplier',
            self::Quarantine => 'Quarantine Intake',
            self::QualityRelease => 'Quality Release',
            self::QualityReject => 'Quality Rejection',
            self::TransferDispatch => 'Transfer Dispatch',
            self::TransferReceipt => 'Transfer Receipt',
            self::DepartmentReturn => 'Department Return',
        };
    }

    /**
     * Whether this movement removes stock from its source location.
     */
    public function decrementsSource(): bool
    {
        return in_array($this, [
            self::StockOut,
            self::Transfer,
            self::Disposal,
            self::Issuance,
            self::ReturnToSupplier,
            self::TransferDispatch,
            self::QualityReject,
        ], true);
    }

    /**
     * Whether this movement adds stock to its destination location.
     */
    public function incrementsDestination(): bool
    {
        return in_array($this, [
            self::StockIn,
            self::Transfer,
            self::QualityRelease,
            self::TransferReceipt,
            self::DepartmentReturn,
        ], true);
    }

    /**
     * Whether this movement represents real demand for the item.
     *
     * Demand forecasting counts only stock that was actually consumed by the
     * hospital. A transfer relocates stock without anyone using it, a disposal
     * is waste rather than demand, a return goes back to the vendor, and an
     * adjustment corrects a counting error — treating any of them as demand
     * would inflate the forecast and order stock nobody needs.
     */
    public function isConsumption(): bool
    {
        return in_array($this, [self::StockOut, self::Issuance], true);
    }

    /**
     * The types that count as consumption.
     *
     * @return array<int, self>
     */
    public static function consumptionCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => $type->isConsumption()));
    }

    /**
     * The stored values that count as consumption, for use in a where-in.
     *
     * @return array<int, string>
     */
    public static function consumptionValues(): array
    {
        return array_map(fn (self $type) => $type->value, self::consumptionCases());
    }

    /**
     * A short explanation of what the type does, shown beside its name in the
     * movement form's dropdown.
     */
    public function hint(): string
    {
        return match ($this) {
            self::StockIn => 'receive into a location',
            self::StockOut => 'consume from a location',
            self::Transfer => 'move between locations',
            self::Adjustment => 'correct a counted balance',
            self::Disposal => 'write off damaged or expired stock',
            self::Issuance => 'dispense to a ward or department',
            self::ReturnToSupplier => 'send back to vendor',
            self::Quarantine => 'intake into quarantine bin',
            self::QualityRelease => 'release from quarantine to active stock',
            self::QualityReject => 'reject from quarantine to blocked',
            self::TransferDispatch => 'dispatch to in-transit location',
            self::TransferReceipt => 'receive from in-transit into destination',
            self::DepartmentReturn => 'return unused stock from department',
        };
    }

    /**
     * The ability a user must hold to record this type of movement.
     *
     * Dispensing to a ward and receiving a delivery are different jobs, so they
     * are gated separately: pharmacy staff hold issue_stock and can therefore
     * record an issuance or a stock-out, but a transfer, a disposal or a return
     * to the supplier needs the wider record_movements, and correcting a
     * balance needs adjust_stock. Declaring it on the type means the controller
     * and the form dropdown both read the rule from the same place.
     */
    public function requiredPermission(): Permission
    {
        return match ($this) {
            self::Issuance, self::StockOut => Permission::IssueStock,
            self::Adjustment => Permission::AdjustStock,
            default => Permission::RecordMovements,
        };
    }

    /**
     * Adjustments carry a signed quantity and are applied directly rather
     * than moving stock between two locations.
     */
    public function isAdjustment(): bool
    {
        return $this === self::Adjustment;
    }

    public function requiresSourceLocation(): bool
    {
        return $this->decrementsSource();
    }

    public function requiresDestinationLocation(): bool
    {
        return $this->incrementsDestination();
    }
}
