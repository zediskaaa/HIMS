<?php

namespace App\Enums;

use App\Models\MaterialRequisition;
use App\Models\SystemRecoveryRecord;
use App\Models\User;
use App\Support\AuthenticationPanel;

/**
 * Allowlisted notification destinations. Notification data never contains an
 * arbitrary URL, and access is checked again when a user follows the link.
 */
enum NotificationDestination: string
{
    case Dashboard = 'dashboard';
    case InventoryAlerts = 'inventory_alerts';
    case MaterialRequisition = 'material_requisition';
    case InventoryAdjustments = 'inventory_adjustments';
    case Procurement = 'procurement';
    case Import = 'import';
    case RecoveryRecord = 'recovery_record';
    case Profile = 'profile';

    public function isAuthorizedFor(User $user): bool
    {
        return match ($this) {
            self::Dashboard => $user->isActive(),
            self::Profile => true,
            self::InventoryAlerts => $user->hasPermission(Permission::AcknowledgeAlerts),
            self::MaterialRequisition => $user->hasPermission(Permission::ApproveRequisition),
            self::InventoryAdjustments => $user->hasPermission(Permission::ApproveAdjustment),
            self::Procurement => $user->hasPermission(Permission::ViewProcurement),
            self::Import => $user->hasPermission(Permission::ManageItems)
                || $user->hasPermission(Permission::ManageLocations)
                || $user->hasPermission(Permission::ManageSuppliers),
            self::RecoveryRecord => $user->isSuperAdministrator()
                && $user->hasPermission(Permission::ManageSystemRecovery),
        };
    }

    /** @param array<string, scalar|null> $parameters */
    public function isAvailable(array $parameters = []): bool
    {
        return match ($this) {
            self::MaterialRequisition => MaterialRequisition::query()
                ->whereKey((int) ($parameters['requisition'] ?? 0))
                ->exists(),
            self::RecoveryRecord => SystemRecoveryRecord::query()
                ->whereKey((int) ($parameters['record'] ?? 0))
                ->exists(),
            default => true,
        };
    }

    /** @param array<string, scalar|null> $parameters */
    public function url(User $user, array $parameters = []): string
    {
        return match ($this) {
            self::Dashboard => route(AuthenticationPanel::forRole($user->role)->dashboardRoute()),
            self::InventoryAlerts => route('inventory.alerts'),
            self::MaterialRequisition => route('inventory.requisitions.show', [
                'requisition' => (int) ($parameters['requisition'] ?? 0),
            ]),
            self::InventoryAdjustments => route('inventory.adjustments'),
            self::Procurement => route('inventory.purchases'),
            self::Import => route('inventory.import.index'),
            self::RecoveryRecord => route('super-admin.recovery.show', [
                'record' => (int) ($parameters['record'] ?? 0),
            ]),
            self::Profile => route('profile.edit'),
        };
    }
}
