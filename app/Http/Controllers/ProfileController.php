<?php

namespace App\Http\Controllers;

use App\Enums\AuthenticatorSecretStatus;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\AuthenticatorSecretService;
use App\Services\AuthenticatorSetupService;
use App\Support\AuthenticationContext;
use App\Support\MfaSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(
        Request $request,
        AuthenticatorSetupService $setup,
        AuthenticatorSecretService $authenticatorSecrets,
    ): View {
        $user = $request->user();
        $authenticatorStatus = $user instanceof User
            ? $authenticatorSecrets->status($user)
            : AuthenticatorSecretStatus::Missing;
        $recoveryRequired = $authenticatorStatus === AuthenticatorSecretStatus::Invalid;

        return view('profile.edit', [
            'user' => $user,
            'authenticatorRecoveryRequired' => $recoveryRequired,
            'authenticatorSetup' => $user instanceof User
                && (! $user->authenticatorMfaEnabled() || $recoveryRequired)
                ? $setup->details($request, $user)
                : null,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->safe()->only([
            'surname',
            'first_name',
            'middle_name',
            'email',
        ]));

        $emailChanged = $request->user()->isDirty('email');

        if ($emailChanged) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();
        $request->session()->put(
            'profile_success',
            $emailChanged ? 'Email updated successfully.' : 'Profile updated successfully.'
        );

        return Redirect::route('profile.edit');
    }

    public function updateMfa(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isAdministrator(), 403);

        $validated = $request->validate([
            'mfa_enabled' => ['required', 'boolean'],
        ]);

        $enabled = (bool) $validated['mfa_enabled'];
        $user->forceFill(['mfa_enabled' => $enabled])->save();
        if ($enabled) {
            $guard = AuthenticationContext::authenticatedGuard() ?? AuthenticationContext::WEB_GUARD;
            MfaSession::mark($request, $user, $guard);
        }
        $request->session()->put(
            'mfa_success',
            $enabled
                ? 'Multi-factor authentication is now ON. A code will be required on your next login.'
                : 'Multi-factor authentication is now OFF. Future logins will use your password only.',
        );

        return Redirect::route('profile.edit');
    }
}
