<?php

namespace App\Services;

use App\Enums\ActivationCancellationReason;
use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AccountActivationCancelled;
use App\Notifications\AccountCreated;
use App\Services\Sms\SmsOtpDelivery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Owns the rules that stop the user-management screen locking everyone out.
 *
 * Two failure modes are easy to reach by accident and impossible to undo from
 * the UI afterwards: an administrator removing their own access mid-session,
 * and the last remaining administrator being deactivated or demoted, which
 * leaves a system nobody can administer. Both are refused here rather than in
 * the controller so the API and any future console command inherit the guard.
 */
class UserAccountService
{
    public function __construct(
        private readonly PasswordHistoryService $passwords,
        private readonly AuditLogger $audit,
        private readonly SmsOtpDelivery $sms,
    ) {}

    /**
     * Roles the actor may assign. Super Admin may manage Administrator
     * accounts, while the Super Administrator role is never exposed as a
     * creatable or demotable role through the general account form.
     *
     * @return array<int, UserRole>
     */
    public function assignableRoles(User $actor, ?User $target = null): array
    {
        if ($target?->role?->isSupplier() && $actor->can(Permission::ApproveSuppliers->value)) {
            return [UserRole::VendorAdministrator, UserRole::VendorOperations, UserRole::VendorFinance];
        }

        // Keep every existing Super Administrator on that role during ordinary
        // account edits. Additional Super Administrators created by the CLI are
        // intentionally not protected records, so checking only is_protected
        // would silently submit the first visible role from the HTML select.
        if ($actor->isSuperAdministrator() && $target?->isSuperAdministrator()) {
            return [UserRole::SuperAdministrator];
        }

        return collect(UserRole::cases())
            ->reject(fn (UserRole $role) => $role->isSupplier())
            ->filter(fn (UserRole $role) => $actor->isSuperAdministrator()
                ? ! $role->isSuperAdministrator()
                : ! $role->isAdministrator() && ! $role->grants(Permission::ViewAuditTrail))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function assignableRoleValues(User $actor, ?User $target = null): array
    {
        return array_map(
            fn (UserRole $role) => $role->value,
            $this->assignableRoles($actor, $target),
        );
    }

    public function canManage(User $actor, User $target): bool
    {
        if ($target->isProtected()) {
            return $actor->isSuperAdministrator() && $actor->is($target);
        }

        if ($actor->isSuperAdministrator()) {
            return true;
        }

        return ! $target->isAdministrator();
    }

    public function canUnlock(User $actor, User $target): bool
    {
        return $this->mayUnlock($actor, $target)
            && $target->isTemporarilyLocked();
    }

    private function mayUnlock(User $actor, User $target): bool
    {
        return $actor->isSuperAdministrator()
            && ! $actor->is($target)
            && ! $target->isSuperAdministrator()
            && $this->canManage($actor, $target);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor, bool $sendNotifications = true): User
    {
        $user = DB::transaction(function () use ($attributes, $actor): User {
            $role = UserRole::from($attributes['role']);
            if (($attributes['supplier_id'] ?? null) !== null && $role->isSupplier()) {
                if (! $actor->can(Permission::ApproveSuppliers->value)) {
                    throw new AuthorizationException('Only supplier approvers may invite supplier users.');
                }
            } else {
                $this->assertCanAssignRole($actor, $role);
            }

            $user = new User([
                ...$this->nameAttributes($attributes),
                'supplier_id' => $attributes['supplier_id'] ?? null,
                'email' => $attributes['email'],
                'password' => null,
                'role' => $role,
                'status' => UserStatus::PendingActivation,
                'employee_id' => $this->nextAccountIdentifier($role),
                'department' => $attributes['department'],
                'phone' => $attributes['phone'] ?? null,
            ]);

            $user->save();

            return $user;
        });

        if ($sendNotifications) {
            $user->notify(new AccountCreated);
            $this->sms->sendAccountCreated($user);
        }

        return $user;
    }

    /**
     * Apply an edit, refusing changes that would remove the acting user's own
     * access or leave the system without an administrator.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes, User $actor): User
    {
        $emailChanged = $attributes['email'] !== $user->email;
        $phoneChanged = ($attributes['phone'] ?? null) !== $user->phone;

        $user = DB::transaction(function () use ($user, $attributes, $actor, $emailChanged, $phoneChanged): User {
            $this->assertCanManage($actor, $user);

            $newRole = UserRole::from($attributes['role']);
            $newStatus = UserStatus::from($attributes['status']);
            $this->assertCanAssignRole($actor, $newRole, $user);

            if ($user->isProtected() && $attributes['email'] !== $user->email) {
                throw ValidationException::withMessages([
                    'email' => ['The protected Super Administrator email cannot be changed.'],
                ]);
            }

            if ($user->isArchived() && $newStatus !== UserStatus::Archived) {
                throw ValidationException::withMessages([
                    'status' => ['Archived user accounts must be restored through the Archive workspace.'],
                ]);
            }

            if ($user->isCancelled() && $newStatus !== UserStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'status' => ['Cancelled activations must be restarted through the Re-invite action.'],
                ]);
            }

            $losesAdmin = $user->isAdministrator()
                && (! $newRole->isAdministrator() || ! $newStatus->isActive());

            if ($losesAdmin) {
                $this->assertNotSelf($user, $actor, 'You cannot remove your own administrator access.');
                $this->assertOtherAdministratorRemains($user);
            }

            $user->fill([
                ...$this->nameAttributes($attributes),
                'email' => $attributes['email'],
                'role' => $newRole,
                'status' => $newStatus,
                'department' => $attributes['department'],
                'phone' => $attributes['phone'] ?? null,
            ]);

            if ($emailChanged) {
                $user->email_verified_at = null;
            }

            // Blank means "leave it alone" — the edit form does not echo the
            // existing password back, so an empty field is not a request to
            // clear it.
            if (! empty($attributes['password']) && ! $user->requiresActivation()) {
                return $this->passwords->usePassword(
                    $user,
                    $attributes['password'],
                    function (string $passwordHash) use ($user): User {
                        $user->password = $passwordHash;
                        $user->save();

                        return $user;
                    },
                );
            }

            $user->save();

            if (($emailChanged || $phoneChanged) && $user->isPendingActivation()) {
                $user->accountActivationChallenge()->delete();
            }

            return $user;
        });

        if ($emailChanged) {
            if ($user->isPendingActivation()) {
                $user->notify(new AccountCreated);
            } elseif (! $user->isCancelled()) {
                $user->sendEmailVerificationNotification();
            }
        }

        return $user;
    }

    /**
     * Flip an account between active and inactive, or restart activation when
     * a cancelled invitation left the account without a password.
     */
    public function toggleStatus(User $user, User $actor): User
    {
        return DB::transaction(function () use ($user, $actor): User {
            $this->assertCanManage($actor, $user);

            if ($user->isArchived()) {
                throw ValidationException::withMessages([
                    'status' => ['Archived user accounts cannot be activated via status toggle. Use the Restore workflow in the Archive workspace.'],
                ]);
            }

            if ($user->isPendingActivation()) {
                throw ValidationException::withMessages([
                    'status' => ['Pending accounts become active only after the user verifies an OTP and creates a password.'],
                ]);
            }

            if ($user->isActive()) {
                $this->assertNotSelf($user, $actor, 'You cannot deactivate your own account.');

                if ($user->isAdministrator()) {
                    $this->assertOtherAdministratorRemains($user);
                }

                $user->status = UserStatus::Inactive;
            } else {
                $user->status = $user->requiresActivation()
                    ? UserStatus::PendingActivation
                    : UserStatus::Active;

                if ($user->status === UserStatus::PendingActivation) {
                    $user->activation_cancellation_reason = null;
                    $user->activation_cancellation_details = null;
                    $user->activation_cancelled_at = null;
                    $user->activation_cancelled_by = null;
                    $user->activation_cancellation_notice_sent_at = null;
                }
            }

            $user->save();

            return $user;
        });
    }

    public function cancelInvitation(
        User $user,
        User $actor,
        ActivationCancellationReason $reason,
        ?string $details,
    ): User {
        return DB::transaction(function () use ($user, $actor, $reason, $details): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            $this->assertCanManage($actor, $lockedUser);

            if (! $lockedUser->isPendingActivation()) {
                throw ValidationException::withMessages([
                    'status' => ['Only pending invitations can be cancelled.'],
                ]);
            }

            $lockedUser->accountActivationChallenge()->delete();
            $lockedUser->forceFill([
                'status' => UserStatus::Cancelled,
                'activation_cancellation_reason' => $reason,
                'activation_cancellation_details' => $details,
                'activation_cancelled_at' => now(),
                'activation_cancelled_by' => $actor->getKey(),
                'activation_cancellation_notice_sent_at' => null,
            ])->saveQuietly();

            $lockedUser->supplierInvitation()
                ->where('status', \App\Models\SupplierInvitation::STATUS_PENDING)
                ->update([
                    'status' => \App\Models\SupplierInvitation::STATUS_REVOKED,
                    'revoked_at' => now(),
                    'revoked_by' => $actor->getKey(),
                    'updated_at' => now(),
                ]);

            $this->audit->log(
                AuditAction::AccountActivationCancelled,
                $actor,
                "Cancelled account activation for {$lockedUser->name}.",
                $lockedUser,
                $lockedUser->name,
                oldValues: ['status' => UserStatus::PendingActivation->value],
                newValues: [
                    'status' => UserStatus::Cancelled->value,
                    'reason' => $reason->label(),
                    'additional_details' => $details,
                ],
                businessReason: $reason->label(),
            );

            return $lockedUser;
        });
    }

    public function sendCancellationNotice(User $user, User $actor, bool $resend = false): bool
    {
        if (! $user->isCancelled() || $user->activation_cancellation_reason === null) {
            throw ValidationException::withMessages([
                'status' => ['Only cancelled activation requests have a cancellation notice.'],
            ]);
        }

        try {
            $creatorEmail = AuditLog::query()
                ->with('actor:id,email')
                ->where('action', AuditAction::CreatedUser->value)
                ->where('target_type', $user->getMorphClass())
                ->where('target_id', (string) $user->getKey())
                ->oldest('id')
                ->first()
                ?->actor
                ?->email;

            $user->notify(new AccountActivationCancelled(
                $user->activation_cancellation_reason,
                $user->activation_cancellation_details,
                $creatorEmail,
            ));
        } catch (Throwable $exception) {
            Log::warning('Account activation cancellation notice could not be sent.', [
                'user_id' => $user->getKey(),
                'exception_class' => $exception::class,
            ]);

            if ($resend) {
                $this->audit->log(
                    AuditAction::AccountActivationCancellationNoticeResent,
                    $actor,
                    "Could not resend the account activation cancellation notice for {$user->name}.",
                    $user,
                    $user->name,
                    outcome: 'failure',
                );
            }

            return false;
        }

        $user->forceFill(['activation_cancellation_notice_sent_at' => now()])->saveQuietly();

        if ($resend) {
            $this->audit->log(
                AuditAction::AccountActivationCancellationNoticeResent,
                $actor,
                "Resent the account activation cancellation notice for {$user->name}.",
                $user,
                $user->name,
            );
        }

        return true;
    }

    public function unlock(User $user, User $actor): User
    {
        return DB::transaction(function () use ($user, $actor): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! $this->mayUnlock($actor, $lockedUser)) {
                throw new AuthorizationException('Only a Super Administrator may unlock accounts.');
            }

            if (! $lockedUser->isTemporarilyLocked()) {
                throw ValidationException::withMessages([
                    'account' => ['This account is not currently temporarily locked.'],
                ]);
            }

            $oldValues = [
                'failed_login_attempts' => (int) $lockedUser->failed_login_attempts,
                'login_retry_at' => $lockedUser->login_retry_at?->toIso8601String(),
                'login_locked_until' => $lockedUser->login_locked_until?->toIso8601String(),
                'login_lockout_count' => (int) $lockedUser->login_lockout_count,
                'lock_reason' => LoginLockoutService::REPEATED_FAILURES_REASON,
            ];

            $lockedUser->forceFill([
                'failed_login_attempts' => 0,
                'last_failed_login_at' => null,
                'login_retry_at' => null,
                'login_locked_until' => null,
            ])->saveQuietly();

            $this->audit->log(
                AuditAction::UnlockedUser,
                $actor,
                "Manually unlocked the user account for {$lockedUser->name}.",
                $lockedUser,
                $lockedUser->name,
                $oldValues,
                [
                    'failed_login_attempts' => 0,
                    'login_locked_until' => null,
                    'login_lockout_count' => (int) $lockedUser->login_lockout_count,
                ],
            );

            return $lockedUser;
        });
    }

    /**
     * Accounts are never deleted — see UserStatus for why. This exists so the
     * controller has one honest place to send a delete attempt.
     */
    public function deactivate(User $user, User $actor): User
    {
        $this->assertCanManage($actor, $user);
        $this->assertNotSelf($user, $actor, 'You cannot deactivate your own account.');

        if ($user->isAdministrator()) {
            $this->assertOtherAdministratorRemains($user);
        }

        $user->status = UserStatus::Inactive;
        $user->save();

        return $user;
    }

    private function assertNotSelf(User $user, User $actor, string $message): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['role' => [$message]]);
        }
    }

    private function assertCanManage(User $actor, User $target): void
    {
        if (! $this->canManage($actor, $target)) {
            throw ValidationException::withMessages([
                'role' => ['Only a Super Administrator may manage administrative accounts.'],
            ]);
        }
    }

    private function assertCanAssignRole(User $actor, UserRole $role, ?User $target = null): void
    {
        if (in_array($role, $this->assignableRoles($actor, $target), true)) {
            return;
        }

        $message = match (true) {
            $role->isSuperAdministrator() => 'The Super Administrator role is reserved for the protected system account.',
            $role->grants(Permission::ViewAuditTrail) => 'Only a Super Administrator may assign an Audit Trail role.',
            default => 'Only a Super Administrator may assign the Administrator role.',
        };

        throw ValidationException::withMessages(['role' => [$message]]);
    }

    /**
     * Structured fields are used by user management. Accepting `name` as a
     * fallback keeps non-HTTP service callers backward compatible.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function nameAttributes(array $attributes): array
    {
        if (array_key_exists('first_name', $attributes) || array_key_exists('surname', $attributes)) {
            return [
                'first_name' => $attributes['first_name'] ?? null,
                'middle_name' => $attributes['middle_name'] ?? null,
                'surname' => $attributes['surname'] ?? null,
            ];
        }

        return ['name' => $attributes['name']];
    }

    /**
     * Reserve the next account identifier while holding a database row lock.
     * Hospital accounts use EMP and supplier accounts use SUP; the legacy
     * users.employee_id column remains the unique storage field.
     */
    private function nextAccountIdentifier(UserRole $role): string
    {
        $sequence = DB::table('employee_id_sequences')
            ->where('id', 1)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            throw new \RuntimeException('The account ID sequence has not been initialized.');
        }

        $number = (int) $sequence->next_value;
        $prefix = $role->isSupplier() ? 'SUP' : 'EMP';

        do {
            $accountIdentifier = $prefix.'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $number++;
        } while (User::query()->where('employee_id', $accountIdentifier)->exists());

        DB::table('employee_id_sequences')->where('id', 1)->update([
            'next_value' => $number,
        ]);

        return $accountIdentifier;
    }

    /**
     * Refuse the change unless some other active administrator would survive it.
     */
    private function assertOtherAdministratorRemains(User $user): void
    {
        $remaining = User::query()
            ->administrators()
            ->active()
            ->whereKeyNot($user->getKey())
            ->count();

        if ($remaining === 0) {
            throw ValidationException::withMessages([
                'role' => ['This is the only active administrator. Promote someone else first.'],
            ]);
        }
    }
}
