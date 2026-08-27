<?php

namespace App\Providers;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Models\User;
use App\Observers\UserObserver;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPermissionGates();
        $this->registerAuditLogging();
    }

    /**
     * Expose every Permission as a Gate ability.
     *
     * Registering them from the enum means a new permission is available to
     * `can:` middleware and `@can` in Blade the moment it is added to the enum,
     * with nothing here to keep in step.
     */
    private function registerPermissionGates(): void
    {
        foreach (Permission::cases() as $permission) {
            Gate::define(
                $permission->value,
                fn (User $user) => $user->hasPermission($permission)
            );
        }

        // An administrator passes every check without each role having to
        // enumerate the full list. Returning null rather than false lets the
        // individual gates decide for everyone else.
        Gate::before(fn (User $user) => $user->isAdministrator() && $user->isActive() ? true : null);
    }

    /**
     * Stamp `last_login_at` so the user list can show dormant accounts.
     */
    private function registerAuditLogging(): void
    {
        User::observe(UserObserver::class);

        Event::listen(function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

                app(AuditLogger::class)->log(
                    AuditAction::LoggedIn,
                    $event->user,
                    "{$event->user->name} logged in.",
                    $event->user,
                    'Account',
                );
            }
        });

        Event::listen(function (Logout $event): void {
            if ($event->user instanceof User) {
                app(AuditLogger::class)->log(
                    AuditAction::LoggedOut,
                    $event->user,
                    "{$event->user->name} logged out.",
                    $event->user,
                    'Account',
                );
            }
        });
    }
}
