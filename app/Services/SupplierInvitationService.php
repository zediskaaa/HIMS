<?php

namespace App\Services;

use App\Enums\ActivationCancellationReason;
use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\SupplierInvitation;
use App\Models\User;
use App\Notifications\SupplierInvitationNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SupplierInvitationService
{
    public function __construct(
        private readonly UserAccountService $accounts,
        private readonly AccountActivationService $activation,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Create the pending Vendor Administrator account and invitation record.
     * Delivery happens separately so a mail failure leaves a recoverable state.
     *
     * @param  array{first_name: string, surname: string, email: string, phone?: ?string, role?: string}  $data
     * @return array{invitation: SupplierInvitation, token: string}
     */
    public function prepare(Supplier $supplier, array $data, User $actor): array
    {
        if (! $actor->can(Permission::ApproveSuppliers->value)) {
            throw new AuthorizationException('Only supplier approvers may invite supplier administrators.');
        }

        if ($supplier->status !== SupplierStatus::Active) {
            throw ValidationException::withMessages([
                'name' => 'Only active supplier records may receive portal invitations.',
            ]);
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])->exists()) {
            throw ValidationException::withMessages([
                'invitation_email' => 'This email address already belongs to a HIMS account or outstanding invitation.',
            ]);
        }

        return DB::transaction(function () use ($supplier, $data, $actor): array {
            $role = UserRole::tryFrom($data['role'] ?? '') ?? UserRole::VendorAdministrator;
            if (! $role->isSupplier()) {
                throw ValidationException::withMessages(['role' => 'Select a valid supplier portal role.']);
            }
            $user = $this->accounts->create([
                'first_name' => $data['first_name'],
                'surname' => $data['surname'],
                'email' => mb_strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'role' => $role->value,
                'supplier_id' => $supplier->id,
                'department' => 'External Supplier',
            ], $actor, sendNotifications: false);

            $token = Str::random(64);
            $now = now();
            $invitation = SupplierInvitation::create([
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
                'invited_by' => $actor->id,
                'email' => $user->email,
                'token_hash' => hash('sha256', $token),
                'status' => SupplierInvitation::STATUS_PENDING,
                'delivery_status' => SupplierInvitation::DELIVERY_PENDING,
                'invited_at' => $now,
                'expires_at' => $now->copy()->addMinutes($this->expiresInMinutes()),
            ]);

            return ['invitation' => $invitation, 'token' => $token];
        }, 3);
    }

    public function deliver(
        SupplierInvitation $invitation,
        #[\SensitiveParameter] string $token,
        User $actor,
        bool $resend = false,
    ): bool {
        $invitation->loadMissing(['supplier', 'user']);

        try {
            Notification::send($invitation->user, new SupplierInvitationNotification($invitation, $token));
        } catch (Throwable) {
            $invitation->forceFill([
                'delivery_status' => SupplierInvitation::DELIVERY_FAILED,
                'delivery_failed_at' => now(),
                'delivery_attempts' => $invitation->delivery_attempts + 1,
            ])->save();
            $this->audit->log(
                AuditAction::FailedSupplierInvitation,
                $actor,
                'The supplier invitation email could not be delivered.',
                $invitation,
                $invitation->supplier->name,
                newValues: ['supplier_id' => $invitation->supplier_id, 'delivery_status' => SupplierInvitation::DELIVERY_FAILED],
                outcome: 'failure',
            );

            return false;
        }

        $invitation->forceFill([
            'delivery_status' => SupplierInvitation::DELIVERY_SENT,
            'sent_at' => now(),
            'delivery_failed_at' => null,
            'delivery_attempts' => $invitation->delivery_attempts + 1,
        ])->save();
        $this->audit->log(
            AuditAction::SentSupplierInvitation,
            $actor,
            $resend ? 'Resent the supplier registration invitation.' : 'Sent the supplier registration invitation.',
            $invitation,
            $invitation->supplier->name,
            newValues: [
                'supplier_id' => $invitation->supplier_id,
                'delivery_status' => SupplierInvitation::DELIVERY_SENT,
                'resend' => $resend,
            ],
        );

        return true;
    }

    /** @return array{invitation: SupplierInvitation, delivered: bool} */
    public function invite(Supplier $supplier, array $data, User $actor): array
    {
        $prepared = $this->prepare($supplier, $data, $actor);

        return [
            'invitation' => $prepared['invitation'],
            'delivered' => $this->deliver($prepared['invitation'], $prepared['token'], $actor),
        ];
    }

    public function resend(SupplierInvitation $invitation, User $actor): bool
    {
        if (! $actor->can(Permission::ApproveSuppliers->value)) {
            throw new AuthorizationException('Only supplier approvers may resend supplier invitations.');
        }

        if (! $invitation->canResend()) {
            throw ValidationException::withMessages([
                'invitation' => 'Only pending, expired, failed, or revoked supplier invitations can be sent again.',
            ]);
        }

        $token = Str::random(64);
        DB::transaction(function () use ($invitation, $actor, $token): void {
            $invitation = SupplierInvitation::query()
                ->with('user')
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invitation->status === SupplierInvitation::STATUS_REVOKED) {
                $this->accounts->toggleStatus($invitation->user, $actor);
            }

            $invitation->forceFill([
                'token_hash' => hash('sha256', $token),
                'status' => SupplierInvitation::STATUS_PENDING,
                'delivery_status' => SupplierInvitation::DELIVERY_PENDING,
                'delivery_failed_at' => null,
                'opened_at' => null,
                'accepted_at' => null,
                'revoked_at' => null,
                'revoked_by' => null,
                'expires_at' => now()->addMinutes($this->expiresInMinutes()),
            ])->save();
        }, 3);

        $invitation->refresh();

        return $this->deliver($invitation, $token, $actor, resend: true);
    }

    public function revoke(SupplierInvitation $invitation, User $actor): SupplierInvitation
    {
        if (! $actor->can(Permission::ApproveSuppliers->value)) {
            throw new AuthorizationException('Only supplier approvers may revoke supplier invitations.');
        }

        if (! $invitation->canRevoke()) {
            throw ValidationException::withMessages([
                'invitation' => 'Only pending supplier invitations can be revoked.',
            ]);
        }

        return DB::transaction(function () use ($invitation, $actor): SupplierInvitation {
            $invitation = SupplierInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            $this->accounts->cancelInvitation(
                $invitation->user,
                $actor,
                ActivationCancellationReason::RequestWithdrawn,
                'Supplier onboarding invitation revoked from the supplier record.',
            );
            $invitation->forceFill([
                'status' => SupplierInvitation::STATUS_REVOKED,
                'revoked_at' => now(),
                'revoked_by' => $actor->id,
            ])->save();

            return $invitation;
        }, 3);
    }

    public function acceptLink(#[\SensitiveParameter] string $token): ?User
    {
        return DB::transaction(function () use ($token): ?User {
            $invitation = SupplierInvitation::query()
                ->with('user')
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($invitation === null
                || $invitation->status !== SupplierInvitation::STATUS_PENDING
                || $invitation->expires_at->isPast()
                || ! $invitation->user?->isPendingActivation()
                || $invitation->user->supplier_id !== $invitation->supplier_id
                || ! $invitation->user->role->isSupplier()
                || ! hash_equals(mb_strtolower($invitation->email), mb_strtolower($invitation->user->email))) {
                return null;
            }

            $user = $this->activation->verifyEmailLink($invitation->user);
            if ($user === null) {
                return null;
            }

            $invitation->forceFill(['opened_at' => $invitation->opened_at ?? now()])->save();

            return $user;
        }, 3);
    }

    private function expiresInMinutes(): int
    {
        return max(1, (int) config('auth.verification.expire', 60));
    }
}
