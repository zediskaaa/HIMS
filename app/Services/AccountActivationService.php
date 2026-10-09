<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\AccountActivationChallenge;
use App\Models\SupplierInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccountActivationService
{
    public const CANCELLED_MESSAGE = 'This account activation request has been cancelled. Please contact your administrator if you believe this is an error.';

    public const SENT = 'sent';

    public const COOLDOWN = 'cooldown';

    public const INVALID = 'invalid';

    public const EXPIRED = 'expired';

    public const TOO_MANY_ATTEMPTS = 'too_many_attempts';

    public const VERIFIED = 'verified';

    public function __construct(private readonly PasswordHistoryService $passwords) {}

    public function eligibleAccount(string $email, string $phone): ?User
    {
        return User::query()
            ->where('email', mb_strtolower(trim($email)))
            ->wherePhoneNumber($phone)
            ->where('status', UserStatus::PendingActivation->value)
            ->whereNull('password')
            ->first();
    }

    /** @return array{status: string, otp?: string, retry_after?: int, resent?: bool} */
    public function issue(User $user, string $channel): array
    {
        return DB::transaction(function () use ($user, $channel): array {
            $lockedUser = User::query()->lockForUpdate()->find($user->getKey());

            if (! $lockedUser?->isPendingActivation() || filled($lockedUser->getAuthPassword())) {
                return ['status' => self::INVALID];
            }

            $challenge = AccountActivationChallenge::query()
                ->where('user_id', $lockedUser->getKey())
                ->lockForUpdate()
                ->first();

            if ($challenge?->resend_available_at?->isFuture()) {
                return [
                    'status' => self::COOLDOWN,
                    'retry_after' => max(1, now()->diffInSeconds($challenge->resend_available_at, false)),
                ];
            }

            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $resent = $challenge !== null;

            AccountActivationChallenge::query()->updateOrCreate(
                ['user_id' => $lockedUser->getKey()],
                [
                    'channel' => $channel,
                    'otp_hash' => Hash::make($otp),
                    'expires_at' => now()->addMinutes($this->otpExpiresInMinutes()),
                    'resend_available_at' => now()->addSeconds($this->resendCooldownSeconds()),
                    'failed_attempts' => 0,
                    'verified_at' => null,
                    'consumed_at' => null,
                ],
            );

            return ['status' => self::SENT, 'otp' => $otp, 'resent' => $resent];
        }, 3);
    }

    public function invalidateIfCurrent(User $user, #[\SensitiveParameter] string $otp): void
    {
        DB::transaction(function () use ($user, $otp): void {
            $challenge = AccountActivationChallenge::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($challenge !== null && is_string($challenge->otp_hash) && Hash::check($otp, $challenge->otp_hash)) {
                $challenge->forceFill(['otp_hash' => null, 'expires_at' => null])->save();
            }
        });
    }

    public function verify(User $user, string $channel, #[\SensitiveParameter] string $otp): string
    {
        return DB::transaction(function () use ($user, $channel, $otp): string {
            $lockedUser = User::query()->lockForUpdate()->find($user->getKey());
            $challenge = AccountActivationChallenge::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedUser?->isPendingActivation()
                || filled($lockedUser->getAuthPassword())
                || $challenge === null
                || ! hash_equals((string) $challenge->channel, $channel)
                || ! is_string($challenge->otp_hash)
                || $challenge->verified_at !== null
                || $challenge->consumed_at !== null) {
                return self::INVALID;
            }

            if ($challenge->expires_at?->isPast() !== false) {
                $challenge->forceFill(['otp_hash' => null])->save();

                return self::EXPIRED;
            }

            if ($challenge->failed_attempts >= $this->maxAttempts()) {
                return self::TOO_MANY_ATTEMPTS;
            }

            if (! Hash::check($otp, $challenge->otp_hash)) {
                $challenge->increment('failed_attempts');

                if ($challenge->failed_attempts >= $this->maxAttempts()) {
                    $challenge->forceFill(['otp_hash' => null])->save();

                    return self::TOO_MANY_ATTEMPTS;
                }

                return self::INVALID;
            }

            $challenge->forceFill([
                'otp_hash' => null,
                'verified_at' => now(),
            ])->save();

            // This existing field remains the authentication gate. For SMS,
            // it records successful registered-contact verification.
            $lockedUser->forceFill(['email_verified_at' => now()])->saveQuietly();

            return self::VERIFIED;
        }, 3);
    }

    public function canSetPassword(User $user): bool
    {
        return $user->isPendingActivation()
            && blank($user->getAuthPassword())
            && AccountActivationChallenge::query()
                ->where('user_id', $user->getKey())
                ->whereNotNull('verified_at')
                ->whereNull('consumed_at')
                ->where('verified_at', '>=', now()->subMinutes($this->setupExpiresInMinutes()))
                ->exists();
    }

    public function verifyEmailLink(User $user): ?User
    {
        return DB::transaction(function () use ($user): ?User {
            $lockedUser = User::query()->lockForUpdate()->find($user->getKey());

            if (! $lockedUser?->isPendingActivation() || filled($lockedUser->getAuthPassword())) {
                return null;
            }

            AccountActivationChallenge::query()->updateOrCreate(
                ['user_id' => $lockedUser->getKey()],
                [
                    'channel' => 'email',
                    'otp_hash' => null,
                    'expires_at' => null,
                    'resend_available_at' => null,
                    'failed_attempts' => 0,
                    'verified_at' => now(),
                    'consumed_at' => null,
                ],
            );

            $lockedUser->forceFill(['email_verified_at' => now()])->saveQuietly();

            return $lockedUser;
        }, 3);
    }

    public function complete(User $user, #[\SensitiveParameter] string $password): ?User
    {
        return DB::transaction(function () use ($user, $password): ?User {
            $lockedUser = User::query()->lockForUpdate()->find($user->getKey());
            $challenge = AccountActivationChallenge::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedUser?->isPendingActivation()
                || filled($lockedUser->getAuthPassword())
                || $challenge?->verified_at === null
                || $challenge->consumed_at !== null
                || $challenge->verified_at->lt(now()->subMinutes($this->setupExpiresInMinutes()))) {
                return null;
            }

            $activated = $this->passwords->usePassword(
                $lockedUser,
                $password,
                function (string $passwordHash) use ($lockedUser): User {
                    $lockedUser->forceFill([
                        'password' => $passwordHash,
                        'status' => UserStatus::Active,
                    ])->save();

                    return $lockedUser;
                },
            );

            $challenge->forceFill(['consumed_at' => now()])->save();

            SupplierInvitation::query()
                ->where('user_id', $lockedUser->getKey())
                ->where('status', SupplierInvitation::STATUS_PENDING)
                ->update([
                    'status' => SupplierInvitation::STATUS_ACCEPTED,
                    'accepted_at' => now(),
                    'updated_at' => now(),
                ]);

            return $activated;
        }, 3);
    }

    public function forgetChallenge(User $user): void
    {
        $user->accountActivationChallenge()->delete();
    }

    public function otpExpiresInMinutes(): int
    {
        return max(1, (int) config('auth.account_activation.otp_expire', 5));
    }

    public function resendCooldownSeconds(): int
    {
        return max(1, (int) config('auth.account_activation.resend_cooldown', 60));
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('auth.account_activation.max_attempts', 5));
    }

    private function setupExpiresInMinutes(): int
    {
        return max(1, (int) config('auth.account_activation.setup_expire', 15));
    }

    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).str_repeat('*', max(3, mb_strlen($local) - 1)).'@'.$domain;
    }

    public static function maskPhone(string $phone): string
    {
        return str_repeat('•', max(0, strlen($phone) - 4)).substr($phone, -4);
    }
}
