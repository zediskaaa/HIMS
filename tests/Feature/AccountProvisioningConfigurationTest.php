<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\OwnerAdminSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountProvisioningConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_seeders_do_not_create_default_accounts_without_configuration(): void
    {
        config()->set('account_provisioning.super_admin', []);
        config()->set('account_provisioning.owner_admin', []);
        config()->set('account_provisioning.demo_accounts', []);

        $this->seed(SuperAdminSeeder::class);
        $this->seed(OwnerAdminSeeder::class);
        $this->seed(DemoUserSeeder::class);

        $this->assertSame(0, User::query()->count());
    }
}
