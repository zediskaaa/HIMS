<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierManagementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierManagementDemoSeeder extends Seeder
{
    public const IDENTITY_KEY = 'tax:245731680000';

    public function run(): void
    {
        if (Supplier::procurementEligible()->exists()) {
            $this->command?->info('A procurement-eligible supplier already exists; no supplier demo data was added.');

            return;
        }

        if (Supplier::where('identity_key', self::IDENTITY_KEY)->exists()) {
            $this->command?->warn('The demonstration supplier already exists but is not procurement eligible; its current business state was preserved.');

            return;
        }

        $creator = $this->supplierCreator();
        $approver = $this->supplierApprover($creator);

        if (! $creator || ! $approver) {
            $this->command?->warn('Supplier demo data was not added because two active, appropriately separated staff accounts are required.');

            return;
        }

        DB::transaction(function () use ($creator, $approver): void {
            $service = app(SupplierManagementService::class);
            $supplier = $service->create([
                'name' => 'Bayanihan Community Hospital Supply Cooperative',
                'trade_name' => 'Bayanihan Hospital Supply',
                'business_structure' => 'cooperative',
                'provides_regulated_health_products' => false,
                'email' => 'procurement@bayanihan-hospital-supply.test',
                'phone' => '+63 2 8555 0146',
                'address' => 'Commonwealth Avenue, Quezon City, Metro Manila',
                'billing_address' => 'Commonwealth Avenue, Quezon City, Metro Manila',
                'delivery_address' => 'Hospital Central Supply Receiving Area',
                'tax_number' => '245-731-680-000',
                'standard_lead_time_days' => 5,
                'payment_terms' => 'Net 30 days after accepted delivery and complete billing documents.',
                'notes' => 'Demonstration supplier for non-regulated hospital and warehouse supplies; seeded only when no procurement-eligible supplier exists.',
            ], $creator);

            $service->submitForReview($supplier, $creator);
            $service->approve(
                $supplier,
                $approver,
                today()->addYear()->toDateString(),
                'Approved for demonstration procurement of non-regulated general hospital supplies.',
            );
        });

        $this->command?->info('Added one procurement-eligible demonstration supplier through the standard accreditation workflow.');
    }

    private function supplierCreator(): ?User
    {
        return User::active()->role(UserRole::InventoryManager)->oldest('id')->first()
            ?? User::active()->role(UserRole::Administrator)->oldest('id')->first()
            ?? User::active()->role(UserRole::SuperAdministrator)->oldest('id')->first();
    }

    private function supplierApprover(?User $creator): ?User
    {
        if (! $creator) {
            return null;
        }

        return User::active()
            ->administrators()
            ->whereKeyNot($creator->getKey())
            ->oldest('id')
            ->first();
    }
}
