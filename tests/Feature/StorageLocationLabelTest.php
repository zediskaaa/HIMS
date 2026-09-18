<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorageLocationLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehousing_locations_page_renders_label_form_with_copies_and_target_blank(): void
    {
        $user = User::factory()->warehouseStaff()->create();
        $location = StorageLocation::create([
            'name' => 'Cold Room A',
            'code' => 'COLD-01-A',
            'type' => 'cold_room',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('inventory.warehousing.locations'));

        $response->assertOk();
        $response->assertSee(route('inventory.storage-locations.label', $location));
        $response->assertSee('name="copies" value="1"', false);
        $response->assertSee('target="_blank"', false);
    }

    public function test_print_label_defaults_to_one_copy_when_copies_parameter_is_omitted(): void
    {
        $user = User::factory()->warehouseStaff()->create();
        $location = StorageLocation::create([
            'name' => 'Cold Room A',
            'code' => 'COLD-01-A',
            'type' => 'cold_room',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post(route('inventory.storage-locations.label', $location));

        $response->assertOk();
        $response->assertViewIs('inventory.warehouse_tasks.label');
        $response->assertViewHas('label', fn ($label) => $label->copies === 1);
    }

    public function test_print_label_accepts_valid_copies_count(): void
    {
        $user = User::factory()->warehouseStaff()->create();
        $location = StorageLocation::create([
            'name' => 'Bin 01',
            'code' => 'BIN-01',
            'type' => 'bin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post(route('inventory.storage-locations.label', $location), [
            'copies' => 4,
        ]);

        $response->assertOk();
        $response->assertViewIs('inventory.warehouse_tasks.label');
        $response->assertViewHas('label', fn ($label) => $label->copies === 4);
    }

    public function test_print_label_validates_copies_bounds(): void
    {
        $user = User::factory()->warehouseStaff()->create();
        $location = StorageLocation::create([
            'name' => 'Bin 01',
            'code' => 'BIN-01',
            'type' => 'bin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post(route('inventory.storage-locations.label', $location), [
            'copies' => 0,
        ]);

        $response->assertSessionHasErrors('copies');

        $responseHigh = $this->actingAs($user)->post(route('inventory.storage-locations.label', $location), [
            'copies' => 25,
        ]);

        $responseHigh->assertSessionHasErrors('copies');
    }

    public function test_unauthorized_user_cannot_print_location_label(): void
    {
        $viewer = User::factory()->viewer()->create();
        $location = StorageLocation::create([
            'name' => 'Bin 01',
            'code' => 'BIN-01',
            'type' => 'bin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($viewer)->post(route('inventory.storage-locations.label', $location));

        $response->assertForbidden();
    }
}
