<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
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

        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        /** @var User $user */
        $user = Auth::user();

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
