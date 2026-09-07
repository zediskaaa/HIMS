<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class AuthenticatorSetupService
{
    public const SESSION_KEY = 'auth.authenticator_setup';

    public function __construct(private readonly AuthenticatorService $authenticator) {}

    public function begin(Request $request, User $user): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->getKey(),
            'secret' => Crypt::encryptString($this->authenticator->generateSecret()),
            'expires_at' => now()->addMinutes($this->expiresInMinutes())->getTimestamp(),
        ]);
    }

    /** @return array{secret: string, provisioning_uri: string, qr_code: string}|null */
    public function details(Request $request, User $user): ?array
    {
        $secret = $this->secret($request, $user);

        if ($secret === null) {
            return null;
        }

        $uri = $this->authenticator->provisioningUri($user, $secret);

        return [
            'secret' => $secret,
            'provisioning_uri' => $uri,
            'qr_code' => $this->authenticator->qrCodeDataUri($uri),
        ];
    }

    public function secret(Request $request, User $user): ?string
    {
        $state = $request->session()->get(self::SESSION_KEY);

        if (! is_array($state)
            || (int) ($state['user_id'] ?? 0) !== (int) $user->getKey()
            || ! is_string($state['secret'] ?? null)
            || ! is_numeric($state['expires_at'] ?? null)
            || (int) $state['expires_at'] <= now()->getTimestamp()) {
            $this->clear($request);

            return null;
        }

        try {
            return Crypt::decryptString($state['secret']);
        } catch (DecryptException) {
            $this->clear($request);

            return null;
        }
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    private function expiresInMinutes(): int
    {
        return max(1, min(
            (int) config('auth.authenticator.setup_expire', 10),
            (int) config('session.lifetime', 120),
        ));
    }
}
