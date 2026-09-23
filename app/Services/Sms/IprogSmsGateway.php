<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use Throwable;

class IprogSmsGateway implements SmsGateway
{
    public function available(): bool
    {
        return filled(config('services.sms.api_token'));
    }

    public function send(string $mobileNumber, #[\SensitiveParameter] string $message): bool
    {
        if (! $this->available()) {
            return false;
        }

        $token = config('services.sms.api_token');

        try {
            $response = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(8)
                ->post('https://www.iprogsms.com/api/v1/sms_messages', [
                    'api_token' => $token,
                    'phone_number' => '63'.substr($mobileNumber, 1),
                    'message' => $message,
                ]);

            return $response->successful()
                && $response->json('status') === 200
                && is_string($response->json('message_id'))
                && $response->json('message_id') !== '';
        } catch (Throwable) {
            return false;
        }
    }
}
