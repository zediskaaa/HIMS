<?php

use App\Http\Middleware\EnforceSessionInactivity;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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

        // Runs on every authenticated web request so that deactivating an
        // account takes effect immediately, not at the end of their session.
        $middleware->web(append: [
            EnforceSessionInactivity::class,
            EnsureUserIsActive::class,
        ]);

        // Stateful browser calls to /api/v1 use the same web session and must
        // obey the same inactivity cutoff. Bearer-token requests are unchanged
        // because the middleware only acts on the authenticated web guard.
        $middleware->api(append: [EnforceSessionInactivity::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
