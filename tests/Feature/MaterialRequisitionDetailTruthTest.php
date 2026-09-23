<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\MaterialRequisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialRequisitionDetailTruthTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_does_not_invent_reason_deadline_or_transaction_price(): void
    {
        $requester = User::factory()->inventoryManager()->create();
        $item = InventoryItem::create([
            'name' => 'Sterile Test Kit',
            'sku' => 'KIT-STERILE',
            'unit' => 'kit',
            'unit_cost' => 39.75,
            'status' => 'active',
        ]);
        $requisition = MaterialRequisition::create([
            'requisition_number' => 'MR-DETAIL-TRUTH',
            'requesting_user_id' => $requester->id,
            'department' => 'Inventory',
            'status' => 'rejected',
            'urgency' => 'routine',
        ]);
        $requisition->lines()->create([
            'item_id' => $item->id,
            'requested_quantity' => 2,
            'line_status' => 'pending',
        ]);

        $this->actingAs($requester)
            ->get(route('inventory.requisitions.show', $requisition))
            ->assertOk()
            ->assertSeeText('No rejection reason recorded')
            ->assertSeeText('Required by: Not specified')
            ->assertSeeText('Current Catalog Cost')
            ->assertSeeText('₱39.75')
            ->assertDontSee('Administrative / budgetary rejection')
            ->assertDontSee('Required by: Immediate')
            ->assertDontSee('Unit Price')
            ->assertDontSee('Pending Pick');
    }
}
