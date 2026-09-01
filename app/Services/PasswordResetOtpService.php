<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class PasswordResetOtpService
{
    public function issue(User $user): string
    {
        return DB::transaction(function () use ($user): string {
            $email = $user->getEmailForPasswordReset();
            $existingHash = DB::table($this->table())
                ->where('email', $email)
                ->lockForUpdate()
                ->value('token');

            do {
                $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            } while (is_string($existingHash) && Hash::check($otp, $existingHash));

            DB::table($this->table())->updateOrInsert(
                ['email' => $email],
                ['token' => Hash::make($otp), 'created_at' => now()],
            );

            return $otp;
        });
    }

    public function exchangeForResetToken(User $user, #[\SensitiveParameter] string $otp): ?string
    {
        return DB::transaction(function () use ($user, $otp): ?string {
            $record = DB::table($this->table())
                ->where('email', $user->getEmailForPasswordReset())
                ->lockForUpdate()
                ->first(['token', 'created_at']);

            if ($record === null) {
                return null;
            }

            if ($this->isExpired($record->created_at)) {
                DB::table($this->table())
                    ->where('email', $user->getEmailForPasswordReset())
                    ->delete();

                return null;
            }

            if (! Hash::check($otp, $record->token)) {
                return null;
            }

            // Replacing the short OTP with Laravel's high-entropy broker token
            // makes the OTP one-time and lets the existing reset safeguards run.
            return Password::broker()->createToken($user);
        });
    }

    public function deleteIfCurrent(User $user, #[\SensitiveParameter] string $otp): void
    {
        DB::transaction(function () use ($user, $otp): void {
            $query = DB::table($this->table())
                ->where('email', $user->getEmailForPasswordReset());
            $tokenHash = $query->lockForUpdate()->value('token');

            if (is_string($tokenHash) && Hash::check($otp, $tokenHash)) {
                $query->delete();
            }
        });
    }

    public function expiresInMinutes(): int
    {
        return (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.otp_expire',
            5,
        );
    }

    private function isExpired(string $createdAt): bool
    {
        return CarbonImmutable::parse($createdAt)
            ->addMinutes($this->expiresInMinutes())
            ->isPast();
    }

    private function table(): string
    {
        return (string) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.table',
            'password_reset_tokens',
        );
    }
}
