<?php

namespace Tests\Feature;

use App\Enums\ApprovalChainType;
use App\Enums\AuditAction;
use App\Enums\NotificationDestination;
use App\Enums\NotificationPriority;
use App\Enums\UserRole;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\HimsNotificationService;
use App\Services\Import\DataImportExecutor;
use App\Services\Import\ImportStagingService;
use App\Services\Procurement\ApprovalRoutingEngine;
use App\Services\Recovery\SafeExecutionService;
use App\Services\StockAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_bell_panel_has_a_clear_empty_state(): void
    {
        $user = User::factory()->viewer()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('No notifications')
            ->assertSee('You are all caught up')
            ->assertDontSee('Mark all as read');
    }

    public function test_new_stock_alerts_notify_only_active_users_who_can_act_without_duplicates(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $warehouse = User::factory()->warehouseStaff()->create();
        $superAdmin = User::factory()->superAdministrator()->create();
        $admin = User::factory()->administrator()->create();
        $viewer = User::factory()->viewer()->create();
        $inactiveWarehouse = User::factory()->warehouseStaff()->inactive()->create();
        $item = InventoryItem::create([
            'name' => 'Emergency Epinephrine',
            'sku' => 'MED-EPI-001',
            'unit' => 'ampoule',
            'quantity_on_hand' => 0,
            'reorder_level' => 10,
            'unit_cost' => 100,
            'status' => 'active',
        ]);

        $alerts = app(StockAlertService::class);

        $this->assertSame(1, $alerts->evaluateStockLevel($item));
        $this->assertSame(0, $alerts->evaluateStockLevel($item));

        foreach ([$manager, $warehouse, $superAdmin] as $recipient) {
            $notification = $recipient->fresh()->notifications()->sole();
            $this->assertSame('Out of Stock', $notification->data['title']);
            $this->assertSame(NotificationPriority::Critical->value, $notification->data['priority']);
            $this->assertSame(NotificationDestination::InventoryAlerts->value, $notification->data['destination']);
        }

        foreach ([$admin, $viewer, $inactiveWarehouse] as $nonRecipient) {
            $this->assertSame(0, $nonRecipient->fresh()->notifications()->count());
        }
    }

    public function test_bell_panel_read_actions_are_scoped_to_the_authenticated_user(): void
    {
        $user = User::factory()->inventoryManager()->create();
        $other = User::factory()->inventoryManager()->create();
        $service = app(HimsNotificationService::class);

        $service->sendToUser(
            $user,
            'test:one',
            'Action needed',
            'A stock alert needs review.',
            NotificationPriority::Warning,
            NotificationDestination::InventoryAlerts,
        );
        $service->sendToUser(
            $user,
            'test:two',
            'Second action',
            'Another stock alert needs review.',
            NotificationPriority::Info,
            NotificationDestination::InventoryAlerts,
        );
        $service->sendToUser(
            $other,
            'test:other',
            'Private notification',
            'This belongs to another user.',
            NotificationPriority::Info,
            NotificationDestination::InventoryAlerts,
        );

        $own = $user->notifications()->firstOrFail();
        $others = $other->notifications()->firstOrFail();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Action needed')
            ->assertSee('2 unread');

        $this->actingAs($user)->patch(route('notifications.read', $others->id))->assertNotFound();
        $this->assertNull($others->fresh()->read_at);

        $this->actingAs($user)->patch(route('notifications.read', $own->id))->assertRedirect();
        $this->assertNotNull($own->fresh()->read_at);
        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());

        $this->actingAs($user)->patch(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
        $this->assertNull($others->fresh()->read_at);
    }

    public function test_notification_link_rechecks_current_authorization_before_redirecting(): void
    {
        $user = User::factory()->inventoryManager()->create();
        app(HimsNotificationService::class)->sendToUser(
            $user,
            'test:permission-change',
            'Inventory alert',
            'Review the active stock alert.',
            NotificationPriority::Warning,
            NotificationDestination::InventoryAlerts,
        );
        $notification = $user->notifications()->sole();

        $user->forceFill(['role' => UserRole::Viewer])->saveQuietly();

        $this->actingAs($user)
            ->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('info');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_notification_link_opens_an_authorized_destination_and_marks_it_read(): void
    {
        $user = User::factory()->inventoryManager()->create();
        app(HimsNotificationService::class)->sendToUser(
            $user,
            'test:authorized-link',
            'Inventory alert',
            'Review the active stock alert.',
            NotificationPriority::Warning,
            NotificationDestination::InventoryAlerts,
        );
        $notification = $user->notifications()->sole();

        $this->actingAs($user)
            ->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('inventory.alerts'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_procurement_chain_notifies_only_the_current_approval_role(): void
    {
        $initiator = User::factory()->pharmacyStaff()->create();
        $manager = User::factory()->inventoryManager()->create();
        $admin = User::factory()->administrator()->create();
        $superAdmin = User::factory()->superAdministrator()->create();
        $viewer = User::factory()->viewer()->create();
        $engine = app(ApprovalRoutingEngine::class);

        $chain = $engine->instantiateChain(
            ApprovalChainType::PurchaseRequest,
            999999,
            100000,
            $initiator,
        );

        $this->assertSame(1, $manager->fresh()->notifications()->count());
        $this->assertSame(0, $admin->fresh()->notifications()->count());
        $this->assertSame(1, $superAdmin->fresh()->notifications()->count());
        $this->assertSame(0, $viewer->fresh()->notifications()->count());
        $this->assertSame(0, $initiator->fresh()->notifications()->count());

        $engine->approveStep($chain, $manager);

        $this->assertSame(1, $admin->fresh()->notifications()->count());
        $this->assertSame(2, $superAdmin->fresh()->notifications()->count());
        $this->assertSame(1, $manager->fresh()->notifications()->count());
    }

    public function test_recovery_notifications_are_critical_and_limited_to_super_administrators(): void
    {
        $superAdmin = User::factory()->superAdministrator()->create();
        $admin = User::factory()->administrator()->create();

        $record = app(SafeExecutionService::class)->recordFailure(
            new RuntimeException('Sensitive internal failure detail'),
            'procurement',
            'commit_purchase_order',
        );

        $notification = $superAdmin->fresh()->notifications()->sole();
        $this->assertSame(NotificationPriority::Critical->value, $notification->data['priority']);
        $this->assertSame(NotificationDestination::RecoveryRecord->value, $notification->data['destination']);
        $this->assertSame(['record' => $record->id], $notification->data['route_parameters']);
        $this->assertStringNotContainsString('Sensitive internal failure detail', $notification->data['message']);
        $this->assertSame(0, $admin->fresh()->notifications()->count());
    }

    public function test_password_and_mfa_changes_create_safe_persistent_security_notifications(): void
    {
        $user = User::factory()->inventoryManager()->create();

        $this->actingAs($user);
        $user->forceFill(['password' => 'a-new-secure-password'])->save();
        $user->forceFill([
            'authenticator_secret' => 'JBSWY3DPEHPK3PXP',
            'authenticator_enabled_at' => now(),
        ])->save();

        $notifications = $user->fresh()->notifications()->get();
        $this->assertCount(2, $notifications);
        $this->assertTrue($notifications->contains(fn ($notification) => $notification->data['title'] === 'Password changed'));
        $this->assertTrue($notifications->contains(fn ($notification) => str_contains($notification->data['title'], 'MFA enabled')));
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $notifications->toJson());
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::ChangedMfa->value]);
    }

    public function test_same_event_key_is_stored_only_once_per_recipient(): void
    {
        $user = User::factory()->inventoryManager()->create();
        $service = app(HimsNotificationService::class);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $service->sendToUser(
                $user,
                'stable-business-event-key',
                'One event',
                'This event must not be duplicated.',
                NotificationPriority::Info,
                NotificationDestination::InventoryAlerts,
            );
        }

        $this->assertSame(1, $user->fresh()->notifications()->count());
    }

    public function test_failed_import_notifies_only_the_actor_without_exposing_the_exception(): void
    {
        $user = User::factory()->inventoryManager()->create();
        $token = app(ImportStagingService::class)->stage(
            'items',
            'create_only',
            [['name' => 'Test item']],
            $user->id,
        );
        $executor = Mockery::mock(DataImportExecutor::class);
        $executor->shouldReceive('execute')
            ->once()
            ->andThrow(new RuntimeException('database-host-secret.example.test'));
        $this->app->instance(DataImportExecutor::class, $executor);

        $this->actingAs($user)
            ->postJson(route('inventory.import.commit'), [
                'import_token' => $token,
                'target' => 'items',
            ])
            ->assertStatus(500)
            ->assertJsonPath(
                'message',
                'The import could not be completed. No records were committed. Review the file and try again.'
            )
            ->assertDontSee('database-host-secret.example.test');

        $notification = $user->fresh()->notifications()->sole();
        $this->assertSame('Data import failed', $notification->data['title']);
        $this->assertStringNotContainsString('database-host-secret.example.test', $notification->toJson());
    }
}
