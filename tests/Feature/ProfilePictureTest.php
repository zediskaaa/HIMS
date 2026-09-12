<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AuthenticationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePictureTest extends TestCase
{
    use RefreshDatabase;

    private function createFakeJpg(string $name = 'avatar.jpg', int $extraBytes = 0): UploadedFile
    {
        $binary = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=')
            . str_repeat('A', $extraBytes);

        return UploadedFile::fake()->createWithContent($name, $binary);
    }

    private function createFakePng(string $name = 'avatar.png', int $extraBytes = 0): UploadedFile
    {
        $binary = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==')
            . str_repeat('A', $extraBytes);

        return UploadedFile::fake()->createWithContent($name, $binary);
    }

    public function test_user_can_view_profile_picture_section_in_profile_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk()
            ->assertSee('Profile Picture')
            ->assertSee('Upload a photo to personalize your avatar')
            ->assertSee('accept="image/jpeg,image/png,image/jpg"', false)
            ->assertSee('Add a profile picture');
    }

    public function test_user_can_upload_valid_jpg_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = $this->createFakeJpg('profile.jpg');

        $response = $this->actingAs($user)
            ->post(route('profile.avatar.update'), [
                'avatar' => $file,
            ]);

        $response->assertRedirect(route('profile.edit'))
            ->assertSessionHas('avatar_success', 'Profile picture updated successfully.');

        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
        $this->assertTrue($user->hasAvatar());
        $this->assertStringContainsString('/users/'.$user->id.'/avatar', $user->avatarUrl());

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Profile picture updated successfully.')
            ->assertSee('Change your profile picture')
            ->assertSee('Remove Picture')
            ->assertSee($user->avatarUrl(), false);
    }

    public function test_user_can_upload_valid_png_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = $this->createFakePng('profile.png');

        $response = $this->actingAs($user)
            ->post(route('profile.avatar.update'), [
                'avatar' => $file,
            ]);

        $response->assertRedirect(route('profile.edit'))
            ->assertSessionHas('avatar_success', 'Profile picture updated successfully.');

        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_replacing_avatar_deletes_old_file_from_storage(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        // 1. Upload first avatar
        $file1 = $this->createFakeJpg('first.jpg');
        $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => $file1]);
        $oldPath = $user->refresh()->avatar_path;
        Storage::disk('public')->assertExists($oldPath);

        // 2. Upload replacement avatar
        $file2 = $this->createFakePng('second.png');
        $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => $file2]);
        $newPath = $user->refresh()->avatar_path;

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_user_can_remove_avatar_and_revert_to_initials(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $file = $this->createFakeJpg('avatar.jpg');
        $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => $file]);
        $savedPath = $user->refresh()->avatar_path;
        Storage::disk('public')->assertExists($savedPath);

        // Delete avatar
        $response = $this->actingAs($user)->delete(route('profile.avatar.destroy'));

        $response->assertRedirect(route('profile.edit'))
            ->assertSessionHas('avatar_success', 'Profile picture removed. Your initials avatar is now active.');

        $user->refresh();
        $this->assertNull($user->avatar_path);
        $this->assertFalse($user->hasAvatar());
        Storage::disk('public')->assertMissing($savedPath);

        // Profile view reflects fallback
        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Add a profile picture')
            ->assertSee($user->initials());
    }

    public function test_non_image_files_are_rejected(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $invalidFiles = [
            UploadedFile::fake()->create('malicious.php', 50, 'application/x-php'),
            UploadedFile::fake()->create('document.pdf', 150, 'application/pdf'),
            UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload'),
            UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
        ];

        foreach ($invalidFiles as $file) {
            $response = $this->actingAs($user)
                ->from(route('profile.edit'))
                ->post(route('profile.avatar.update'), ['avatar' => $file]);

            $response->assertRedirect(route('profile.edit'))
                ->assertSessionHasErrors('avatar');

            $this->assertNull($user->refresh()->avatar_path);
        }
    }

    public function test_oversized_image_files_are_rejected(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        // 3500 KB extra bytes exceeds 3072 KB (3 MB) limit
        $oversized = $this->createFakeJpg('huge.jpg', 3500 * 1024);

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.avatar.update'), ['avatar' => $oversized]);

        $response->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->refresh()->avatar_path);
    }

    public function test_corrupted_or_spoofed_image_files_are_rejected_by_integrity_check(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        // Text content disguised with a .jpg filename
        $spoofed = UploadedFile::fake()->createWithContent(
            'fake.jpg',
            '<?php echo "I am not a real image file"; ?>',
        );

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.avatar.update'), ['avatar' => $spoofed]);

        $response->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->refresh()->avatar_path);
    }

    public function test_guest_cannot_upload_or_delete_profile_picture(): void
    {
        $file = $this->createFakeJpg('test.jpg');

        $this->post(route('profile.avatar.update'), ['avatar' => $file])
            ->assertRedirect(route('login'));

        $this->delete(route('profile.avatar.destroy'))
            ->assertRedirect(route('login'));
    }

    public function test_avatar_endpoint_streams_image_with_security_headers(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = $this->createFakeJpg('avatar.jpg');

        $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => $file]);
        $user->refresh();

        $response = $this->actingAs($user)->get(route('users.avatar', $user));

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringStartsWith('image/', $response->headers->get('Content-Type'));
    }

    public function test_avatar_endpoint_returns_404_when_user_has_no_avatar_or_file_is_missing(): void
    {
        $userWithoutAvatar = User::factory()->create(['avatar_path' => null]);
        $this->actingAs($userWithoutAvatar)
            ->get(route('users.avatar', $userWithoutAvatar))
            ->assertNotFound();

        Storage::fake('public');
        $userWithMissingFile = User::factory()->create(['avatar_path' => 'avatars/missing.jpg']);
        $this->actingAs($userWithMissingFile)
            ->get(route('users.avatar', $userWithMissingFile))
            ->assertNotFound();
    }

    public function test_avatar_renders_in_topbar_and_admin_user_views(): void
    {
        Storage::fake('public');

        $admin = User::factory()->administrator()->create();
        $file = $this->createFakePng('admin_avatar.png');

        $this->actingAs($admin, AuthenticationContext::ADMIN_GUARD)
            ->post(route('profile.avatar.update'), ['avatar' => $file]);

        $admin->refresh();

        // Topbar shows avatar URL
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee($admin->avatarUrl(), false);

        // Admin User Show shows avatar URL
        $this->get(route('admin.users.show', $admin))
            ->assertOk()
            ->assertSee($admin->avatarUrl(), false);

        // Admin User Index shows avatar URL
        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee($admin->avatarUrl(), false);
    }
}
