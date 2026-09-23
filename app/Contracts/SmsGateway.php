<?php

namespace App\Contracts;

interface SmsGateway
{
    public function available(): bool;

    public function send(string $mobileNumber, #[\SensitiveParameter] string $message): bool;
}
