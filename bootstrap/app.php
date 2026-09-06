<?php

use App\Http\Middleware\EnforceSessionInactivity;
use App\Http\Middleware\EnsureAdministrator;
use App\Http\Middleware\EnsureAuthenticationPanelRole;
use App\Http\Middleware\EnsureSuperAdministrator;
use App\Http\Middleware\EnsureUserIsActive;
use App\Support\AuthenticationContext;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The Blade screens call /api/v1/* with the session cookie rather than a
        // bearer token. Without this, the api group never starts a session, so
        // auth:sanctum cannot resolve the logged-in user and every call 401s.
        $middleware->statefulApi();

        $middleware->alias([
            'administrator' => EnsureAdministrator::class,
            'super-admin' => EnsureSuperAdministrator::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => match (true) {
            $request->is('super-admin/*') => route('super-admin.login'),
            $request->routeIs('admin.audit-logs.*') => route('super-admin.login'),
            $request->is('admin/*') => route('admin.login'),
            default => route('login'),
        });

        $middleware->redirectUsersTo(fn () => Auth::guard(AuthenticationContext::SUPER_ADMIN_GUARD)->check()
            ? route('super-admin.dashboard')
            : route('dashboard'));

        // Runs on every authenticated web request so that deactivating an
        // account takes effect immediately, not at the end of their session.
        $middleware->web(append: [
            EnforceSessionInactivity::class,
            EnsureUserIsActive::class,
            EnsureAuthenticationPanelRole::class,
        ]);

        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            EnforceSessionInactivity::class,
        );

        // Stateful browser calls to /api/v1 use the same web session and must
        // obey the same inactivity cutoff. Bearer-token requests are unchanged
        // because the middleware only acts on the authenticated web guard.
        $middleware->api(append: [
            EnforceSessionInactivity::class,
            EnsureAuthenticationPanelRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Keep Laravel's CSRF protection unchanged, but present browser token
        // mismatches with the branded recovery screen even when debug mode is
        // enabled locally. JSON callers retain Laravel's default response.
        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return response()->view('errors.419', status: 419);
        });
    })->create();
