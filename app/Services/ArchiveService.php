<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArchiveService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Archive a supplier record.
     */
    public function archiveSupplier(Supplier $supplier, User $actor, ?string $reason = null): Supplier
    {
        return DB::transaction(function () use ($supplier, $actor, $reason): Supplier {
            /** @var Supplier $locked */
            $locked = Supplier::query()->whereKey($supplier->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isArchived()) {
                throw ValidationException::withMessages([
                    'archive' => ['This supplier is already archived.'],
                ]);
            }

            $oldStatus = $locked->status?->value ?? 'active';

            $locked->update([
                'status' => SupplierStatus::Archived,
                'archived_at' => now(),
                'archived_by' => $actor->id,
                'archive_reason' => $reason,
                'suspension_reason' => $reason,
            ]);

            $this->audit->log(
                AuditAction::ArchivedSupplier,
                $actor,
                "Archived supplier {$locked->name} from active procurement registry.",
                $locked,
                $locked->name,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => SupplierStatus::Archived->value,
                    'archived_at' => $locked->archived_at?->toIso8601String(),
                    'archive_reason' => $reason,
                ],
                businessReason: $reason,
            );

            return $locked->fresh(['archivedBy']);
        });
    }

    /**
     * Restore an archived supplier.
     */
    public function unarchiveSupplier(Supplier $supplier, User $actor): Supplier
    {
        return DB::transaction(function () use ($supplier, $actor): Supplier {
            /** @var Supplier $locked */
            $locked = Supplier::query()->whereKey($supplier->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isArchived()) {
                throw ValidationException::withMessages([
                    'archive' => ['This supplier is not currently archived.'],
                ]);
            }

            // Conflict safety check: ensure no active supplier has the same identity key or tax number
            if (filled($locked->tax_number)) {
                $conflict = Supplier::query()
                    ->whereKeyNot($locked->id)
                    ->where('status', '!=', SupplierStatus::Archived->value)
                    ->where('tax_number', $locked->tax_number)
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'unarchive' => ["Cannot restore supplier: Another active supplier already exists with Tax ID {$locked->tax_number}."],
                    ]);
                }
            }

            if (filled($locked->identity_key)) {
                $conflict = Supplier::query()
                    ->whereKeyNot($locked->id)
                    ->where('status', '!=', SupplierStatus::Archived->value)
                    ->where('identity_key', $locked->identity_key)
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'unarchive' => ['Cannot restore supplier: A supplier with matching corporate identity is currently active.'],
                    ]);
                }
            }

            $locked->update([
                'status' => SupplierStatus::Active,
                'archived_at' => null,
                'archived_by' => null,
                'archive_reason' => null,
                'suspension_reason' => null,
            ]);

            $this->audit->log(
                AuditAction::UnarchivedSupplier,
                $actor,
                "Restored supplier {$locked->name} to active registry.",
                $locked,
                $locked->name,
                oldValues: ['status' => SupplierStatus::Archived->value],
                newValues: ['status' => SupplierStatus::Active->value],
            );

            return $locked->fresh(['archivedBy']);
        });
    }

    /**
     * Archive an inventory item.
     */
    public function archiveItem(InventoryItem $item, User $actor, ?string $reason = null): InventoryItem
    {
        return DB::transaction(function () use ($item, $actor, $reason): InventoryItem {
            /** @var InventoryItem $locked */
            $locked = InventoryItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isArchived()) {
                throw ValidationException::withMessages([
                    'archive' => ['This inventory item is already archived.'],
                ]);
            }

            $oldStatus = $locked->status ?? 'active';

            // Archive the item without modifying its existing stock balance or movement history
            $locked->update([
                'status' => 'archived',
                'archived_at' => now(),
                'archived_by' => $actor->id,
                'archive_reason' => $reason,
            ]);

            $this->audit->log(
                AuditAction::ArchivedInventoryItem,
                $actor,
                "Archived inventory item {$locked->name} (SKU: {$locked->sku}). Historical stock and transactions preserved.",
                $locked,
                $locked->sku,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => 'archived',
                    'quantity_on_hand' => $locked->quantity_on_hand,
                    'archived_at' => $locked->archived_at?->toIso8601String(),
                    'archive_reason' => $reason,
                ],
                businessReason: $reason,
            );

            return $locked->fresh(['archivedBy', 'category', 'defaultLocation']);
        });
    }

    /**
     * Restore an archived inventory item.
     */
    public function unarchiveItem(InventoryItem $item, User $actor): InventoryItem
    {
        return DB::transaction(function () use ($item, $actor): InventoryItem {
            /** @var InventoryItem $locked */
            $locked = InventoryItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isArchived()) {
                throw ValidationException::withMessages([
                    'archive' => ['This inventory item is not currently archived.'],
                ]);
            }

            // Conflict safety check: ensure no active item has the same SKU, Barcode, or GTIN
            $skuConflict = InventoryItem::query()
                ->whereKeyNot($locked->id)
                ->where('status', '!=', 'archived')
                ->where('sku', $locked->sku)
                ->exists();

            if ($skuConflict) {
                throw ValidationException::withMessages([
                    'unarchive' => ["Cannot restore item: SKU '{$locked->sku}' is already in use by an active inventory item."],
                ]);
            }

            if (filled($locked->barcode_value)) {
                $barcodeConflict = InventoryItem::query()
                    ->whereKeyNot($locked->id)
                    ->where('status', '!=', 'archived')
                    ->where('barcode_value', $locked->barcode_value)
                    ->exists();

                if ($barcodeConflict) {
                    throw ValidationException::withMessages([
                        'unarchive' => ["Cannot restore item: Barcode '{$locked->barcode_value}' is currently assigned to an active item."],
                    ]);
                }
            }

            if (filled($locked->gtin)) {
                $gtinConflict = InventoryItem::query()
                    ->whereKeyNot($locked->id)
                    ->where('status', '!=', 'archived')
                    ->where('gtin', $locked->gtin)
                    ->exists();

                if ($gtinConflict) {
                    throw ValidationException::withMessages([
                        'unarchive' => ["Cannot restore item: GTIN '{$locked->gtin}' is currently assigned to an active item."],
                    ]);
                }
            }

            $locked->update([
                'status' => 'active',
                'archived_at' => null,
                'archived_by' => null,
                'archive_reason' => null,
            ]);

            $this->audit->log(
                AuditAction::UnarchivedInventoryItem,
                $actor,
                "Restored inventory item {$locked->name} (SKU: {$locked->sku}) to active catalog.",
                $locked,
                $locked->sku,
                oldValues: ['status' => 'archived'],
                newValues: ['status' => 'active'],
            );

            return $locked->fresh(['archivedBy', 'category', 'defaultLocation']);
        });
    }

    /**
     * Archive a user account.
     */
    public function archiveUser(User $user, User $actor, ?string $reason = null): User
    {
        return DB::transaction(function () use ($user, $actor, $reason): User {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isArchived()) {
                throw ValidationException::withMessages([
                    'archive' => ['This user account is already archived.'],
                ]);
            }

            if ($locked->is($actor)) {
                throw ValidationException::withMessages([
                    'archive' => ['You cannot archive your own user account.'],
                ]);
            }

            if ($locked->isProtected()) {
                throw ValidationException::withMessages([
                    'archive' => ['The protected system Super Administrator account cannot be archived.'],
                ]);
            }

            if ($locked->isAdministrator()) {
                $remainingAdmins = User::query()
                    ->administrators()
                    ->where('status', UserStatus::Active->value)
                    ->whereKeyNot($locked->id)
                    ->count();

                if ($remainingAdmins === 0) {
                    throw ValidationException::withMessages([
                        'archive' => ['Cannot archive the only remaining active administrator. Promote another administrator first.'],
                    ]);
                }
            }

            $oldStatus = $locked->status?->value ?? 'active';

            // Invalidate session security tokens and remember token
            $locked->tokens()->delete();
            $locked->remember_token = null;

            $locked->update([
                'status' => UserStatus::Archived,
                'archived_at' => now(),
                'archived_by' => $actor->id,
                'archive_reason' => $reason,
            ]);

            $this->audit->log(
                AuditAction::ArchivedUser,
                $actor,
                "Archived user {$locked->name} (Employee ID: {$locked->employee_id}). Active access revoked, historical logs preserved.",
                $locked,
                $locked->name,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => UserStatus::Archived->value,
                    'archived_at' => $locked->archived_at?->toIso8601String(),
                    'archive_reason' => $reason,
                ],
                businessReason: $reason,
            );

            return $locked->fresh(['archivedBy']);
        });
    }

    /**
     * Restore an archived user account.
     */
    public function unarchiveUser(User $user, User $actor): User
    {
        return DB::transaction(function () use ($user, $actor): User {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isArchived()) {
                throw ValidationException::withMessages([
                    'archive' => ['This user account is not currently archived.'],
                ]);
            }

            // Conflict safety check: ensure no active user has the same email or employee_id
            $emailConflict = User::query()
                ->whereKeyNot($locked->id)
                ->where('status', '!=', UserStatus::Archived->value)
                ->where('email', $locked->email)
                ->exists();

            if ($emailConflict) {
                throw ValidationException::withMessages([
                    'unarchive' => ["Cannot restore account: Email '{$locked->email}' is currently assigned to another active account."],
                ]);
            }

            if (filled($locked->employee_id)) {
                $employeeIdConflict = User::query()
                    ->whereKeyNot($locked->id)
                    ->where('status', '!=', UserStatus::Archived->value)
                    ->where('employee_id', $locked->employee_id)
                    ->exists();

                if ($employeeIdConflict) {
                    throw ValidationException::withMessages([
                        'unarchive' => ["Cannot restore account: Employee ID '{$locked->employee_id}' is currently assigned to another active account."],
                    ]);
                }
            }

            $locked->update([
                'status' => UserStatus::Active,
                'archived_at' => null,
                'archived_by' => null,
                'archive_reason' => null,
            ]);

            $this->audit->log(
                AuditAction::UnarchivedUser,
                $actor,
                "Restored user {$locked->name} to active status.",
                $locked,
                $locked->name,
                oldValues: ['status' => UserStatus::Archived->value],
                newValues: ['status' => UserStatus::Active->value],
            );

            return $locked->fresh(['archivedBy']);
        });
    }

    /**
     * Get archived records with server-side pagination, record-type filtering, search, and date filters.
     */
    public function getArchivedRecords(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $type = $filters['type'] ?? 'all';
        $search = trim((string) ($filters['search'] ?? ''));
        $dateFrom = $filters['archive_date_from'] ?? null;
        $dateTo = $filters['archive_date_to'] ?? null;
        $archivedBy = $filters['archived_by'] ?? null;

        if ($type === 'items') {
            return InventoryItem::query()
                ->archived()
                ->with(['archivedBy', 'category', 'defaultLocation'])
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($q) use ($search): void {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhere('barcode_value', 'like', "%{$search}%")
                            ->orWhere('generic_name', 'like', "%{$search}%");
                    });
                })
                ->when($dateFrom, fn ($q) => $q->whereDate('archived_at', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('archived_at', '<=', $dateTo))
                ->when($archivedBy, fn ($q) => $q->where('archived_by', $archivedBy))
                ->latest('archived_at')
                ->latest('id')
                ->paginate($perPage)
                ->withQueryString();
        }

        if ($type === 'suppliers') {
            return Supplier::query()
                ->archived()
                ->with(['archivedBy'])
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($q) use ($search): void {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('trade_name', 'like', "%{$search}%")
                            ->orWhere('tax_number', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->when($dateFrom, fn ($q) => $q->whereDate('archived_at', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('archived_at', '<=', $dateTo))
                ->when($archivedBy, fn ($q) => $q->where('archived_by', $archivedBy))
                ->latest('archived_at')
                ->latest('id')
                ->paginate($perPage)
                ->withQueryString();
        }

        if ($type === 'users') {
            return User::query()
                ->archived()
                ->with(['archivedBy'])
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($q) use ($search): void {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('employee_id', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('department', 'like', "%{$search}%");
                    });
                })
                ->when($dateFrom, fn ($q) => $q->whereDate('archived_at', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('archived_at', '<=', $dateTo))
                ->when($archivedBy, fn ($q) => $q->where('archived_by', $archivedBy))
                ->latest('archived_at')
                ->latest('id')
                ->paginate($perPage)
                ->withQueryString();
        }

        // 'all' type: Unified union query across items, suppliers, and users
        $itemsQuery = DB::table('inventory_items')
            ->selectRaw("'item' as record_type, id, name as record_title, sku as identifier, archived_at, archived_by, archive_reason")
            ->where('status', 'archived')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode_value', 'like', "%{$search}%");
                });
            })
            ->when($dateFrom, fn ($q) => $q->whereDate('archived_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('archived_at', '<=', $dateTo))
            ->when($archivedBy, fn ($q) => $q->where('archived_by', $archivedBy));

        $supplierIdentifier = DB::connection()->getDriverName() === 'sqlite'
            ? "coalesce(tax_number, 'SUP-' || id)"
            : "COALESCE(tax_number, CONCAT('SUP-', id))";

        $suppliersQuery = DB::table('suppliers')
            ->selectRaw("'supplier' as record_type, id, name as record_title, {$supplierIdentifier} as identifier, archived_at, archived_by, archive_reason")
            ->where('status', SupplierStatus::Archived->value)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('trade_name', 'like', "%{$search}%")
                        ->orWhere('tax_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($dateFrom, fn ($q) => $q->whereDate('archived_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('archived_at', '<=', $dateTo))
            ->when($archivedBy, fn ($q) => $q->where('archived_by', $archivedBy));

        $usersQuery = DB::table('users')
            ->selectRaw("'user' as record_type, id, name as record_title, employee_id as identifier, archived_at, archived_by, archive_reason")
            ->where('status', UserStatus::Archived->value)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%");
                });
            })
            ->when($dateFrom, fn ($q) => $q->whereDate('archived_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('archived_at', '<=', $dateTo))
            ->when($archivedBy, fn ($q) => $q->where('archived_by', $archivedBy));

        $unionQuery = $itemsQuery->unionAll($suppliersQuery)->unionAll($usersQuery);

        $paginator = DB::query()
            ->fromSub($unionQuery, 'archived_records')
            ->orderByDesc('archived_at')
            ->paginate($perPage)
            ->withQueryString();

        // Eager-load actor user models for archived_by references
        $archivedByUserIds = collect($paginator->items())->pluck('archived_by')->filter()->unique()->all();
        $archivedByUsers = $archivedByUserIds !== []
            ? User::query()->whereIn('id', $archivedByUserIds)->get()->keyBy('id')
            : collect();

        foreach ($paginator->items() as $item) {
            $item->archived_by_user = $archivedByUsers->get($item->archived_by);
        }

        return $paginator;
    }

    /**
     * Get live summary counters of archived records across categories.
     *
     * @return array{total: int, items: int, suppliers: int, users: int}
     */
    public function getArchiveCounts(): array
    {
        $items = InventoryItem::archived()->count();
        $suppliers = Supplier::archived()->count();
        $users = User::archived()->count();

        return [
            'total' => $items + $suppliers + $users,
            'items' => $items,
            'suppliers' => $suppliers,
            'users' => $users,
        ];
    }
}
