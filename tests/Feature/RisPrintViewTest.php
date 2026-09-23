<?php

namespace Tests\Feature;

use App\Models\CostCenter;
use App\Models\InventoryItem;
use App\Models\MaterialRequisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RisPrintViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_slip_leaves_unrecorded_issue_and_receipt_details_blank(): void
    {
        $requester = User::factory()->pharmacyStaff()->create(['name' => 'Requesting Pharmacist']);
        $viewer = User::factory()->inventoryManager()->create(['name' => 'Viewing Manager']);
        $item = InventoryItem::create(['name' => 'Sterile Dressing', 'sku' => 'RIS-DRESS-01', 'unit' => 'pack']);
        $requisition = MaterialRequisition::create([
            'requisition_number' => 'MR-RIS-PENDING-01',
            'requesting_user_id' => $requester->id,
            'department' => 'Emergency',
            'status' => 'pending_approval',
            'justification' => 'Replenish emergency treatment carts.',
        ]);
        $requisition->lines()->create([
            'item_id' => $item->id,
            'requested_quantity' => 8,
            'issued_quantity' => 0,
            'notes' => 'For emergency wound care.',
        ]);

        $response = $this->actingAs($viewer)->get(route('inventory.logistics.ris.show', $requisition));

        $response->assertOk()
            ->assertSeeText('Emergency')
            ->assertSeeText('RIS-DRESS-01')
            ->assertSeeText('pack')
            ->assertSeeText('Replenish emergency treatment carts.')
            ->assertSeeText('For emergency wound care.')
            ->assertSee('>8</td>', false)
            ->assertSee('>0</td>', false)
            ->assertDontSee('01 - Regular Agency Fund')
            ->assertDontSee('RCC-2001')
            ->assertDontSee('Issued in good condition')
            ->assertDontSee('[X]');

        $html = $response->getContent();
        $issuedSection = Str::between($html, 'Issued By:', 'Received By:');
        $receivedSection = Str::between($html, 'Received By:', 'HIMS System Reference');

        $this->assertStringNotContainsString($viewer->name, $issuedSection);
        $this->assertStringNotContainsString(now()->format('m/d/Y'), $issuedSection);
        $this->assertStringNotContainsString($requester->name, $receivedSection);
        $this->assertStringNotContainsString(now()->format('m/d/Y'), $receivedSection);
    }

    public function test_partially_issued_slip_shows_recorded_quantity_issuer_and_issue_date_only(): void
    {
        $requester = User::factory()->pharmacyStaff()->create(['name' => 'Ward Requester']);
        $issuer = User::factory()->warehouseStaff()->create(['name' => 'Store Issuer']);
        $viewer = User::factory()->inventoryManager()->create(['name' => 'Printing Manager']);
        $item = InventoryItem::create(['name' => 'Surgical Mask', 'sku' => 'RIS-MASK-01', 'unit' => 'box']);
        $requisition = MaterialRequisition::create([
            'requisition_number' => 'MR-RIS-PARTIAL-01',
            'requesting_user_id' => $requester->id,
            'department' => 'Surgery',
            'status' => 'picking',
            'issued_by_id' => $issuer->id,
            'issued_at' => '2026-09-10 09:30:00',
        ]);
        $requisition->lines()->create([
            'item_id' => $item->id,
            'requested_quantity' => 8,
            'issued_quantity' => 3,
            'line_status' => 'partially_issued',
        ]);

        $response = $this->actingAs($viewer)->get(route('inventory.logistics.ris.show', $requisition));

        $response->assertOk()->assertSee('>8</td>', false)->assertSee('>3</td>', false);
        $html = $response->getContent();
        $issuedSection = Str::between($html, 'Issued By:', 'Received By:');
        $receivedSection = Str::between($html, 'Received By:', 'HIMS System Reference');

        $this->assertStringContainsString($issuer->name, $issuedSection);
        $this->assertStringContainsString('09/10/2026', $issuedSection);
        $this->assertStringNotContainsString($viewer->name, $issuedSection);
        $this->assertStringNotContainsString($requester->name, $receivedSection);
    }

    public function test_acknowledged_slip_shows_recorded_recipient_and_acknowledgement_date(): void
    {
        $requester = User::factory()->pharmacyStaff()->create(['name' => 'Receiving Requester']);
        $issuer = User::factory()->warehouseStaff()->create(['name' => 'Store Custodian']);
        $viewer = User::factory()->inventoryManager()->create(['name' => 'Reviewing Manager']);
        $costCenter = CostCenter::create([
            'code' => 'CC-RIS-PHARM',
            'name' => 'Pharmacy Cost Center',
            'department' => 'Pharmacy',
            'is_active' => true,
        ]);
        $item = InventoryItem::create(['name' => 'Syringe', 'sku' => 'RIS-SYR-01', 'unit' => 'piece']);
        $requisition = MaterialRequisition::create([
            'requisition_number' => 'MR-RIS-ACK-01',
            'requesting_user_id' => $requester->id,
            'department' => 'Pharmacy',
            'cost_center_id' => $costCenter->id,
            'status' => 'acknowledged',
            'issued_by_id' => $issuer->id,
            'issued_at' => '2026-09-10 09:30:00',
            'acknowledged_by_id' => $requester->id,
            'acknowledged_at' => '2026-09-11 11:45:00',
        ]);
        $requisition->lines()->create([
            'item_id' => $item->id,
            'requested_quantity' => 5,
            'issued_quantity' => 5,
            'line_status' => 'issued',
        ]);

        $response = $this->actingAs($viewer)->get(route('inventory.logistics.ris.show', $requisition));

        $response->assertOk()->assertSeeText('CC-RIS-PHARM')->assertSee('>5</td>', false);
        $receivedSection = Str::between($response->getContent(), 'Received By:', 'HIMS System Reference');

        $this->assertStringContainsString($requester->name, $receivedSection);
        $this->assertStringContainsString('09/11/2026', $receivedSection);
        $this->assertStringNotContainsString($viewer->name, $receivedSection);
    }
}
