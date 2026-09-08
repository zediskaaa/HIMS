<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PasswordStandard implements ValidationRule
{
    public const REQUIREMENTS = 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.';

    public const CONFIRMATION_MESSAGE = 'Passwords do not match.';

    /**
     * @param  Closure(string, ?string=): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (mb_strlen($value) < 8) {
            $fail('Password must be at least 8 characters long.');
        }

        if (! preg_match('/[A-Z]/', $value)) {
            $fail('Password must contain at least one uppercase letter.');
        }

        if (! preg_match('/[a-z]/', $value)) {
            $fail('Password must contain at least one lowercase letter.');
        }

        if (! preg_match('/[0-9]/', $value)) {
            $fail('Password must contain at least one number.');
        }

        if (! preg_match('/[^A-Za-z0-9\s]/', $value)) {
            $fail('Password must contain at least one special character.');
        }
    }
}
