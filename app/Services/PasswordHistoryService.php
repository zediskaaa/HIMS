<?php

namespace App\Services;

use App\Models\PasswordHistory;
use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PasswordHistoryService
{
    public const REJECTION_MESSAGE = 'This password cannot be used. Please choose a different password.';

    /**
     * Atomically persist and record a password after checking global history.
     *
     * The callback receives a fresh Laravel hash and must return the saved user.
     * The unique blind fingerprint is the final concurrency guard if two
     * requests pass the history check at the same time.
     *
     * @param  Closure(string): User  $persist
     */
    public function usePassword(string $password, Closure $persist, string $errorBag = 'default'): User
    {
        try {
            return DB::transaction(function () use ($password, $persist, $errorBag): User {
                if ($this->hasBeenUsed($password)) {
                    $this->reject($errorBag);
                }

                $user = $persist(Hash::make($password));

                PasswordHistory::query()->create([
                    'user_id' => $user->getKey(),
                    'password_hash' => $user->getAuthPassword(),
                    'password_fingerprint' => $this->fingerprint($password),
                    'used_at' => now(),
                ]);

                return $user;
            }, 5);
        } catch (QueryException $exception) {
            if ($this->isFingerprintConflict($exception)) {
                $this->reject($errorBag);
            }

            throw $exception;
        }
    }

    public function hasBeenUsed(string $password): bool
    {
        $fingerprint = $this->fingerprint($password);
        $indexed = PasswordHistory::query()
            ->where('password_fingerprint', $fingerprint)
            ->first();

        if ($indexed !== null && Hash::check($password, $indexed->password_hash)) {
            // Verify with Laravel's password hasher rather than trusting hash
            // string equality.
            return true;
        }

        // Rows backfilled at rollout have no fingerprint because their
        // plaintext is unavailable. Stream them in bounded chunks so they are
        // still protected without loading the whole history into memory.
        foreach (PasswordHistory::query()
            ->whereNull('password_fingerprint')
            ->select(['id', 'password_hash'])
            ->lazyById(100) as $history) {
            if (Hash::check($password, $history->password_hash)) {
                return true;
            }
        }

        return false;
    }

    private function fingerprint(string $password): string
    {
        $key = (string) config('auth.password_history.key', config('app.key'));

        if ($key === '') {
            throw new RuntimeException('A password history fingerprint key is not configured.');
        }

        return hash_hmac(
            'sha256',
            $password,
            $key,
        );
    }

    private function reject(string $errorBag): never
    {
        $exception = ValidationException::withMessages([
            'password' => [self::REJECTION_MESSAGE],
        ]);
        $exception->errorBag = $errorBag;

        throw $exception;
    }

    private function isFingerprintConflict(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'password_histories_password_fingerprint_unique')
            || str_contains($message, 'password_histories.password_fingerprint');
    }
}
