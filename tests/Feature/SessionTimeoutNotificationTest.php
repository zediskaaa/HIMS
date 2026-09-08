<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceSessionInactivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SessionTimeoutNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const CONTEXT_COOKIE = 'hims_inactivity';

    public static function panels(): array
    {
        return [
            'Staff' => ['web', 'login', 'session.expired', 'session.activity', 'logout'],
            'Admin' => ['admin', 'admin.login', 'admin.session.expired', 'admin.session.activity', 'admin.logout'],
            'Super Admin' => ['super_admin', 'super-admin.login', 'super-admin.session.expired', 'super-admin.session.activity', 'super-admin.logout'],
        ];
    }

    private function login(string $guard, string $route): void
    {
        $factory = User::factory();
        $factory = match ($guard) {
            'admin' => $factory->administrator(),
            'super_admin' => $factory->superAdministrator(),
            default => $factory,
        };
        $user = $factory->create(['password' => bcrypt('password')]);
        $response = $this->post(route($route), ['email' => $user->email, 'password' => 'password']);
        $response->assertRedirect();
        $cookie = $response->getCookie(self::CONTEXT_COOKIE);
        if ($cookie) {
            $this->withCookie(self::CONTEXT_COOKIE, $cookie->getValue());
        }
        $this->app['auth']->forgetGuards();
    }

    private function assertNoticeOnce(string $login): void
    {
        $response = $this->get(route($login))->assertOk()
            ->assertSee('Session Timeout')
            ->assertSee('Your session has expired due to inactivity. Please log in again.');
        if ($login === 'admin.login') {
            $response->assertDontSee('Access is limited to the administration modules assigned to your account.');
        }
        $response->assertSessionMissing('session_timeout');
        $this->get(route($login))->assertOk()->assertDontSee('Session Timeout');
    }

    #[DataProvider('panels')]
    public function test_activity_endpoint_confirms_and_extends_each_panel_session(
        string $guard,
        string $login,
        string $expired,
        string $activity,
        string $logout,
    ): void {
        config(['session.lifetime' => 4]);
        $this->login($guard, $login);
        $this->travel(3)->minutes();

        $this->postJson(route($activity))
            ->assertNoContent()
            ->assertHeader(
                EnforceSessionInactivity::LAST_ACTIVITY_RESPONSE_HEADER,
                (string) now()->getTimestamp(),
            );

        // This second request is six minutes after login but only three
        // minutes after continuation, proving the server deadline was renewed.
        $this->app['auth']->forgetGuards();
        $this->travel(3)->minutes();
        $this->postJson(route($activity))->assertNoContent();
        $this->assertAuthenticated($guard);
    }

    #[DataProvider('panels')]
    public function test_json_timeout_survives_browser_redirects(string $guard, string $login, string $expired, string $activity): void
    {
        config(['session.lifetime' => 4]);
        $this->login($guard, $login);
        $this->travel(4)->minutes();
        $this->postJson(route($activity))->assertUnauthorized()
            ->assertJsonPath('code', 'SESSION_TIMEOUT');
        // Apply the server's deletion as a browser would.
        $this->withCookie(self::CONTEXT_COOKIE, '');
        $this->app['auth']->forgetGuards();
        $this->assertGuest($guard);
        $this->get(URL::signedRoute($expired, absolute: false))->assertRedirect(route($login));
        // A fetch following a login redirect must not consume the visible notice.
        $this->withHeader('Sec-Fetch-Mode', 'cors')->get(route($login))->assertOk();
        $this->withHeader('Sec-Fetch-Mode', 'navigate');
        $this->assertNoticeOnce($login);
    }

    #[DataProvider('panels')]
    public function test_database_expiration_preserves_panel_and_notice(string $guard, string $login, string $expired): void
    {
        config(['session.driver' => 'database', 'session.lifetime' => 4]);
        $this->login($guard, $login);
        $this->travel(5)->minutes();
        // Force a fresh read: the real database handler now returns an empty
        // payload, so neither guard nor last-activity timestamp is available.
        $this->assertDatabaseCount('sessions', 1);
        $this->assertSame('', session()->getHandler()->read(session()->getId()));
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->get(URL::signedRoute($expired, absolute: false))
            ->assertRedirect(route($login))->assertSessionHas('session_timeout', true);
        $this->withCookie(self::CONTEXT_COOKIE, '');
        $this->app['auth']->forgetGuards();
        $this->assertGuest($guard);
        $this->assertNoticeOnce($login);
    }

    #[DataProvider('panels')]
    public function test_manual_logout_at_deadline_never_creates_notice(string $guard, string $login, string $expired, string $activity, string $logout): void
    {
        config(['session.lifetime' => 4]);
        $this->login($guard, $login);
        $this->travel(4)->minutes();
        $this->post(route($logout))->assertRedirect()->assertSessionMissing('session_timeout');
        $this->withCookie(self::CONTEXT_COOKIE, '');
        $this->app['auth']->forgetGuards();
        $this->assertGuest($guard);
        $this->get(URL::signedRoute($expired, absolute: false))->assertRedirect(route($login));
        $this->get(route($login))->assertOk()->assertDontSee('Session Timeout');
    }

    #[DataProvider('panels')]
    public function test_unconsumed_notice_expires(string $guard, string $login, string $expired, string $activity): void
    {
        config(['session.lifetime' => 4]);
        $this->login($guard, $login);
        $this->travel(4)->minutes();
        $this->postJson(route($activity))->assertUnauthorized();
        $this->withCookie(self::CONTEXT_COOKIE, '');
        $this->app['auth']->forgetGuards();
        $this->travel(5)->minutes();
        $this->get(route($login))->assertOk()->assertDontSee('Session Timeout');
    }

    #[DataProvider('panels')]
    public function test_relogin_clears_unconsumed_timeout(string $guard, string $login, string $expired, string $activity, string $logout): void
    {
        config(['session.lifetime' => 4]);
        $this->login($guard, $login);
        $this->travel(4)->minutes();
        $this->postJson(route($activity))->assertUnauthorized();
        $this->withCookie(self::CONTEXT_COOKIE, '');
        $this->app['auth']->forgetGuards();
        $this->login($guard, $login);
        $this->assertAuthenticated($guard);
        $this->assertFalse(session()->has('session_timeout'));
        $this->post(route($logout))->assertRedirect();
        $this->withCookie(self::CONTEXT_COOKIE, '');
        $this->get(route($login))->assertOk()->assertDontSee('Session Timeout');
    }
}
