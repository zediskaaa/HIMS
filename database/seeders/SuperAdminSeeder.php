<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public const EMAIL = 'zediskaaa@gmail.com';

    /**
     * Initial setup secret only. Authentication always uses Laravel's hashed
     * password verifier; this value is never written to logs or responses.
     */
    private const INITIAL_PASSWORD = 'superadminzediskaaa123';

    public function run(): void
    {
        User::withoutEvents(function (): void {
            $user = User::query()->firstOrNew(['email' => self::EMAIL]);
            $shouldSetInitialPassword = ! $user->exists || ! $user->isProtected();
            $employeeId = $user->employee_id ?: $this->availableEmployeeId($user);

            // forceFill is deliberate: is_protected and email_verified_at are
            // system-owned fields and must never be mass assignable from HTTP.
            $attributes = [
                'name' => 'HIMS Super Administrator',
                'role' => UserRole::SuperAdministrator,
                'status' => UserStatus::Active,
                'is_protected' => true,
                'employee_id' => $employeeId,
                'department' => 'Administration',
                'email_verified_at' => now(),
            ];

            // Set the documented credential when the protected account is
            // provisioned (or an existing same-email account is promoted), but
            // never undo a password the Super Administrator changes later.
            if ($shouldSetInitialPassword) {
                $attributes['password'] = Hash::make(self::INITIAL_PASSWORD);
            }

            $user->forceFill($attributes)->save();
        });
    }

    private function availableEmployeeId(User $user): string
    {
        $number = 1;

        do {
            $employeeId = 'SA-'.str_pad((string) $number++, 4, '0', STR_PAD_LEFT);
        } while (User::query()
            ->where('employee_id', $employeeId)
            ->when($user->exists, fn ($query) => $query->whereKeyNot($user->getKey()))
            ->exists());

        return $employeeId;
    }
}
