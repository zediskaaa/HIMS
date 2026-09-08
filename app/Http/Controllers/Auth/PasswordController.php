<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\PasswordStandard;
use App\Services\PasswordHistoryService;
use App\Support\AuthenticationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request, PasswordHistoryService $passwords): RedirectResponse
    {
        $guard = AuthenticationContext::authenticatedGuard() ?? AuthenticationContext::WEB_GUARD;
        $user = $request->user($guard);

        abort_unless($user instanceof User, 401);

        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password:'.$guard],
            'password' => [
                'required',
                new PasswordStandard,
                'confirmed',
            ],
        ], [
            'current_password.required' => 'Current password is required.',
            'current_password.current_password' => 'Current password is incorrect.',
            'password.confirmed' => PasswordStandard::CONFIRMATION_MESSAGE,
        ]);

        $passwords->usePassword(
            $validated['password'],
            function (string $passwordHash) use ($user): User {
                $user->forceFill(['password' => $passwordHash])->save();

                return $user;
            },
            'updatePassword',
        );

        $request->session()->put('password_success', 'Password updated successfully.');

        return back();
    }
}
