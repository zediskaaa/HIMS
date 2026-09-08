<?php

namespace App\Http\Controllers;

use App\Enums\AuthenticatorSecretStatus;
use App\Exceptions\InvalidAuthenticatorSecretException;
use App\Models\User;
use App\Services\AuthenticatorSecretService;
use App\Services\AuthenticatorService;
use App\Services\AuthenticatorSetupService;
use App\Support\AuthenticationContext;
use App\Support\MfaSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthenticatorController extends Controller
{
    public function setup(
        Request $request,
        AuthenticatorSetupService $setup,
        AuthenticatorSecretService $authenticatorSecrets,
    ): RedirectResponse {
        $guard = AuthenticationContext::authenticatedGuard() ?? AuthenticationContext::WEB_GUARD;
        $user = $request->user($guard);

        abort_unless($user instanceof User, 401);

        if ($user->authenticatorMfaEnabled()
            && $authenticatorSecrets->status($user) !== AuthenticatorSecretStatus::Invalid) {
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

    /**
     * JSON variant of setup() — returns QR data so the frontend can open a
     * modal without a full-page redirect.
     */
    public function setupJson(
        Request $request,
        AuthenticatorSetupService $setup,
        AuthenticatorSecretService $authenticatorSecrets,
    ): JsonResponse {
        $guard = AuthenticationContext::authenticatedGuard() ?? AuthenticationContext::WEB_GUARD;
        $user = $request->user($guard);

        abort_unless($user instanceof User, 401);

        if ($user->authenticatorMfaEnabled()
            && $authenticatorSecrets->status($user) !== AuthenticatorSecretStatus::Invalid) {
            return response()->json(['errors' => ['authenticator' => ['Authenticator app is already enabled.']]], 422);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'current_password:'.$guard],
        ], [
            'current_password.required' => 'Current password is required.',
            'current_password.current_password' => 'Current password is incorrect.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $setup->begin($request, $user);
        $details = $setup->details($request, $user);

        if ($details === null) {
            return response()->json(['errors' => ['authenticator' => ['Setup failed. Please try again.']]], 500);
        }

        return response()->json([
            'qr_code' => $details['qr_code'],
            'secret' => $details['secret'],
        ]);
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

    /**
     * JSON variant of enable() — keeps the modal open on errors and returns
     * a success flag so the frontend can close the modal and update the UI.
     */
    public function enableJson(
        Request $request,
        AuthenticatorSetupService $setup,
        AuthenticatorService $authenticator,
    ): JsonResponse {
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
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $secret = $setup->secret($request, $user);

        if ($secret === null) {
            return response()->json(['errors' => ['authenticator' => ['Your authenticator setup has expired. Please start again.']]], 422);
        }

        if (! $authenticator->verify($secret, $validated['code'])) {
            return response()->json(['errors' => ['code' => ['Invalid authenticator code. Please try again.']]], 422);
        }

        $user->forceFill([
            'authenticator_secret' => $secret,
            'authenticator_enabled_at' => now(),
        ])->save();
        $setup->clear($request);
        MfaSession::mark($request, $user, $guard);

        return response()->json(['success' => true, 'message' => 'Authenticator app enabled successfully.']);
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
        AuthenticatorSecretService $authenticatorSecrets,
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

        if (! $user->authenticatorMfaEnabled()) {
            return redirect()->route('profile.edit')->withErrors([
                'authenticator' => 'Authenticator app is not enabled.',
            ], 'authenticatorDisable');
        }

        try {
            $secret = $authenticatorSecrets->decrypt($user);
        } catch (InvalidAuthenticatorSecretException) {
            return redirect()->route('profile.edit')->withErrors([
                'authenticator' => 'Your saved authenticator setup cannot be verified. Reconfigure it before making other authenticator changes.',
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
