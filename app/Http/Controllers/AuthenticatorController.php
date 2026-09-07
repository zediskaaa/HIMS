<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthenticatorService;
use App\Services\AuthenticatorSetupService;
use App\Support\AuthenticationContext;
use App\Support\MfaSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthenticatorController extends Controller
{
    public function setup(Request $request, AuthenticatorSetupService $setup): RedirectResponse
    {
        $guard = AuthenticationContext::authenticatedGuard() ?? AuthenticationContext::WEB_GUARD;
        $user = $request->user($guard);

        abort_unless($user instanceof User, 401);

        if ($user->authenticatorMfaEnabled()) {
            return redirect()->route('profile.edit')->withErrors([
                'authenticator' => 'Authenticator app is already enabled.',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'current_password:'.$guard],
        ], [
            'current_password.required' => 'Current password is required.',
            'current_password.current_password' => 'Current password is incorrect.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('profile.edit')
                ->withErrors($validator, 'authenticatorSetup');
        }

        $setup->begin($request, $user);

        return redirect()->route('profile.edit');
    }

    public function enable(
        Request $request,
        AuthenticatorSetupService $setup,
        AuthenticatorService $authenticator,
    ): RedirectResponse {
        $guard = AuthenticationContext::authenticatedGuard() ?? AuthenticationContext::WEB_GUARD;
        $user = $request->user($guard);

        abort_unless($user instanceof User, 401);

        $validator = Validator::make($request->all(), [
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => 'Enter the 6-digit code from your authenticator app.',
            'code.digits' => 'Enter a valid 6-digit authenticator code.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('profile.edit')
                ->withErrors($validator, 'authenticatorEnable');
        }

        $validated = $validator->validated();

        $secret = $setup->secret($request, $user);

        if ($secret === null) {
            return redirect()->route('profile.edit')->withErrors([
                'authenticator' => 'Your authenticator setup has expired. Please start again.',
            ], 'authenticatorEnable');
        }

        if (! $authenticator->verify($secret, $validated['code'])) {
            return redirect()->route('profile.edit')->withErrors([
                'code' => 'Invalid authenticator code. Please try again.',
            ], 'authenticatorEnable');
        }

        $user->forceFill([
            'authenticator_secret' => $secret,
            'authenticator_enabled_at' => now(),
        ])->save();
        $setup->clear($request);
        MfaSession::mark($request, $user, $guard);
        $request->session()->put('authenticator_success', 'Authenticator app enabled successfully.');

        return redirect()->route('profile.edit');
    }

    public function cancel(Request $request, AuthenticatorSetupService $setup): RedirectResponse
    {
        $setup->clear($request);

        return redirect()->route('profile.edit');
    }

    public function disable(
        Request $request,
        AuthenticatorService $authenticator,
        AuthenticatorSetupService $setup,
    ): RedirectResponse {
        $guard = AuthenticationContext::authenticatedGuard() ?? AuthenticationContext::WEB_GUARD;
        $user = $request->user($guard);

        abort_unless($user instanceof User, 401);

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'current_password:'.$guard],
            'code' => ['required', 'digits:6'],
        ], [
            'current_password.required' => 'Current password is required.',
            'current_password.current_password' => 'Current password is incorrect.',
            'code.required' => 'Enter the 6-digit code from your authenticator app.',
            'code.digits' => 'Enter a valid 6-digit authenticator code.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('profile.edit')
                ->withErrors($validator, 'authenticatorDisable');
        }

        $validated = $validator->validated();

        $secret = $user->authenticator_secret;

        if (! $user->authenticatorMfaEnabled() || ! is_string($secret)) {
            return redirect()->route('profile.edit')->withErrors([
                'authenticator' => 'Authenticator app is not enabled.',
            ], 'authenticatorDisable');
        }

        if (! $authenticator->verify($secret, $validated['code'])) {
            return redirect()->route('profile.edit')->withErrors([
                'code' => 'Invalid authenticator code. Please try again.',
            ], 'authenticatorDisable');
        }

        $user->forceFill([
            'authenticator_secret' => null,
            'authenticator_enabled_at' => null,
        ])->save();
        $setup->clear($request);
        $request->session()->put('authenticator_success', 'Authenticator app disabled successfully.');

        return redirect()->route('profile.edit');
    }
}
