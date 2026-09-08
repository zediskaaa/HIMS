<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use App\Support\AuthenticationContext;

class AdminLoginRequest extends RoleRestrictedLoginRequest
{
    protected function guard(): string
    {
        return AuthenticationContext::ADMIN_GUARD;
    }

    protected function allowedRoles(): array
    {
        return [UserRole::Administrator->value];
    }

    protected function throttlePrefix(): string
    {
        return 'admin';
    }
}
