<?php

namespace Tests\Feature;

use App\Enums\DemandTrend;
use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\DemandForecastService;
use App\Support\AuthenticationContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The demo data is what the panel actually sees, so it is worth asserting
 * rather than eyeballing. The forecast derives entirely from consumption
 * history: with no seeded movements every row on the screen would read
 * "No recorded consumption in the analysis window", and the module would look
 * broken at the defence even though the code is correct.
 */
class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_it_seeds_demo_staff_and_the_protected_super_admin(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertGreaterThan(
                0,
                User::role($role)->count(),
                'No demo account seeded for the '.$role->label().' role.'
            );
        }

        // The admin account is the one that can reach user management and
        // create everyone else, so the demo is unusable without it.
        $admin = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertTrue($admin->isAdministrator());
        $this->assertTrue($admin->isActive());
        $this->assertSame(1, User::superAdministrators()->count());
        $this->assertTrue(User::superAdministrators()->firstOrFail()->isProtected());
    }

    public function test_the_seeded_admin_can_sign_in_with_the_documented_password(): void
    {
        $this->post('/admin/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated(AuthenticationContext::ADMIN_GUARD);
    }

    public function test_every_seeded_item_has_consumption_history(): void
    {
        $items = InventoryItem::all();

        $this->assertCount(3, $items);

        foreach ($items as $item) {
            $consumption = StockMovement::where('item_id', $item->id)
                ->whereIn('movement_type', MovementType::consumptionValues())
                ->count();

            $this->assertGreaterThan(0, $consumption, $item->name.' has no consumption history.');
        }
    }

    /**
     * History is written backdated rather than posted through the stock
     * service, because the seeded stock levels already represent today. If it
     * went through the service the same units would be deducted twice and the
     * balances would go negative.
     */
    public function test_seeding_did_not_double_deduct_the_stock_levels(): void
    {
        foreach (InventoryItem::all() as $item) {
            $this->assertGreaterThan(0, $item->quantity_on_hand, $item->name.' seeded at or below zero.');
        }

        $this->assertDatabaseMissing('item_stock_levels', ['quantity' => -1]);
        $this->assertSame(0, ItemStockLevel::where('quantity', '<', 0)->count());
    }

    public function test_the_history_lands_inside_the_default_analysis_window(): void
    {
        $cutoff = now()->subDays(DemandForecastService::DEFAULT_ANALYSIS_DAYS);

        $outside = StockMovement::whereIn('movement_type', MovementType::consumptionValues())
            ->where('moved_at', '<', $cutoff)
            ->count();

        $this->assertSame(0, $outside, 'Seeded consumption falls outside the 90-day window and will not show.');
    }

    public function test_the_forecast_screen_shows_real_numbers_for_the_demo_data(): void
    {
        $forecasts = app(DemandForecastService::class)->forecastAll();

        $this->assertCount(3, $forecasts);

        foreach ($forecasts as $row) {
            $this->assertGreaterThan(0, $row['historical_usage'], $row['item']->name.' forecast has no usage.');
            $this->assertGreaterThan(0.0, (float) $row['average_daily_usage']);
            $this->assertNotNull($row['days_of_cover']);
            $this->assertNotSame('No recorded consumption in the analysis window.', $row['trigger_reason']);
        }
    }

    /**
     * The three items are shaped to tell three different stories, so the trend
     * column is not a single value repeated down the screen.
     */
    public function test_the_demo_items_show_more_than_one_trend(): void
    {
        $trends = app(DemandForecastService::class)->forecastAll()
            ->pluck('trend')
            ->unique();

        $this->assertGreaterThan(1, $trends->count(), 'Every demo item reports the same trend.');
        $this->assertFalse($trends->contains(DemandTrend::Insufficient), 'A demo item has too little history to trend.');
    }

    public function test_the_seeded_transfer_and_adjustment_are_excluded_from_demand(): void
    {
        // Both exist in the movement history...
        $this->assertGreaterThan(0, StockMovement::ofType(MovementType::Transfer)->count());
        $this->assertGreaterThan(0, StockMovement::ofType(MovementType::Adjustment)->count());

        // ...but neither reaches the forecast for the item they belong to.
        $masks = InventoryItem::where('sku', 'PPE-MASK-N95')->firstOrFail();

        $countedUnits = (int) StockMovement::where('item_id', $masks->id)
            ->whereIn('movement_type', MovementType::consumptionValues())
            ->sum('quantity');

        $this->assertSame(
            $countedUnits,
            app(DemandForecastService::class)->forecast($masks)['historical_usage']
        );
    }

    /**
     * Issuance needs somewhere to issue to. Without department locations the
     * "Issued To" dropdown on the stock movement screen is empty and the
     * movement type cannot be demonstrated.
     */
    public function test_it_seeds_departments_to_issue_stock_to(): void
    {
        $this->assertGreaterThanOrEqual(
            3,
            StorageLocation::where('type', 'department')->count()
        );

        $this->assertGreaterThan(0, StockMovement::ofType(MovementType::Issuance)->count());
    }
}
