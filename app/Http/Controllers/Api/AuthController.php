<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\AuthenticationPanel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function token(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        $credentials = [
            ...$request->only('email', 'password'),
            fn (Builder $query) => $query->where('role', '!=', UserRole::SuperAdministrator->value),
        ];

        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->passwordHasExpired()) {
            Auth::logout();
            $loginUrl = route(AuthenticationPanel::forRole($user->role)->loginRoute());

            return response()->json([
                'message' => 'Your password has expired. Sign in through the HIMS login page to create a new password.',
                'code' => 'PASSWORD_EXPIRED',
                'redirect' => $loginUrl,
            ], 428)->header('X-HIMS-Password-Expired', $loginUrl);
        }

        $device = $request->input('device_name', 'api-token');
        $token = $user->createToken($device)->plainTextToken;

        return response()->json(['token' => $token]);
    }

    public function logout(Request $request, AuditLogger $audit)
    {
        /** @var User $user */
        $user = $request->user();

        // Token revocation does not dispatch Laravel's web Logout event.
        $audit->log(
            AuditAction::LoggedOut,
            $user,
            "{$user->name} logged out.",
            $user,
            'Account',
        );

        // Revoke current token
        $user->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
