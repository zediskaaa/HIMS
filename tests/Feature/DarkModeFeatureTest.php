<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AuthenticationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DarkModeFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_layout_includes_theme_script_and_theme_toggle(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();

        // Check zero-flicker theme script in head
        $response->assertSee("localStorage.getItem('hims_theme')", false);
        $response->assertSee("classList.add('dark')", false);

        // Check theme toggle in guest header
        $response->assertSee('data-theme-toggle', false);
        $response->assertSee('$store.theme.toggle()', false);
    }

    public function test_authenticated_layout_includes_theme_script_and_theme_toggles(): void
    {
        $user = User::factory()->administrator()->create();

        $response = $this->actingAs($user, AuthenticationContext::ADMIN_GUARD)
            ->get(route('dashboard'));

        $response->assertOk();

        // Check zero-flicker theme script in head
        $response->assertSee("localStorage.getItem('hims_theme')", false);

        // Check quick toggle in topbar
        $response->assertSee('data-theme-toggle', false);
        $response->assertSee('$store.theme.toggle()', false);

        // Check dropdown switch theme item. It reads the resolved theme from
        // `effective`; the store exposes no `resolved` property, so an earlier
        // assertion on that name was pinning a reference that rendered blank.
        $response->assertSee('Switch theme', false);
        $response->assertSee('$store.theme.effective', false);
    }

    public function test_profile_page_includes_appearance_and_theme_settings_card(): void
    {
        $user = User::factory()->administrator()->create();

        $response = $this->actingAs($user, AuthenticationContext::ADMIN_GUARD)
            ->get(route('profile.edit'));

        $response->assertOk();

        // Check Appearance & Theme card
        $response->assertSee('Appearance &amp; Theme', false);
        $response->assertSee('Customize how HIMS displays on your screen', false);
        $response->assertSee('Light', false);
        $response->assertSee('Dark', false);
        $response->assertSee('System', false);
        $response->assertSee('$store.theme.set(\'light\')', false);
        $response->assertSee('$store.theme.set(\'dark\')', false);
        $response->assertSee('$store.theme.set(\'system\')', false);
    }

    public function test_theme_js_and_tailwind_config_are_properly_configured(): void
    {
        $tailwindConfig = file_get_contents(base_path('tailwind.config.js'));
        $this->assertStringContainsString("darkMode: 'class'", $tailwindConfig);

        $themeJs = file_get_contents(resource_path('js/theme.js'));
        $this->assertStringContainsString('himsTheme', $themeJs);
        $this->assertStringContainsString('localStorage.setItem', $themeJs);
        $this->assertStringContainsString("Alpine.store('theme'", $themeJs);

        $appJs = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('registerThemeWithAlpine', $appJs);
    }
}
