<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\SupplierAccreditationStatus;
use App\Enums\SupplierStatus;
use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupplierLogoTest extends TestCase
{
    use RefreshDatabase;

    /** A 1x1 baseline JPEG, so getimagesize() returns real dimensions. */
    private const JPG_HEADER = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';

    /** A 1x1 PNG, so getimagesize() returns real dimensions. */
    private const PNG_HEADER = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private function createFakeJpg(string $name = 'logo.jpg', int $extraBytes = 0): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::JPG_HEADER).str_repeat('A', $extraBytes));
    }

    private function createFakePng(string $name = 'logo.png', int $extraBytes = 0): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG_HEADER).str_repeat('A', $extraBytes));
    }

    private function manager(): User
    {
        return User::factory()->inventoryManager()->create();
    }

    private function supplier(array $overrides = []): Supplier
    {
        return Supplier::create(array_replace([
            'name' => 'Zeta Vaccine Supply',
            'business_structure' => 'corporation',
            'address' => '100 Health Avenue, Manila',
            'status' => SupplierStatus::Active,
            'accreditation_status' => SupplierAccreditationStatus::Draft,
        ], $overrides));
    }

    public function test_manager_can_upload_a_jpg_logo(): void
    {
        Storage::fake('local');

        $manager = $this->manager();
        $supplier = $this->supplier();

        $response = $this->actingAs($manager)
            ->from(route('inventory.suppliers.show', $supplier))
            ->post(route('inventory.suppliers.logo.update', $supplier), [
                'logo' => $this->createFakeJpg('supplier.jpg'),
            ]);

        $response->assertRedirect(route('inventory.suppliers.show', $supplier))
            ->assertSessionHas('success', 'Supplier logo updated.');

        $supplier->refresh();

        $this->assertNotNull($supplier->logo_path);
        $this->assertTrue($supplier->hasLogo());
        Storage::disk('local')->assertExists($supplier->logo_path);
        $this->assertStringContainsString('/inventory/suppliers/'.$supplier->id.'/logo', $supplier->logoUrl());
    }

    public function test_manager_can_upload_a_png_logo(): void
    {
        Storage::fake('local');

        $manager = $this->manager();
        $supplier = $this->supplier();

        $this->actingAs($manager)
            ->from(route('inventory.suppliers.show', $supplier))
            ->post(route('inventory.suppliers.logo.update', $supplier), [
                'logo' => $this->createFakePng('supplier.png'),
            ])
            ->assertSessionHasNoErrors();

        $supplier->refresh();

        $this->assertNotNull($supplier->logo_path);
        Storage::disk('local')->assertExists($supplier->logo_path);
    }

    public function test_replacing_a_logo_deletes_the_previous_file(): void
    {
        Storage::fake('local');

        $manager = $this->manager();
        $supplier = $this->supplier();

        $this->actingAs($manager)->post(route('inventory.suppliers.logo.update', $supplier), [
            'logo' => $this->createFakeJpg('first.jpg'),
        ]);
        $oldPath = $supplier->refresh()->logo_path;
        Storage::disk('local')->assertExists($oldPath);

        $this->actingAs($manager)->post(route('inventory.suppliers.logo.update', $supplier), [
            'logo' => $this->createFakePng('second.png'),
        ]);
        $newPath = $supplier->refresh()->logo_path;

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);
    }

    public function test_manager_can_remove_a_logo_and_revert_to_initials(): void
    {
        Storage::fake('local');

        $manager = $this->manager();
        $supplier = $this->supplier();

        $this->actingAs($manager)->post(route('inventory.suppliers.logo.update', $supplier), [
            'logo' => $this->createFakeJpg('supplier.jpg'),
        ]);
        $savedPath = $supplier->refresh()->logo_path;
        Storage::disk('local')->assertExists($savedPath);

        $response = $this->actingAs($manager)
            ->from(route('inventory.suppliers.show', $supplier))
            ->delete(route('inventory.suppliers.logo.destroy', $supplier));

        $response->assertRedirect(route('inventory.suppliers.show', $supplier))
            ->assertSessionHas('success', 'Supplier logo removed. The supplier initials are now shown.');

        $supplier->refresh();

        $this->assertNull($supplier->logo_path);
        $this->assertFalse($supplier->hasLogo());
        $this->assertNull($supplier->logoUrl());
        Storage::disk('local')->assertMissing($savedPath);
    }

    public function test_non_image_files_are_rejected_and_persist_nothing(): void
    {
        Storage::fake('local');

        $manager = $this->manager();
        $supplier = $this->supplier();

        $invalidFiles = [
            UploadedFile::fake()->create('malicious.php', 50, 'application/x-php'),
            UploadedFile::fake()->create('document.pdf', 150, 'application/pdf'),
            UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload'),
            UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
        ];

        foreach ($invalidFiles as $file) {
            $this->actingAs($manager)
                ->from(route('inventory.suppliers.show', $supplier))
                ->post(route('inventory.suppliers.logo.update', $supplier), ['logo' => $file])
                ->assertSessionHasErrors('logo');

            $this->assertNull($supplier->refresh()->logo_path);
        }

        $this->assertSame([], Storage::disk('local')->allFiles('supplier-logos/'.$supplier->id));
    }

    public function test_an_oversized_image_is_rejected(): void
    {
        Storage::fake('local');

        $manager = $this->manager();
        $supplier = $this->supplier();

        // 3500 KB of extra bytes exceeds the 3072 KB (3 MB) limit.
        $oversized = $this->createFakeJpg('huge.jpg', 3500 * 1024);

        $this->actingAs($manager)
            ->from(route('inventory.suppliers.show', $supplier))
            ->post(route('inventory.suppliers.logo.update', $supplier), ['logo' => $oversized])
            ->assertSessionHasErrors('logo');

        $this->assertNull($supplier->fresh()->logo_path);
        $this->assertSame([], Storage::disk('local')->allFiles('supplier-logos/'.$supplier->id));
    }

    public function test_a_spoofed_or_corrupted_image_is_rejected_by_the_integrity_check(): void
    {
        Storage::fake('local');

        $manager = $this->manager();
        $supplier = $this->supplier();

        // Text content disguised behind a .jpg filename.
        $spoofed = UploadedFile::fake()->createWithContent(
            'fake.jpg',
            '<?php echo "I am not a real image file"; ?>',
        );

        $this->actingAs($manager)
            ->from(route('inventory.suppliers.show', $supplier))
            ->post(route('inventory.suppliers.logo.update', $supplier), ['logo' => $spoofed])
            ->assertSessionHasErrors('logo');

        $this->assertNull($supplier->fresh()->logo_path);
        $this->assertSame([], Storage::disk('local')->allFiles('supplier-logos/'.$supplier->id));
    }

    public function test_a_truncated_image_that_still_reports_as_jpeg_is_rejected_by_the_integrity_check(): void
    {
        Storage::fake('local');

        $manager = $this->manager();
        $supplier = $this->supplier();

        // Cut the JPEG off at its start-of-frame marker. finfo still detects
        // image/jpeg, so the mimes/mimetypes rules pass, but getimagesize() cannot
        // read any dimensions. Without the validator's integrity closure this file
        // would be stored and then served back as an image.
        $full = base64_decode(self::JPG_HEADER);
        $cut = strpos($full, "\xFF\xC2");
        $this->assertNotFalse($cut, 'The fixture no longer contains a start-of-frame marker to truncate at.');

        $file = UploadedFile::fake()->createWithContent('truncated.jpg', substr($full, 0, $cut));

        // Prove the fixture really is the trap being tested.
        $this->assertSame('image/jpeg', (new \finfo(\FILEINFO_MIME_TYPE))->file($file->getRealPath()));
        $this->assertFalse(@getimagesize($file->getRealPath()));

        $this->actingAs($manager)
            ->from(route('inventory.suppliers.show', $supplier))
            ->post(route('inventory.suppliers.logo.update', $supplier), ['logo' => $file])
            ->assertSessionHasErrors('logo');

        $this->assertNull($supplier->fresh()->logo_path);
        $this->assertSame([], Storage::disk('local')->allFiles('supplier-logos/'.$supplier->id));
    }

    public function test_a_missing_file_part_is_rejected(): void
    {
        Storage::fake('local');

        $supplier = $this->supplier();

        $this->actingAs($this->manager())
            ->from(route('inventory.suppliers.show', $supplier))
            ->post(route('inventory.suppliers.logo.update', $supplier), [])
            ->assertSessionHasErrors('logo');

        $this->assertNull($supplier->fresh()->logo_path);
    }

    public function test_images_wider_than_the_dimension_cap_are_rejected(): void
    {
        Storage::fake('local');

        $manager = $this->manager();
        $supplier = $this->supplier();

        // 4100px wide clears the 4096px cap; synthesized PNG header avoids GD extension dependency.
        $ihdr = 'IHDR'.pack('NN', 4100, 10).chr(8).chr(0).chr(0).chr(0).chr(0);
        $crc = pack('N', crc32($ihdr));
        $binary = chr(137)."PNG\r\n\x1a\n".pack('N', 13).$ihdr.$crc.pack('N', 0).'IEND'.pack('N', crc32('IEND'));

        $this->actingAs($manager)
            ->from(route('inventory.suppliers.show', $supplier))
            ->post(route('inventory.suppliers.logo.update', $supplier), [
                'logo' => UploadedFile::fake()->createWithContent('wide.png', $binary),
            ])
            ->assertSessionHasErrors('logo');

        $this->assertNull($supplier->fresh()->logo_path);
        $this->assertSame([], Storage::disk('local')->allFiles('supplier-logos/'.$supplier->id));
    }

    public function test_users_without_manage_suppliers_cannot_upload_or_remove_a_logo(): void
    {
        Storage::fake('local');

        $supplier = $this->supplier();

        // This is the regression guard for SupplierController::middleware(): those
        // permission lists are per-method, so a new method left out of them would be
        // reachable by any signed-in user.
        foreach ([User::factory()->viewer()->create(), User::factory()->auditor()->create()] as $user) {
            $this->actingAs($user)
                ->post(route('inventory.suppliers.logo.update', $supplier), [
                    'logo' => $this->createFakeJpg('blocked.jpg'),
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->delete(route('inventory.suppliers.logo.destroy', $supplier))
                ->assertForbidden();
        }

        $this->assertNull($supplier->fresh()->logo_path);
        $this->assertSame([], Storage::disk('local')->allFiles('supplier-logos/'.$supplier->id));
    }

    public function test_streaming_a_logo_requires_view_suppliers_permission(): void
    {
        Storage::fake('local');

        $manager = $this->manager();
        $supplier = $this->supplier();

        $this->actingAs($manager)->post(route('inventory.suppliers.logo.update', $supplier), [
            'logo' => $this->createFakePng('viewable.png'),
        ]);

        // Viewer and Auditor hold ViewSuppliers.
        $this->actingAs(User::factory()->viewer()->create())
            ->get(route('inventory.suppliers.logo', $supplier))
            ->assertOk();

        $this->actingAs(User::factory()->auditor()->create())
            ->get(route('inventory.suppliers.logo', $supplier))
            ->assertOk();

        // Warehouse staff do not.
        $this->actingAs(User::factory()->warehouseStaff()->create())
            ->get(route('inventory.suppliers.logo', $supplier))
            ->assertForbidden();
    }

    public function test_guest_cannot_upload_or_remove_a_logo(): void
    {
        $supplier = $this->supplier();

        $this->post(route('inventory.suppliers.logo.update', $supplier), [
            'logo' => $this->createFakeJpg('guest.jpg'),
        ])->assertRedirect(route('login'));

        $this->delete(route('inventory.suppliers.logo.destroy', $supplier))
            ->assertRedirect(route('login'));
    }

    public function test_logo_endpoint_streams_the_image_with_security_headers(): void
    {
        Storage::fake('local');

        $manager = $this->manager();
        $supplier = $this->supplier();

        $this->actingAs($manager)->post(route('inventory.suppliers.logo.update', $supplier), [
            'logo' => $this->createFakeJpg('supplier.jpg'),
        ]);
        $supplier->refresh();

        $response = $this->actingAs($manager)->get(route('inventory.suppliers.logo', $supplier));

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringStartsWith('image/', $response->headers->get('Content-Type'));
    }

    public function test_logo_endpoint_returns_404_when_no_logo_or_the_file_is_missing(): void
    {
        Storage::fake('local');

        $manager = $this->manager();

        $withoutLogo = $this->supplier(['name' => 'No Logo Vendor']);
        $this->actingAs($manager)
            ->get(route('inventory.suppliers.logo', $withoutLogo))
            ->assertNotFound();

        $missingFile = $this->supplier(['name' => 'Missing File Vendor']);
        $missingFile->forceFill(['logo_path' => 'supplier-logos/'.$missingFile->id.'/gone.jpg'])->save();
        $this->actingAs($manager)
            ->get(route('inventory.suppliers.logo', $missingFile))
            ->assertNotFound();
    }

    public function test_directory_and_profile_render_the_logo_and_fall_back_to_initials(): void
    {
        Storage::fake('local');

        $manager = $this->manager();
        $supplier = $this->supplier(['name' => 'Zeta Vaccine Supply']);

        // No logo yet: the initials tile stands in and no image is requested.
        $this->actingAs($manager)->get(route('inventory.suppliers'))
            ->assertOk()
            ->assertSee('ZV')
            ->assertDontSee('/inventory/suppliers/'.$supplier->id.'/logo', false);

        $this->actingAs($manager)
            ->from(route('inventory.suppliers.show', $supplier))
            ->post(route('inventory.suppliers.logo.update', $supplier), [
                'logo' => $this->createFakePng('supplier.png'),
            ]);

        $logoUrl = $supplier->fresh()->logoUrl();

        $this->actingAs($manager)->get(route('inventory.suppliers'))
            ->assertOk()
            ->assertSee($logoUrl, false)
            ->assertDontSee('ZV');

        $this->actingAs($manager)->get(route('inventory.suppliers.show', $supplier))
            ->assertOk()
            ->assertSee($logoUrl, false);
    }

    public function test_the_edit_supplier_dialog_exposes_the_logo_control(): void
    {
        Storage::fake('local');

        $supplier = $this->supplier();

        $this->actingAs($this->manager())
            ->get(route('inventory.suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('Display picture / Logo')
            ->assertSee('accept="image/jpeg,image/png,image/jpg"', false)
            ->assertSee('name="logo"', false)
            ->assertSee('Choose Image');
    }

    public function test_the_remove_logo_control_appears_only_when_a_logo_exists(): void
    {
        Storage::fake('local');

        $manager = $this->manager();
        $supplier = $this->supplier();

        $this->actingAs($manager)->get(route('inventory.suppliers.show', $supplier))
            ->assertOk()
            ->assertDontSee('Remove Logo');

        $this->actingAs($manager)->post(route('inventory.suppliers.logo.update', $supplier), [
            'logo' => $this->createFakeJpg('supplier.jpg'),
        ]);

        $this->actingAs($manager)->get(route('inventory.suppliers.show', $supplier->fresh()))
            ->assertOk()
            ->assertSee('Remove Logo');
    }

    public function test_logo_changes_are_audited_and_rejections_are_not(): void
    {
        Storage::fake('local');

        $manager = $this->manager();
        $supplier = $this->supplier();

        $this->actingAs($manager)->post(route('inventory.suppliers.logo.update', $supplier), [
            'logo' => $this->createFakeJpg('first.jpg'),
        ]);

        $logs = AuditLog::where('action', AuditAction::UpdatedSupplier->value)
            ->where('target_id', (string) $supplier->id)
            ->get();

        $this->assertCount(1, $logs);
        $this->assertSame($manager->id, $logs->first()->user_id);
        $this->assertSame($supplier->name, $logs->first()->target_name);
        $this->assertSame('Updated the supplier logo.', $logs->first()->description);

        // A rejected upload must not produce a success event.
        $this->actingAs($manager)
            ->from(route('inventory.suppliers.show', $supplier))
            ->post(route('inventory.suppliers.logo.update', $supplier), [
                'logo' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('logo');

        $this->assertSame(1, AuditLog::where('action', AuditAction::UpdatedSupplier->value)
            ->where('target_id', (string) $supplier->id)
            ->count());

        // Removal is recorded as its own event.
        $this->actingAs($manager)->delete(route('inventory.suppliers.logo.destroy', $supplier));

        $this->assertSame(2, AuditLog::where('action', AuditAction::UpdatedSupplier->value)
            ->where('target_id', (string) $supplier->id)
            ->count());

        $this->assertSame('Removed the supplier logo.', AuditLog::where('action', AuditAction::UpdatedSupplier->value)
            ->where('target_id', (string) $supplier->id)
            ->latest('id')
            ->first()
            ->description);
    }

    public function test_manager_can_optionally_upload_a_logo_during_supplier_creation(): void
    {
        Storage::fake('local');

        $manager = $this->manager();

        $response = $this->actingAs($manager)->post(route('inventory.suppliers.store'), [
            'name' => 'Metro Health Logistics',
            'trade_name' => 'Metro Logistics',
            'business_structure' => 'corporation',
            'address' => '77 Pioneer Way, Pasig City',
            'logo' => $this->createFakePng('company-logo.png'),
        ]);

        $supplier = Supplier::where('name', 'Metro Health Logistics')->first();
        $this->assertNotNull($supplier);

        $response->assertRedirect(route('inventory.suppliers.show', $supplier));
        $this->assertNotNull($supplier->logo_path);
        $this->assertTrue($supplier->hasLogo());
        Storage::disk('local')->assertExists($supplier->logo_path);
    }

    public function test_supplier_creation_without_logo_succeeds_as_optional(): void
    {
        Storage::fake('local');

        $manager = $this->manager();

        $response = $this->actingAs($manager)->post(route('inventory.suppliers.store'), [
            'name' => 'Apex Pharma Solutions',
            'business_structure' => 'corporation',
            'address' => '12 Ayala Avenue, Makati',
        ]);

        $supplier = Supplier::where('name', 'Apex Pharma Solutions')->first();
        $this->assertNotNull($supplier);

        $response->assertRedirect(route('inventory.suppliers.show', $supplier));
        $this->assertNull($supplier->logo_path);
        $this->assertFalse($supplier->hasLogo());
    }

    public function test_invalid_logo_during_supplier_creation_is_rejected(): void
    {
        Storage::fake('local');

        $manager = $this->manager();

        $response = $this->actingAs($manager)->post(route('inventory.suppliers.store'), [
            'name' => 'Invalid Logo Supplier',
            'business_structure' => 'corporation',
            'address' => '123 Fake Street',
            'logo' => UploadedFile::fake()->create('contract.pdf', 10, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('logo');
        $this->assertNull(Supplier::where('name', 'Invalid Logo Supplier')->first());
    }

    public function test_create_supplier_modal_contains_optional_dp_input(): void
    {
        $manager = $this->manager();

        $response = $this->actingAs($manager)->get(route('inventory.suppliers'));

        $response->assertOk()
            ->assertSee('Display picture / Logo')
            ->assertSee('Optional')
            ->assertSee('name="logo"', false);
    }
}
