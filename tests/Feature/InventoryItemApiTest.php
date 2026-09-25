<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryItemApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_and_create_inventory_item()
    {
        $user = User::factory()->role(UserRole::InventoryManager)->create();
        Sanctum::actingAs($user, ['*']);

        $payload = [
            'sku' => 'TEST-SKU-001',
            'name' => 'Test Item',
            'quantity_on_hand' => 10,
            'unit_cost' => 5.5,
        ];

        $createResp = $this->postJson('/api/v1/inventory-items', $payload);
        $createResp->assertStatus(201)->assertJsonFragment(['sku' => 'TEST-SKU-001']);

        $listResp = $this->getJson('/api/v1/inventory-items');
        $listResp->assertStatus(200)->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_barcode_and_tracking_changes_are_captured_in_item_master_audit_snapshots(): void
    {
        $user = User::factory()->role(UserRole::InventoryManager)->create();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/inventory-items', [
            'sku' => 'TRACE-ITEM-001',
            'barcode_value' => 'HIMS-TRACE-001',
            'gtin' => '04801234567897',
            'name' => 'Traceable Test Item',
            'unit' => 'piece',
            'is_batch_tracked' => true,
            'is_expiry_tracked' => true,
        ])->assertCreated();

        $item = InventoryItem::where('sku', 'TRACE-ITEM-001')->firstOrFail();
        $created = AuditLog::where('action', AuditAction::CreatedInventoryItem->value)->latest('id')->firstOrFail();
        $this->assertSame('HIMS-TRACE-001', $created->new_values['barcode_value']);
        $this->assertSame('04801234567897', $created->new_values['gtin']);
        $this->assertTrue($created->new_values['is_batch_tracked']);

        $this->patchJson("/api/v1/inventory-items/{$item->id}", [
            'barcode_value' => 'HIMS-TRACE-002',
        ])->assertOk();

        $updated = AuditLog::where('action', AuditAction::UpdatedInventoryItem->value)->latest('id')->firstOrFail();
        $this->assertSame('HIMS-TRACE-001', $updated->old_values['barcode_value']);
        $this->assertSame('HIMS-TRACE-002', $updated->new_values['barcode_value']);
        $this->assertSame($user->id, $updated->user_id);
    }
}
