<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Support\AuthenticationPanel;
use Illuminate\Support\Facades\Route;

// This signed endpoint is intentionally outside the auth/guest groups. It can
// still clear a session when Laravel's storage lifetime elapsed before the
// browser timer fired, and always creates a fresh one-time timeout notice.
Route::get('session/expired', [AuthenticatedSessionController::class, 'expired'])
    ->middleware('signed:relative')
    ->name('session.expired');

Route::middleware(['guest:web', 'guest:admin', 'guest:super_admin'])->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->defaults('auth_panel', AuthenticationPanel::Staff->value)
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->defaults('auth_panel', AuthenticationPanel::Staff->value)
        ->name('password.email');

    // Retire the former client-side OTP flow. It did not carry a Laravel
    // broker token, so these compatibility endpoints can never reset data.
    Route::get('reset-password', fn () => redirect()->route('password.request'))
        ->name('password.reset.legacy');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->defaults('auth_panel', AuthenticationPanel::Staff->value)
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->defaults('auth_panel', AuthenticationPanel::Staff->value)
        ->name('password.store');

    // Keep old bookmarks working without accepting tokenless resets.
    Route::match(['get', 'post'], 'reset-password-otp', fn () => redirect()
        ->route('password.request')
        ->withErrors(['email' => 'Please request a new secure password reset link.']))
        ->name('password.reset.otp.legacy');
});

Route::middleware('auth:web,admin,super_admin')->group(function () {
    // Meaningful browser interaction is synchronized here. The global
    // inactivity middleware updates the authoritative timestamp before this
    // no-content response is returned.
    Route::post('session/activity', fn () => response()->noContent())
        ->name('session.activity');

    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
