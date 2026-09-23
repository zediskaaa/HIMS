<?php

namespace Tests\Concerns;

use App\Enums\UserRole;
use Illuminate\Support\Str;

trait ConfiguresAccountProvisioning
{
    protected function configureAccountProvisioning(): void
    {
        config()->set('account_provisioning.super_admin', [
            'name' => 'Seed Test Super Administrator',
            'email' => 'seed.super-admin@example.test',
            'phone' => null,
            'department' => 'Testing',
            'password' => 'SyntheticSeedSuperAdmin123!',
        ]);

        config()->set('account_provisioning.owner_admin', []);
        config()->set('account_provisioning.demo_accounts', collect(UserRole::cases())
            ->reject(fn (UserRole $role) => $role === UserRole::SuperAdministrator)
            ->values()
            ->map(fn (UserRole $role, int $index) => [
                'role' => $role->value,
                'name' => $role->label().' Test User',
                'email' => str_replace('_', '.', $role->value).'@example.test',
                'employee_id' => 'TEST-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'department' => 'Testing',
                'phone' => null,
                'password' => 'Synthetic'.Str::studly($role->value).'123!',
            ])
            ->all());
    }
}
