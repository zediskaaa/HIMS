<?php

namespace App\Services\Privacy;

use App\Enums\AuditAction;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class PrivacyRequestService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DsarPackageService $packageService,
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
                'target_completion_date' => now()->addDays(21), // 15 working days standard SLA
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

    /**
     * Transition a request into Under Review status.
     */
    public function markUnderReview(PrivacyRequest $request, User $actor): PrivacyRequest
    {
        return DB::transaction(function () use ($request, $actor) {
            $oldStatus = $request->status;
            $request->status = PrivacyRequest::STATUS_UNDER_REVIEW;
            $request->handled_by_user_id = $actor->id;
            $request->handled_at = now();
            $request->save();

            $this->auditLogger->record(
                action: AuditAction::ResolvedPrivacyRequest,
                actor: $actor,
                target: $request,
                targetName: "Data Subject Request {$request->ticket_number}",
                description: "Placed Data Subject Request {$request->ticket_number} under review.",
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $request->status, 'handled_by' => $actor->name]
            );

            return $request;
        });
    }

    /**
     * Approve a Data Subject Request and trigger actual fulfillment package generation.
     */
    public function approveAndFulfill(PrivacyRequest $request, User $actor, ?string $notes = null): PrivacyRequest
    {
        // 1. Mark Approved & Processing
        DB::transaction(function () use ($request, $actor, $notes) {
            $oldStatus = $request->status;
            $request->status = PrivacyRequest::STATUS_APPROVED;
            $request->approved_at = now();
            $request->approved_by_user_id = $actor->id;
            $request->handled_by_user_id = $actor->id;
            $request->handled_at = now();
            $request->processing_started_at = now();
            $request->resolution_notes = $notes ?? 'Data Subject Request approved. Generating access and portability package.';
            $request->save();

            $this->auditLogger->record(
                action: AuditAction::ApprovedPrivacyRequest,
                actor: $actor,
                target: $request,
                targetName: "Data Subject Request {$request->ticket_number}",
                description: "Approved Data Subject Request {$request->ticket_number} for fulfillment.",
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => $request->status,
                    'approved_by' => $actor->name,
                    'resolution_notes' => $request->resolution_notes,
                ]
            );
        });

        // 2. Perform actual data extraction, redaction, and multi-artifact packaging
        try {
            $packageResult = $this->packageService->generatePackage($request, $actor);

            return DB::transaction(function () use ($request, $actor, $packageResult) {
                // Support both STATUS_FULFILLED and STATUS_RELEASED (using 'fulfilled' as base status for compatibility)
                $request->status = PrivacyRequest::STATUS_FULFILLED;
                $request->fulfilled_at = now();
                $request->package_path = $packageResult['package_path'];
                $request->package_filename = $packageResult['package_filename'];
                $request->package_hash = $packageResult['package_hash'];
                $request->package_size_bytes = $packageResult['package_size_bytes'];
                $request->package_manifest = $packageResult['manifest'];
                $request->package_expires_at = $packageResult['expires_at'];
                $request->exclusions_summary = $packageResult['exclusions_summary'];

                if ($request->user) {
                    $request->export_payload = $this->generateSanitizedExport($request->user);
                }

                $request->save();

                $this->auditLogger->record(
                    action: AuditAction::FulfilledPrivacyRequest,
                    actor: $actor,
                    target: $request,
                    targetName: "Data Subject Request {$request->ticket_number}",
                    description: "Fulfilled Data Subject Request {$request->ticket_number}. Package {$request->package_filename} generated with SHA-256 integrity hash.",
                    newValues: [
                        'status' => $request->status,
                        'package_filename' => $request->package_filename,
                        'package_hash' => $request->package_hash,
                        'package_size_bytes' => $request->package_size_bytes,
                        'expires_at' => $request->package_expires_at?->toIso8601String(),
                    ]
                );

                return $request;
            });
        } catch (Throwable $e) {
            // Record failure in resolution notes and keep request in approved state for safe retry
            $request->resolution_notes = "Package generation failed: " . $e->getMessage();
            $request->save();

            throw $e;
        }
    }

    /**
     * Fulfill an approved Data Subject Request (triggers full package generation).
     */
    public function fulfillRequest(PrivacyRequest $request, User $resolver, ?string $notes = null): PrivacyRequest
    {
        return $this->approveAndFulfill($request, $resolver, $notes);
    }

    /**
     * Reject a Data Subject Request with mandatory statutory justification.
     */
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
            PrivacyRequest::STATUS_RELEASED,
            PrivacyRequest::STATUS_REJECTED,
            PrivacyRequest::STATUS_CLOSED,
        ];

        if (! in_array($decision['status'], $validStatuses, true)) {
            throw new InvalidArgumentException("Invalid privacy request status: {$decision['status']}.");
        }

        if ($decision['status'] === PrivacyRequest::STATUS_REJECTED && empty(trim($decision['resolution_notes'] ?? ''))) {
            throw new InvalidArgumentException("A formal legal justification is required when denying a Data Subject Request under RA 10173 Section 16.");
        }

        if (in_array($decision['status'], [PrivacyRequest::STATUS_APPROVED, PrivacyRequest::STATUS_FULFILLED, PrivacyRequest::STATUS_RELEASED], true)) {
            return $this->approveAndFulfill($request, $actor, $decision['resolution_notes'] ?? null);
        }

        return DB::transaction(function () use ($request, $actor, $decision) {
            $oldStatus = $request->status;
            $request->status = $decision['status'];
            $request->handled_by_user_id = $actor->id;
            $request->handled_at = now();
            $request->resolution_notes = $decision['resolution_notes'] ?? $request->resolution_notes;
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
     * Generate a sanitized personal data export payload (legacy JSON structure).
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
