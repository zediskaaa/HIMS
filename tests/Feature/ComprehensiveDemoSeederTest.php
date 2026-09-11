<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DpriReferencePrice;
use App\Models\InspectionAcceptanceReport;
use App\Models\InventoryItem;
use App\Models\IoTTelemetryLog;
use App\Models\LogisticsDocument;
use App\Models\ProcurementCategory;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\SourcingRfq;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\ComprehensiveDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComprehensiveDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_provides_accounts_and_guided_data_for_every_major_module(): void
    {
        $this->seed(ComprehensiveDemoSeeder::class);

        foreach (UserRole::cases() as $role) {
            $this->assertTrue(User::active()->role($role)->exists(), "Missing active {$role->label()} demo account.");
        }

        $this->assertTrue(InventoryItem::where('sku', 'PPE-MASK-N95')->exists());
        $this->assertTrue(Supplier::procurementEligible()->exists());
        $this->assertTrue(ProcurementCategory::query()->exists());
        $this->assertTrue(SourcingRfq::query()->exists());
        $this->assertTrue(PurchaseOrder::query()->exists());
        $this->assertTrue(IoTTelemetryLog::query()->exists());
        $this->assertTrue(Shipment::query()->exists());
        $this->assertTrue(InspectionAcceptanceReport::query()->exists());
        $this->assertTrue(LogisticsDocument::query()->exists());
        $this->assertTrue(DpriReferencePrice::query()->exists());
    }

    public function test_it_can_be_repeated_without_duplicating_demo_records(): void
    {
        $this->seed(ComprehensiveDemoSeeder::class);
        $counts = $this->representativeCounts();

        $this->seed(ComprehensiveDemoSeeder::class);

        $this->assertSame($counts, $this->representativeCounts());
    }

    /** @return array<string, int> */
    private function representativeCounts(): array
    {
        return [
            'users' => User::count(),
            'items' => InventoryItem::count(),
            'suppliers' => Supplier::count(),
            'procurement_categories' => ProcurementCategory::count(),
            'rfqs' => SourcingRfq::count(),
            'purchase_orders' => PurchaseOrder::count(),
            'telemetry_logs' => IoTTelemetryLog::count(),
            'shipments' => Shipment::count(),
            'inspection_reports' => InspectionAcceptanceReport::count(),
            'logistics_documents' => LogisticsDocument::count(),
            'dpri_prices' => DpriReferencePrice::count(),
        ];
    }
}
