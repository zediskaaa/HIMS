<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use App\Support\AuthenticationContext;

class SuperAdminLoginRequest extends RoleRestrictedLoginRequest
{
    protected function guard(): string
    {
        return AuthenticationContext::SUPER_ADMIN_GUARD;
    }

    protected function allowedRoles(): array
    {
        return [UserRole::SuperAdministrator->value];
    }

    protected function throttlePrefix(): string
    {
        return 'super-admin';
    }
}
