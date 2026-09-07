<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

class AuthenticatorService
{
    public function __construct(private readonly Google2FA $totp) {}

    public function generateSecret(): string
    {
        return $this->totp->generateSecretKey(32);
    }

    public function provisioningUri(User $user, #[\SensitiveParameter] string $secret): string
    {
        return $this->totp->getQRCodeUrl(
            $this->issuer(),
            $user->email,
            $secret,
        );
    }

    public function qrCodeDataUri(string $provisioningUri): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(280, 4),
            new SvgImageBackEnd,
        );
        $svg = (new Writer($renderer))->writeString($provisioningUri);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function verify(#[\SensitiveParameter] string $secret, #[\SensitiveParameter] string $code): bool
    {
        if (! preg_match('/^\d{6}$/D', $code)) {
            return false;
        }

        return $this->totp->verifyKey(
            $secret,
            $code,
            max(0, (int) config('auth.authenticator.window', 1)),
        );
    }

    private function issuer(): string
    {
        return trim((string) config('auth.authenticator.issuer', config('app.name', 'HIMS'))) ?: 'HIMS';
    }
}
