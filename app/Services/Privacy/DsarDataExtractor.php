<?php

namespace App\Services\Privacy;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Collection;

class DsarDataExtractor
{
    /**
     * Extract sanitized account profile and security configuration.
     * All authentication secrets (passwords, blind hashes, TOTP secrets, recovery tokens) are strictly excluded.
     *
     * @return array<string, mixed>
     */
    public function extractAccountProfile(User $user): array
    {
        return [
            'personal_identity' => [
                'user_id' => $user->id,
                'full_name' => $user->name,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'surname' => $user->surname,
                'employee_id' => $user->employee_id,
                'department' => $user->department?->value ?? (string) $user->department,
                'official_email' => $user->email,
                'contact_phone' => $user->phone,
            ],
            'account_status' => [
                'current_status' => $user->status->value,
                'is_active' => $user->status->value === 'active',
                'is_protected' => (bool) $user->is_protected,
                'created_at' => $user->created_at?->toIso8601String(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'password_last_changed_at' => $user->password_changed_at?->toIso8601String(),
                'archived_at' => $user->archived_at?->toIso8601String(),
                'archived_by' => $user->archived_by,
                'archive_reason' => $user->archive_reason,
            ],
            'security_settings' => [
                'email_mfa_enabled' => (bool) $user->mfa_enabled,
                'sms_mfa_enabled' => (bool) $user->sms_mfa_enabled,
                'authenticator_totp_enabled' => $user->authenticatorMfaEnabled(),
                'authenticator_enabled_at' => $user->authenticator_enabled_at?->toIso8601String(),
                'session_timeout_reminder_enabled' => (bool) $user->session_timeout_reminder_enabled,
                'password_policy' => [
                    'min_length' => 8,
                    'complexity' => 'Requires uppercase, lowercase, numbers, and symbols',
                    'expiration_period_days' => (int) config('auth.password_expiration.days', 90),
                    'global_reuse_prevention' => true,
                ],
            ],
        ];
    }

    /**
     * Extract assigned roles, RBAC permissions, and access scope.
     *
     * @return array<string, mixed>
     */
    public function extractRoleAndAccess(User $user): array
    {
        $permissions = $user->role->permissions();
        $formattedPermissions = array_map(fn ($perm) => [
            'key' => $perm->value,
            'name' => $perm->name,
        ], $permissions);

        // Access scope analysis based on permissions
        $scopes = [
            'administrative_privileges' => $user->role->isAdministrator(),
            'super_admin_privileges' => $user->role->isSuperAdministrator(),
            'procurement_authority' => $user->hasRole(\App\Enums\UserRole::Administrator)
                || $user->hasRole(\App\Enums\UserRole::InventoryManager),
            'stock_mutation_authority' => $user->hasRole(\App\Enums\UserRole::InventoryManager)
                || $user->hasRole(\App\Enums\UserRole::WarehouseStaff),
            'clinical_dispensing_authority' => $user->hasRole(\App\Enums\UserRole::PharmacyStaff),
            'audit_inspection_authority' => $user->hasRole(\App\Enums\UserRole::Auditor)
                || $user->hasRole(\App\Enums\UserRole::SuperAdministrator),
        ];

        // Retrieve historical role/account lifecycle events from audit logs
        $roleHistory = AuditLog::query()
            ->where(function ($query) use ($user) {
                $query->where('target_type', User::class)
                    ->where('target_id', (string) $user->id);
            })
            ->whereIn('action', [
                AuditAction::CreatedUser,
                AuditAction::UpdatedUser,
                AuditAction::ArchivedUser,
                AuditAction::UnarchivedUser,
                AuditAction::TemporarilyLockedUser,
                AuditAction::UnlockedUser,
            ])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (AuditLog $log) => [
                'timestamp' => $log->authoritativeTimestamp()->toIso8601String(),
                'action' => $log->action->value,
                'action_label' => $log->action->label(),
                'performed_by' => $log->displayActorName(),
                'description' => $log->description,
                'changes' => [
                    'old' => $log->old_values,
                    'new' => $log->new_values,
                ],
            ])
            ->values()
            ->all();

        return [
            'current_role' => [
                'key' => $user->role->value,
                'title' => $user->role->label(),
                'description' => $user->role->description(),
            ],
            'access_scope' => $scopes,
            'rbac_permissions_count' => count($formattedPermissions),
            'rbac_permissions' => $formattedPermissions,
            'role_change_history' => $roleHistory,
        ];
    }

    /**
     * Extract the requesting user's personal activity records from audit logs.
     * Uses query chunking to avoid loading unbounded rows into memory.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function extractPersonalActivity(User $user, int $limit = 5000): Collection
    {
        $activities = collect();

        // 1. Logs where the user acted as the actor
        AuditLog::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->chunk(250, function ($logs) use (&$activities) {
                foreach ($logs as $log) {
                    $activities->push($this->formatAuditRecord($log));
                }
            });

        // 2. Logs where administrative actions specifically targeted this user's account
        AuditLog::query()
            ->where('target_type', User::class)
            ->where('target_id', (string) $user->id)
            ->where('user_id', '!=', $user->id) // avoid duplicates
            ->orderBy('created_at', 'desc')
            ->take(500)
            ->chunk(100, function ($logs) use (&$activities) {
                foreach ($logs as $log) {
                    $activities->push($this->formatAuditRecord($log, isTarget: true));
                }
            });

        // Sort all chronologically descending
        return $activities->sortByDesc('timestamp_utc')->values();
    }

    /**
     * Format a single AuditLog record into standardized portable activity schema.
     *
     * @return array<string, mixed>
     */
    private function formatAuditRecord(AuditLog $log, bool $isTarget = false): array
    {
        return [
            'event_id' => $log->event_id ?? 'EVT-' . $log->id,
            'timestamp_utc' => $log->authoritativeTimestamp()->toIso8601String(),
            'timestamp_display' => $log->displayTimestamp()->format('Y-m-d H:i:s') . ' ' . $log->displayTimezoneLabel(),
            'event_type' => $log->action->value,
            'action_name' => $log->action->label(),
            'category' => $log->event_category ?: $log->action->category(),
            'module' => $log->module ?: $log->action->module(),
            'relationship' => $isTarget ? 'Target Account' : 'Account Actor',
            'actor_name' => $log->displayActorName(),
            'target_type' => $log->target_type ? class_basename($log->target_type) : null,
            'target_reference' => $log->target_reference ?: ($log->target_id ? '#' . $log->target_id : null),
            'target_name' => $log->target_name,
            'description' => $log->description,
            'business_reason' => $log->business_reason,
            'outcome' => $log->outcome ?: 'success',
            'ip_address' => $log->ip_address,
            'device' => $log->deviceSummary() ?: 'System Session',
            'location' => $log->locationSummary() ?: ($log->location_source ? 'Approximate: ' . $log->location_source : null),
        ];
    }
}
