<?php

namespace App\Http\Controllers;

use App\Enums\AuthenticatorSecretStatus;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\AuthenticatorSecretService;
use App\Services\AuthenticatorSetupService;
use App\Support\AuditBrowserLocation;
use App\Support\AuthenticationContext;
use App\Support\MfaSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function updateSessionTimeoutReminder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'session_timeout_reminder_enabled' => ['required', 'boolean'],
        ]);

        $enabled = (bool) $validated['session_timeout_reminder_enabled'];
        $request->user()->forceFill([
            'session_timeout_reminder_enabled' => $enabled,
        ])->save();

        $request->session()->put(
            'session_reminder_success',
            $enabled
                ? 'Session timeout reminders are now ON.'
                : 'Session timeout reminders are now OFF. Automatic logout remains active.',
        );

        return Redirect::route('profile.edit');
    }

    public function storeAuditLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);

        AuditBrowserLocation::store($request, $validated);

        return response()->json(['stored' => true]);
    }

    /**
     * Update the user's profile picture.
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'avatar' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png',
                'mimetypes:image/jpeg,image/png',
                'max:3072', // 3 MB max
            ],
        ], [
            'avatar.required' => 'Please select an image file to upload.',
            'avatar.file' => 'The uploaded file is not valid.',
            'avatar.mimes' => 'The profile picture must be a file of type: JPG, JPEG, PNG.',
            'avatar.mimetypes' => 'The profile picture must be a file of type: JPG, JPEG, PNG.',
            'avatar.max' => 'The profile picture must not exceed 3 MB.',
        ]);

        $validator->after(function ($validator) use ($request) {
            $file = $request->file('avatar');
            if (! $file || ! $file->isValid()) {
                return;
            }

            // Image integrity check: verify decodable image headers and dimensions
            $imageInfo = @getimagesize($file->getRealPath());
            if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
                $validator->errors()->add('avatar', 'The uploaded file is corrupted or not a valid image.');
                return;
            }

            if (! in_array($imageInfo['mime'], ['image/jpeg', 'image/png'], true)) {
                $validator->errors()->add('avatar', 'The uploaded image must be a valid JPG, JPEG, or PNG format.');
                return;
            }

            if ($imageInfo[0] > 4096 || $imageInfo[1] > 4096) {
                $validator->errors()->add('avatar', 'The image dimensions cannot exceed 4096x4096 pixels.');
                return;
            }
        });

        if ($validator->fails()) {
            return Redirect::route('profile.edit')
                ->withErrors($validator)
                ->withInput();
        }

        $user = $request->user();
        $file = $request->file('avatar');

        // Delete previous avatar file from storage disk if exists
        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        // Store new image securely using hashed filename on public disk
        $path = $file->store('avatars', 'public');
        $user->forceFill(['avatar_path' => $path])->save();

        $request->session()->put('avatar_success', 'Profile picture updated successfully.');

        return Redirect::route('profile.edit');
    }

    /**
     * Remove the user's profile picture.
     */
    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->forceFill(['avatar_path' => null])->save();

        $request->session()->put('avatar_success', 'Profile picture removed. Your initials avatar is now active.');

        return Redirect::route('profile.edit');
    }

    /**
     * Safely stream the user's profile picture.
     */
    public function showAvatar(User $user): BinaryFileResponse
    {
        abort_unless($user->avatar_path && Storage::disk('public')->exists($user->avatar_path), 404);

        $path = Storage::disk('public')->path($user->avatar_path);
        $mime = Storage::disk('public')->mimeType($user->avatar_path) ?? 'image/jpeg';

        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
