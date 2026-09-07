<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

final class MfaSession
{
    public static function key(string $guard): string
    {
        return "auth.{$guard}.mfa_completed_user_id";
    }

    public static function mark(Request $request, User $user, string $guard): void
    {
        $request->session()->put(self::key($guard), (int) $user->getKey());
    }

    public static function completed(Request $request, User $user, string $guard): bool
    {
        return (int) $request->session()->get(self::key($guard)) === (int) $user->getKey();
    }
}
