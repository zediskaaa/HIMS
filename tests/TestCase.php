<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AuthenticationContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Keep feature-test sessions aligned with the same role-to-panel mapping
     * production logins enforce. Tests can still pass an explicit guard when
     * they intentionally exercise a mismatched-session boundary.
     *
     * @return $this
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if ($guard === null && $user instanceof User) {
            $guard = match (true) {
                $user->isSuperAdministrator() => AuthenticationContext::SUPER_ADMIN_GUARD,
                $user->role === UserRole::Administrator => AuthenticationContext::ADMIN_GUARD,
                default => AuthenticationContext::WEB_GUARD,
            };
        }

        foreach (AuthenticationContext::sessionGuards() as $sessionGuard) {
            if ($sessionGuard !== $guard) {
                $this->app['auth']->guard($sessionGuard)->forgetUser();
            }
        }

        return parent::actingAs($user, $guard);
    }
}
