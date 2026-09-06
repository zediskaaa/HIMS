<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
    public function __construct(private readonly PasswordHistoryService $passwords) {}

    /**
     * Roles the actor may assign. Super Admin may manage Administrator
     * accounts, while the protected Super Administrator role is never exposed
     * as a creatable role.
     *
     * @return array<int, UserRole>
     */
    public function assignableRoles(User $actor, ?User $target = null): array
    {
        $roles = collect(UserRole::cases())
            ->filter(fn (UserRole $role) => $actor->isSuperAdministrator()
                ? ! $role->isSuperAdministrator()
                : ! $role->isAdministrator())
            ->values()
            ->all();

        // The protected account may preserve its existing role while editing
        // its own non-security profile fields. It still cannot assign that role
        // to any other account.
        if ($target?->isProtected() && $target->is($actor)) {
            array_unshift($roles, UserRole::SuperAdministrator);
        }

        return $roles;
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor): User
    {
        return $this->passwords->usePassword($attributes['password'], function (string $passwordHash) use ($attributes, $actor): User {
            $role = UserRole::from($attributes['role']);
            $this->assertCanAssignRole($actor, $role);

            $user = new User([
                ...$this->nameAttributes($attributes),
                'email' => $attributes['email'],
                'password' => $passwordHash,
                'role' => $role,
                'status' => $attributes['status'] ?? UserStatus::Active->value,
                'employee_id' => $this->nextEmployeeId(),
                'department' => $attributes['department'],
                'phone' => $attributes['phone'] ?? null,
            ]);

            // An administrator created this account in person, so there is nobody
            // to send a confirmation link to. Set outside the fillable list on
            // purpose: email_verified_at must never be mass-assignable from a
            // request, so passing it to User::create() would be dropped silently.
            $user->email_verified_at = now();

            $user->save();

            return $user;
        });
    }

    /**
     * Apply an edit, refusing changes that would remove the acting user's own
     * access or leave the system without an administrator.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes, User $actor): User
    {
        return DB::transaction(function () use ($user, $attributes, $actor): User {
            $this->assertCanManage($actor, $user);

            $newRole = UserRole::from($attributes['role']);
            $newStatus = UserStatus::from($attributes['status']);
            $this->assertCanAssignRole($actor, $newRole, $user);

            if ($user->isProtected() && $attributes['email'] !== $user->email) {
                throw ValidationException::withMessages([
                    'email' => ['The protected Super Administrator email cannot be changed.'],
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

            // Blank means "leave it alone" — the edit form does not echo the
            // existing password back, so an empty field is not a request to
            // clear it.
            if (! empty($attributes['password'])) {
                return $this->passwords->usePassword(
                    $attributes['password'],
                    function (string $passwordHash) use ($user): User {
                        $user->password = $passwordHash;
                        $user->save();

                        return $user;
                    },
                );
            }

            $user->save();

            return $user;
        });
    }

    /**
     * Flip an account between active and inactive.
     */
    public function toggleStatus(User $user, User $actor): User
    {
        return DB::transaction(function () use ($user, $actor): User {
            $this->assertCanManage($actor, $user);

            if ($user->isActive()) {
                $this->assertNotSelf($user, $actor, 'You cannot deactivate your own account.');

                if ($user->isAdministrator()) {
                    $this->assertOtherAdministratorRemains($user);
                }

                $user->status = UserStatus::Inactive;
            } else {
                $user->status = UserStatus::Active;
            }

            $user->save();

            return $user;
        });
    }

    public function resetPassword(User $user, string $password): User
    {
        return $this->passwords->usePassword(
            $password,
            function (string $passwordHash) use ($user): User {
                $user->password = $passwordHash;
                $user->save();

                return $user;
            },
        );
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

        $message = $role->isSuperAdministrator()
            ? 'The Super Administrator role is reserved for the protected system account.'
            : 'Only a Super Administrator may assign the Administrator role.';

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
     * Reserve the next ID while holding a database row lock. All creators
     * serialize through this single row, and users.employee_id has a unique
     * index as the final database-level duplicate guard.
     */
    private function nextEmployeeId(): string
    {
        $sequence = DB::table('employee_id_sequences')
            ->where('id', 1)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            throw new \RuntimeException('The employee ID sequence has not been initialized.');
        }

        $number = (int) $sequence->next_value;

        do {
            $employeeId = 'EMP-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $number++;
        } while (User::query()->where('employee_id', $employeeId)->exists());

        DB::table('employee_id_sequences')->where('id', 1)->update([
            'next_value' => $number,
        ]);

        return $employeeId;
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
