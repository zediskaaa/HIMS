<?php

namespace App\Services;

use App\Enums\AuthenticatorSecretStatus;
use App\Exceptions\InvalidAuthenticatorSecretException;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;

class AuthenticatorSecretService
{
    public function status(User $user): AuthenticatorSecretStatus
    {
        if (! $user->authenticatorMfaEnabled()) {
            return AuthenticatorSecretStatus::Missing;
        }

        try {
            $this->decrypt($user);
        } catch (InvalidAuthenticatorSecretException) {
            return AuthenticatorSecretStatus::Invalid;
        }

        return AuthenticatorSecretStatus::Valid;
    }

    /**
     * Resolve the model's encrypted cast at a deliberate integrity boundary.
     * Callers must treat an invalid value as an MFA recovery condition, never
     * as MFA being disabled.
     */
    public function decrypt(User $user): string
    {
        try {
            $secret = $user->authenticator_secret;
        } catch (DecryptException $exception) {
            throw new InvalidAuthenticatorSecretException(
                'The stored authenticator secret failed its encryption integrity check.',
                previous: $exception,
            );
        }

        if (! is_string($secret) || preg_match('/^[A-Z2-7]{16,128}$/D', $secret) !== 1) {
            throw new InvalidAuthenticatorSecretException(
                'The stored authenticator secret is not a valid TOTP seed.',
            );
        }

        return $secret;
    }

    public function fingerprint(User $user): ?string
    {
        $ciphertext = $user->getRawOriginal('authenticator_secret');

        return is_string($ciphertext) && $ciphertext !== ''
            ? hash('sha256', $ciphertext)
            : null;
    }
}
