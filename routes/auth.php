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
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    // Forgot-password OTP frontend hits this to confirm the email is registered
    // before sending an OTP via Google Apps Script. GET avoids CSRF since the
    // caller is a static HTML page. Throttled to discourage enumeration.
    Route::get('check-email', function (Request $request) {
        $request->validate(['email' => ['required', 'email']]);

        $exists = User::where('email', $request->email)->exists();

        return response()->json([
            'exists'  => $exists,
            'message' => $exists
                ? 'Email found.'
                : 'This email is not registered in the system.',
        ]);
    })->middleware('throttle:10,1')->name('check-email');

    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    // Keep previously cached OTP pages working. They used /reset-password with
    // an email query string, while Laravel reserves that path for POST submits.
    Route::get('reset-password', function (Request $request) {
        return redirect()->route('password.reset.otp', [
            'email' => $request->query('email', ''),
        ]);
    })->name('password.reset.otp.redirect');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');

    // ── OTP-verified password reset (no token required) ──────────────
    // The user's identity was already confirmed via email OTP on the
    // static HTML page, so these routes accept just email + new password.

    Route::get('reset-password-otp', function (Request $request) {
        return view('auth.reset-password-otp', [
            'email' => $request->query('email', ''),
        ]);
    })->name('password.reset.otp');

    Route::post('reset-password-otp', function (Request $request) {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return back()->withErrors(['email' => 'No account found with this email.']);
        }

        $user->forceFill([
            'password'       => \Illuminate\Support\Facades\Hash::make($request->password),
            'remember_token' => \Illuminate\Support\Str::random(60),
        ])->save();

        event(new \Illuminate\Auth\Events\PasswordReset($user));

        return redirect()->route('login')->with('status', 'Your password has been reset successfully!');
    })->name('password.store.otp');
});

Route::middleware('auth')->group(function () {
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
