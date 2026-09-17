<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SuperAdminPasswordConfirmation
{
    public const SESSION_KEY = 'super_admin_confirmation';

    public const TOKEN_TTL_MINUTES = 5;

    /**
     * Issue a single-use confirmation token stored in the session.
     */
    public static function issueToken(Request $request, User $actor): string
    {
        $token = Str::random(40);

        $request->session()->put(self::SESSION_KEY, [
            'token' => $token,
            'user_id' => $actor->getKey(),
            'expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES)->timestamp,
        ]);

        return $token;
    }

    /**
     * Verify whether the request includes either a valid current_password or
     * a valid single-use session confirmation token for the Super Admin actor.
     *
     * @throws ValidationException
     */
    public static function validate(Request $request, User $actor, string $errorKey = 'current_password'): void
    {
        if ($request->filled('current_password')) {
            if (! Hash::check($request->string('current_password')->toString(), $actor->password)) {
                throw ValidationException::withMessages([
                    $errorKey => ['Current password is incorrect.'],
                ]);
            }

            return;
        }

        $token = $request->input('super_admin_confirmation_token');
        $sessionData = $request->session()->get(self::SESSION_KEY);

        $isValidToken = is_array($sessionData)
            && isset($sessionData['token'], $sessionData['user_id'], $sessionData['expires_at'])
            && is_string($token)
            && hash_equals($sessionData['token'], $token)
            && $sessionData['user_id'] === $actor->getKey()
            && $sessionData['expires_at'] >= now()->timestamp;

        if ($isValidToken) {
            // Burn the single-use token immediately to prevent replay.
            $request->session()->forget(self::SESSION_KEY);

            return;
        }

        throw ValidationException::withMessages([
            $errorKey => [$token ? 'Current password confirmation has expired or is invalid.' : 'Current password is required.'],
        ]);
    }
}
