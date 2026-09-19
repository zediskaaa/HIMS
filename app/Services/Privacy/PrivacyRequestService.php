<?php

namespace App\Services\Privacy;

use App\Enums\AuditAction;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PrivacyRequestService
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Submit a new Data Subject Request.
     *
     * @param  array{request_type: string, details: string, requestor_name?: string, requestor_email?: string}  $data
     */
    public function submit(?User $requestor, array $data): PrivacyRequest
    {
        return DB::transaction(function () use ($requestor, $data) {
            $ticketNumber = PrivacyRequest::generateTicketNumber();

            $request = PrivacyRequest::create([
                'ticket_number' => $ticketNumber,
                'user_id' => $requestor?->id,
                'requestor_name' => $requestor?->name ?? ($data['requestor_name'] ?? 'Anonymous Requestor'),
                'requestor_email' => $requestor?->email ?? ($data['requestor_email'] ?? ''),
                'request_type' => $data['request_type'],
                'details' => $data['details'],
                'status' => PrivacyRequest::STATUS_PENDING,
            ]);

            $this->auditLogger->record(
                action: AuditAction::SubmittedPrivacyRequest,
                actor: $requestor,
                target: $request,
                targetName: "Data Subject Request {$ticketNumber}",
                description: "Submitted Data Subject Request {$ticketNumber} ({$request->typeLabel()}).",
                newValues: [
                    'ticket_number' => $ticketNumber,
                    'request_type' => $request->request_type,
                    'status' => $request->status,
                ]
            );

            return $request;
        });
    }

    public function submitRequest(User $user, string $type, string $details): PrivacyRequest
    {
        return $this->submit($user, [
            'request_type' => $type,
            'details' => $details,
        ]);
    }

    public function fulfillRequest(PrivacyRequest $request, User $resolver, ?string $notes = null): PrivacyRequest
    {
        return $this->resolve($request, $resolver, [
            'status' => PrivacyRequest::STATUS_FULFILLED,
            'resolution_notes' => $notes ?? 'Data Subject Request fulfilled.',
        ]);
    }

    public function rejectRequest(PrivacyRequest $request, User $resolver, string $reason): PrivacyRequest
    {
        return $this->resolve($request, $resolver, [
            'status' => PrivacyRequest::STATUS_REJECTED,
            'resolution_notes' => $reason,
        ]);
    }

    public function exportPersonalData(User $user): array
    {
        return $this->generateSanitizedExport($user);
    }

    /**
     * Resolve a Data Subject Request (Approve, Fulfill, or Reject with legal grounds).
     *
     * @param  array{status: string, resolution_notes: string}  $decision
     */
    public function resolve(PrivacyRequest $request, User $actor, array $decision): PrivacyRequest
    {
        $validStatuses = [
            PrivacyRequest::STATUS_UNDER_REVIEW,
            PrivacyRequest::STATUS_APPROVED,
            PrivacyRequest::STATUS_FULFILLED,
            PrivacyRequest::STATUS_REJECTED,
            PrivacyRequest::STATUS_CLOSED,
        ];

        if (! in_array($decision['status'], $validStatuses, true)) {
            throw new InvalidArgumentException("Invalid privacy request status: {$decision['status']}.");
        }

        if ($decision['status'] === PrivacyRequest::STATUS_REJECTED && empty(trim($decision['resolution_notes'] ?? ''))) {
            throw new InvalidArgumentException("A formal legal justification is required when denying a Data Subject Request under RA 10173 Section 16.");
        }

        return DB::transaction(function () use ($request, $actor, $decision) {
            $oldStatus = $request->status;
            $request->status = $decision['status'];
            $request->handled_by_user_id = $actor->id;
            $request->handled_at = now();
            $request->resolution_notes = $decision['resolution_notes'] ?? $request->resolution_notes;

            // When fulfilling an Access request, generate the sanitized export payload
            if ($decision['status'] === PrivacyRequest::STATUS_FULFILLED && $request->request_type === PrivacyRequest::TYPE_ACCESS) {
                if ($request->user) {
                    $request->export_payload = $this->generateSanitizedExport($request->user);
                }
            }

            $request->save();

            $this->auditLogger->record(
                action: AuditAction::ResolvedPrivacyRequest,
                actor: $actor,
                target: $request,
                targetName: "Data Subject Request {$request->ticket_number}",
                description: "Resolved Data Subject Request {$request->ticket_number} from [{$oldStatus}] to [{$request->status}].",
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => $request->status,
                    'handled_by' => $actor->name,
                    'resolution_notes' => $request->resolution_notes,
                ]
            );

            return $request;
        });
    }

    /**
     * Generate a sanitized, privacy-compliant personal data export for a user.
     * All authentication secrets (passwords, blind fingerprints, TOTP secrets) are excluded.
     *
     * @return array<string, mixed>
     */
    public function generateSanitizedExport(User $user): array
    {
        $profile = [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'surname' => $user->surname,
            'email' => $user->email,
            'employee_id' => $user->employee_id,
            'department' => $user->department?->value ?? (string) $user->department,
            'role' => $user->role->value,
            'status' => $user->status->value,
            'created_at' => $user->created_at?->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'mfa_enabled' => (bool) $user->mfa_enabled || $user->authenticatorMfaEnabled(),
        ];

        return [
            'export_metadata' => [
                'hospital' => config('privacy.hospital_name'),
                'export_timestamp' => now()->toIso8601String(),
                'regulatory_framework' => 'Republic Act No. 10173 (Data Privacy Act of 2012)',
                'classification' => 'Confidential Personal Data',
            ],
            'account_profile' => $profile,
            'profile' => $profile,
            'operational_summary' => [
                'note' => 'Operational stock movements and requisitions are preserved pursuant to COA inventory accounting regulations.',
                'account_retention_policy' => 'Employee accounts are deactivated rather than permanently erased to maintain historical stock traceability.',
            ],
        ];
    }
}
