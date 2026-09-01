<?php

use App\Http\Controllers\SuperAdmin\AuthenticatedSessionController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('super-admin')->name('super-admin.')->group(function () {
    Route::middleware(['guest:web', 'guest:admin', 'guest:super_admin'])->group(function () {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    });

    Route::get('session/expired', [AuthenticatedSessionController::class, 'expired'])
        ->middleware('signed:relative')
        ->name('session.expired');

    Route::middleware(['auth:super_admin', 'super-admin'])->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::post('session/activity', fn () => response()->noContent())->name('session.activity');
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    });
});
