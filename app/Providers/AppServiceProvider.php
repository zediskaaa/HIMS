<?php

namespace App\Providers;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Models\User;
use App\Observers\UserObserver;
use App\Services\AuditLogger;
use App\Support\AuthenticationPanel;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Notifications\ResetPassword;
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
        $this->registerPasswordResetUrls();
    }

    /**
     * Every reset email returns to the panel assigned to the account's role.
     */
    private function registerPasswordResetUrls(): void
    {
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            $panel = AuthenticationPanel::forRole($user->role);

            return route($panel->passwordResetRoute(), [
                'token' => $token,
                'email' => $user->email,
            ]);
        });
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

        Event::listen(function (Failed $event): void {
            if ($event->user instanceof User) {
                app(AuditLogger::class)->log(
                    AuditAction::FailedLogin,
                    null,
                    'A failed sign-in attempt was recorded for an existing account.',
                    $event->user,
                    'Account',
                    source: 'user',
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
