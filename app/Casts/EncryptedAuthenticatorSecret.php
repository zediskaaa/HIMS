<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

/** @implements CastsAttributes<string|null, string|null> */
class EncryptedAuthenticatorSecret implements CastsAttributes, ComparesCastableAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('The encrypted authenticator secret must be a string.');
        }

        return Crypt::decryptString($value);
    }

    public function set(
        Model $model,
        string $key,
        #[\SensitiveParameter] mixed $value,
        array $attributes,
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('The authenticator secret must be a string.');
        }

        return Crypt::encryptString($value);
    }

    public function compare(
        Model $model,
        string $key,
        mixed $firstValue,
        mixed $secondValue,
    ): bool {
        return is_string($firstValue) && is_string($secondValue)
            ? hash_equals($firstValue, $secondValue)
            : $firstValue === $secondValue;
    }
}
