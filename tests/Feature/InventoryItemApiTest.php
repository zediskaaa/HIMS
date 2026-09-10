<?php

namespace Tests\Feature;

use App\Enums\UserRole;
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
}
