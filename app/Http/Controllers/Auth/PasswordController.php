<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\NotCurrentPassword;
use App\Support\AuthenticationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $guard = AuthenticationContext::authenticatedGuard() ?? AuthenticationContext::WEB_GUARD;

        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password:'.$guard],
            'password' => [
                'required',
                Password::defaults(),
                'confirmed',
                new NotCurrentPassword($request->user()),
            ],
        ], [
            'current_password.required' => 'Current password is required.',
            'current_password.current_password' => 'Current password is incorrect.',
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $request->session()->put('password_success', 'Password updated successfully.');

        return back();
    }
}
