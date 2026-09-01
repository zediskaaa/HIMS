<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\PasswordResetOtpController;
use App\Support\AuthenticationPanel;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware(['guest:web', 'guest:admin', 'guest:super_admin'])->group(function () {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
        Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
            ->defaults('auth_panel', AuthenticationPanel::Admin->value)
            ->name('password.request');
        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
            ->defaults('auth_panel', AuthenticationPanel::Admin->value)
            ->name('password.email');
        Route::get('reset-password-otp', [PasswordResetOtpController::class, 'show'])
            ->defaults('auth_panel', AuthenticationPanel::Admin->value)
            ->name('password.otp');
        Route::post('reset-password-otp', [PasswordResetOtpController::class, 'verify'])
            ->defaults('auth_panel', AuthenticationPanel::Admin->value)
            ->middleware('throttle:6,1')
            ->name('password.otp.verify');
        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
            ->defaults('auth_panel', AuthenticationPanel::Admin->value)
            ->name('password.reset');
        Route::post('reset-password', [NewPasswordController::class, 'store'])
            ->defaults('auth_panel', AuthenticationPanel::Admin->value)
            ->name('password.store');
    });

    Route::get('session/expired', [AuthenticatedSessionController::class, 'expired'])
        ->middleware('signed:relative')
        ->name('session.expired');

    Route::middleware(['auth:admin', 'administrator'])->group(function () {
        Route::post('session/activity', fn () => response()->noContent())->name('session.activity');
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    });
});
