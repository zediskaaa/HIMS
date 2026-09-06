<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceSessionInactivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('session.lifetime', 4);
        config()->set('session.warning_seconds', 60);
    }

    public function test_authenticated_layout_exposes_an_accessible_session_warning_from_central_config(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('data-session-timeout-seconds="240"', false)
            ->assertSee('data-session-warning-seconds="60"', false)
            ->assertSee('data-session-warning', false)
            ->assertSee('aria-modal="true"', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('data-session-warning-audio', false)
            ->assertSee(asset('audio/session_sound.mp3'), false)
            ->assertDontSee('Play alert sound')
            ->assertSee('Your session is about to expire')
            ->assertSee('Continue Session')
            ->assertSee('Dismiss');
    }

    public function test_session_warning_sound_is_available_from_the_public_asset_directory(): void
    {
        $soundPath = public_path('audio/session_sound.mp3');

        $this->assertFileExists($soundPath);
        $this->assertGreaterThan(0, filesize($soundPath));
        $this->assertSame('ID3', file_get_contents($soundPath, false, null, 0, 3));
    }

    public function test_login_starts_the_inactivity_clock(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertIsInt(session(EnforceSessionInactivity::LAST_ACTIVITY_AT));
    }

    public function test_inactive_web_session_is_logged_out_and_redirected_with_notice(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession([
                EnforceSessionInactivity::LAST_ACTIVITY_AT => now()->subMinutes(4)->getTimestamp(),
            ])
            ->get('/dashboard');

        $this->assertGuest();
        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('session_timeout', true);
    }

    public function test_normal_authenticated_requests_reset_the_inactivity_clock(): void
    {
        $user = User::factory()->create();
        $previousActivity = now()->subMinutes(3)->getTimestamp();
        $requestStartedAt = now()->getTimestamp();

        $response = $this
            ->actingAs($user)
            ->withSession([
                EnforceSessionInactivity::LAST_ACTIVITY_AT => $previousActivity,
            ])
            ->get('/dashboard');

        $response->assertOk();
        $this->assertAuthenticatedAs($user);
        $response->assertSessionHas(
            EnforceSessionInactivity::LAST_ACTIVITY_AT,
            fn (int $timestamp) => $timestamp >= $requestStartedAt,
        );
    }

    public function test_passive_background_request_does_not_reset_inactivity_clock(): void
    {
        $user = User::factory()->create();
        $previousActivity = now()->subMinutes(3)->getTimestamp();

        $response = $this
            ->actingAs($user)
            ->withSession([
                EnforceSessionInactivity::LAST_ACTIVITY_AT => $previousActivity,
            ])
            ->withHeader(EnforceSessionInactivity::PASSIVE_ACTIVITY_HEADER, 'passive')
            ->get('/dashboard/live');

        $response->assertOk();
        $response->assertSessionHas(
            EnforceSessionInactivity::LAST_ACTIVITY_AT,
            $previousActivity,
        );
    }

    public function test_expired_stateful_api_request_is_rejected_without_running_action(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        // Resolve authentication again from the session cookie, exactly as a
        // stateful browser API call does after the login response.
        $this->app['auth']->forgetGuards();
        $this->travel(4)->minutes();

        $response = $this
            ->withHeader('Referer', config('app.url'))
            ->getJson('/api/v1/demand-plans');

        $response
            ->assertUnauthorized()
            ->assertHeader('X-Session-Expired', 'true')
            ->assertJson([
                'code' => 'SESSION_TIMEOUT',
            ]);

        // Sanctum's request guard caches the user resolved earlier in this
        // request. Force the same fresh session lookup the next browser request
        // performs before asserting the web session was actually removed.
        $this->app['auth']->forgetGuards();
        $this->assertGuest();
    }

    public function test_signed_browser_timeout_logs_out_and_shows_notice_once(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession([
                EnforceSessionInactivity::LAST_ACTIVITY_AT => now()->subMinutes(4)->getTimestamp(),
            ])
            ->get(URL::signedRoute('session.expired', absolute: false));

        $this->assertGuest();
        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('session_timeout', true);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Session Timeout')
            ->assertSee('Your session has expired due to inactivity. Please log in again.');

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Your session has expired due to inactivity. Please log in again.');
    }

    public function test_signed_timeout_url_cannot_create_a_fake_timeout_before_inactivity_limit(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession([
                EnforceSessionInactivity::LAST_ACTIVITY_AT => now()->getTimestamp(),
            ])
            ->get(URL::signedRoute('session.expired', absolute: false));

        $response
            ->assertRedirect(route('login'))
            ->assertSessionMissing('session_timeout');
        $this->assertAuthenticatedAs($user);
    }

    public function test_stale_timeout_url_after_manual_logout_does_not_flash_timeout_notice(): void
    {
        $user = User::factory()->create();
        $signedTimeoutUrl = URL::signedRoute('session.expired', absolute: false);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertSessionMissing('session_timeout');

        $this->get($signedTimeoutUrl)
            ->assertRedirect(route('login'))
            ->assertSessionMissing('session_timeout');

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Your session has expired due to inactivity. Please log in again.');
    }

    public function test_timeout_notice_does_not_survive_relogin_and_later_manual_logout(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->actingAs($user)
            ->withSession([
                EnforceSessionInactivity::LAST_ACTIVITY_AT => now()->subMinutes(4)->getTimestamp(),
            ])
            ->get('/dashboard')
            ->assertSessionHas('session_timeout', true);

        $this->get(route('login'))
            ->assertSee('Session Timeout');

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->post(route('logout'))
            ->assertSessionMissing('session_timeout');

        $this->get(route('login'))
            ->assertDontSee('Your session has expired due to inactivity. Please log in again.');
    }

    public function test_activity_endpoint_keeps_an_active_session_authenticated(): void
    {
        $user = User::factory()->create();
        $requestStartedAt = now()->getTimestamp();

        $response = $this
            ->actingAs($user)
            ->withSession([
                EnforceSessionInactivity::LAST_ACTIVITY_AT => now()->subMinutes(3)->getTimestamp(),
            ])
            ->postJson(route('session.activity'));

        $response->assertNoContent();
        $response->assertHeader(
            EnforceSessionInactivity::LAST_ACTIVITY_RESPONSE_HEADER,
            (string) session(EnforceSessionInactivity::LAST_ACTIVITY_AT),
        );
        $this->assertAuthenticatedAs($user);
        $response->assertSessionHas(
            EnforceSessionInactivity::LAST_ACTIVITY_AT,
            fn (int $timestamp) => $timestamp >= $requestStartedAt,
        );
    }

    public function test_repeated_activity_keeps_the_user_signed_in_beyond_four_total_minutes(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->travel(3)->minutes();
        $this->postJson(route('session.activity'))->assertNoContent();

        $this->travel(3)->minutes();
        $this->get('/dashboard')->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_remember_me_cookie_cannot_restore_an_expired_session(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $guard = Auth::guard('web');
        $recallerName = $guard->getRecallerName();

        $loginResponse = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ]);

        $recallerCookie = $loginResponse->getCookie($recallerName);
        $this->assertNotNull($recallerCookie);

        // Simulate Laravel's session storage expiring while the longer-lived
        // remember cookie remains in the browser.
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $response = $this
            ->withCookie($recallerName, $recallerCookie->getValue())
            ->get('/dashboard');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('session_timeout', true);

        $this->app['auth']->forgetGuards();
        $this->assertGuest();
    }
}
