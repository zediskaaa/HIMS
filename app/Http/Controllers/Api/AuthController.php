<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LoginLockoutService;
use App\Support\AuthenticationPanel;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function token(Request $request, LoginLockoutService $lockouts)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        $email = $request->string('email')->toString();
        $throttleKey = $lockouts->throttleKey('api', $email, $request->ip());
        $allowedRoles = collect(UserRole::cases())
            ->reject(fn (UserRole $role) => $role->isSuperAdministrator())
            ->map->value
            ->all();
        $result = $lockouts->attempt(
            $email,
            $request->string('password')->toString(),
            $allowedRoles,
        );

        if ($result['status'] === LoginLockoutService::INVALID) {
            return response()->json([
                'message' => $lockouts->message($result),
                'attempts_remaining' => $lockouts->attemptsRemaining($result),
            ], 401);
        }

        if (in_array($result['status'], [LoginLockoutService::WAITING, LoginLockoutService::LOCKED], true)) {
            return response()->json([
                'message' => $lockouts->message($result),
                'code' => $result['status'] === LoginLockoutService::LOCKED
                    ? 'LOGIN_LOCKED'
                    : 'LOGIN_THROTTLED',
                'retry_after' => $result['seconds'],
                'retry_at' => $result['expires_at'],
                'attempts_remaining' => $lockouts->attemptsRemaining($result),
            ], 429)->header('Retry-After', (string) $result['seconds']);
        }

        /** @var User $user */
        $user = $result['user'];

        if ($user->mfa_enabled) {
            $loginUrl = route(AuthenticationPanel::forRole($user->role)->loginRoute());

            return response()->json([
                'message' => 'Multi-factor authentication is required. Sign in through the HIMS login page to verify your account.',
                'code' => 'MFA_REQUIRED',
                'redirect' => $loginUrl,
            ], 428);
        }

        if ($user->passwordHasExpired()) {
            $loginUrl = route(AuthenticationPanel::forRole($user->role)->loginRoute());

            return response()->json([
                'message' => 'Your password has expired. Sign in through the HIMS login page to create a new password.',
                'code' => 'PASSWORD_EXPIRED',
                'redirect' => $loginUrl,
            ], 428)->header('X-HIMS-Password-Expired', $loginUrl);
        }

        if ($restriction = $lockouts->completeSuccessfulLogin($user, $throttleKey)) {
            return response()->json([
                'message' => $lockouts->message($restriction),
                'code' => $restriction['status'] === LoginLockoutService::LOCKED
                    ? 'LOGIN_LOCKED'
                    : 'LOGIN_THROTTLED',
                'retry_after' => $restriction['seconds'],
                'retry_at' => $restriction['expires_at'],
                'attempts_remaining' => $lockouts->attemptsRemaining($restriction),
            ], 429)->header('Retry-After', (string) $restriction['seconds']);
        }

        event(new Login('sanctum', $user, false));

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
