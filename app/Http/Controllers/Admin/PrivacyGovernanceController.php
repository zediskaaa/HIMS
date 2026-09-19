<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\PrivacyRequest;
use App\Models\SecurityIncident;
use App\Services\Privacy\CompliancePostureService;
use App\Services\Privacy\DataClassificationService;
use App\Services\Privacy\DataProcessingRegisterService;
use App\Services\Privacy\DataRetentionService;
use App\Services\Privacy\PrivacyRequestService;
use App\Services\Privacy\SecurityIncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivacyGovernanceController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly CompliancePostureService $complianceService,
        private readonly DataProcessingRegisterService $ropaService,
        private readonly DataClassificationService $classificationService,
        private readonly PrivacyRequestService $privacyRequestService,
        private readonly SecurityIncidentService $securityIncidentService,
        private readonly DataRetentionService $retentionService,
    ) {}

    /**
     * @return array<int, string>
     */
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            'can:'.Permission::ManagePrivacyCompliance->value,
        ];
    }

    /**
     * Privacy & Security Governance Dashboard.
     */
    public function index(Request $request): View
    {
        $activeTab = $request->query('tab', 'posture');
        if (! in_array($activeTab, ['posture', 'ropa', 'classification', 'dsr', 'incidents', 'retention'], true)) {
            $activeTab = 'posture';
        }

        $posture = $this->complianceService->getPostureReport();
        $ropaActivities = $this->ropaService->getActivities();
        $classificationCatalog = $this->classificationService->getCatalog();
        $classificationTiers = $this->classificationService->getTiers();

        $privacyRequests = PrivacyRequest::with(['user', 'resolvedBy'])
            ->latest()
            ->paginate(15, ['*'], 'dsr_page');

        $securityIncidents = SecurityIncident::with(['reportedBy', 'assignedTo'])
            ->latest()
            ->paginate(15, ['*'], 'incident_page');

        $openDsrCount = PrivacyRequest::whereIn('status', ['pending', 'in_review'])->count();
        $openIncidentsCount = SecurityIncident::whereIn('status', ['reported', 'investigating', 'contained'])->count();

        return view('admin.privacy.index', [
            'activeTab' => $activeTab,
            'posture' => $posture,
            'ropaActivities' => $ropaActivities,
            'classificationCatalog' => $classificationCatalog,
            'classificationTiers' => $classificationTiers,
            'privacyRequests' => $privacyRequests,
            'securityIncidents' => $securityIncidents,
            'openDsrCount' => $openDsrCount,
            'openIncidentsCount' => $openIncidentsCount,
        ]);
    }

    /**
     * Fulfill an approved Data Subject Request.
     */
    public function fulfillRequest(Request $request, PrivacyRequest $privacyRequest): RedirectResponse
    {
        $validated = $request->validate([
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->privacyRequestService->fulfillRequest(
            request: $privacyRequest,
            resolver: $request->user(),
            notes: $validated['resolution_notes'] ?? null
        );

        return back()->with('status', "Data Subject Request #{$privacyRequest->ticket_number} marked as fulfilled.");
    }

    /**
     * Reject a Data Subject Request with mandatory lawful basis.
     */
    public function rejectRequest(Request $request, PrivacyRequest $privacyRequest): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'reason.required' => 'A formal legal/statutory justification is mandatory when refusing a Data Subject Request under RA 10173 Section 16.',
            'reason.min' => 'Please provide at least 10 characters justifying the refusal under applicable Philippine statutory retention laws.',
        ]);

        $this->privacyRequestService->rejectRequest(
            request: $privacyRequest,
            resolver: $request->user(),
            reason: $validated['reason']
        );

        return back()->with('status', "Data Subject Request #{$privacyRequest->ticket_number} rejected with statutory justification.");
    }

    /**
     * Download portable personal data export payload (JSON format).
     */
    public function exportUserData(PrivacyRequest $privacyRequest): StreamedResponse|JsonResponse
    {
        if ($privacyRequest->user === null) {
            abort(404, 'Associated user account could not be found.');
        }

        $exportData = $this->privacyRequestService->exportPersonalData($privacyRequest->user);
        $filename = sprintf('personal-data-export-%s-%s.json', $privacyRequest->ticket_number, now()->format('YmdHis'));

        return response()->streamDownload(function () use ($exportData) {
            echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Record a new Security Incident under ISO 27001 Control A.5.24 / NPC Circular 16-03.
     */
    public function storeIncident(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'incident_type' => ['required', 'string', 'max:100'],
            'severity' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'containment_actions' => ['nullable', 'string', 'max:5000'],
            'is_reportable_breach' => ['nullable', 'boolean'],
        ]);

        $incident = $this->securityIncidentService->recordIncident(
            title: $validated['title'],
            description: $validated['description'],
            severity: $validated['severity'],
            incidentType: $validated['incident_type'],
            reportedBy: $request->user(),
            containmentActions: $validated['containment_actions'] ?? null,
            isReportableBreach: (bool) ($validated['is_reportable_breach'] ?? false),
        );

        return back()->with('status', "Security incident #{$incident->incident_number} recorded successfully.");
    }

    /**
     * Update an existing Security Incident status and breach assessment.
     */
    public function updateIncident(Request $request, SecurityIncident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['reported', 'investigating', 'contained', 'resolved', 'closed'])],
            'severity' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'containment_actions' => ['nullable', 'string', 'max:5000'],
            'remediation_notes' => ['nullable', 'string', 'max:5000'],
            'is_reportable_breach' => ['nullable', 'boolean'],
            'affected_subjects_count' => ['nullable', 'integer', 'min:0'],
            'npc_notified_at' => ['nullable', 'date'],
        ]);

        $this->securityIncidentService->updateIncidentStatus(
            incident: $incident,
            status: $validated['status'],
            actor: $request->user(),
            remediationNotes: $validated['remediation_notes'] ?? null,
            containmentActions: $validated['containment_actions'] ?? null,
            severity: $validated['severity'] ?? null,
        );

        if (array_key_exists('is_reportable_breach', $validated) || array_key_exists('affected_subjects_count', $validated)) {
            $this->securityIncidentService->assessBreachNotification(
                incident: $incident,
                isReportableBreach: (bool) ($validated['is_reportable_breach'] ?? false),
                affectedCount: isset($validated['affected_subjects_count']) ? (int) $validated['affected_subjects_count'] : null,
                notifiedAt: ! empty($validated['npc_notified_at']) ? \Carbon\Carbon::parse($validated['npc_notified_at']) : null,
                actor: $request->user()
            );
        }

        return back()->with('status', "Security incident #{$incident->incident_number} updated.");
    }

    /**
     * Trigger manual ephemeral data retention sweep.
     */
    public function sweepRetention(Request $request): RedirectResponse
    {
        $results = $this->retentionService->sweepEphemeralData(dryRun: false, actor: $request->user());

        $message = sprintf(
            'Retention sweep complete: %d temporary files removed, %d read notifications purged, %d recovery records archived. %d NAP records flagged for review. Permanent audit trails preserved.',
            $results['temporary_files_cleared'],
            $results['notifications_purged'],
            $results['resolved_recovery_records_purged'],
            $results['expired_documents_flagged']
        );

        return back()->with('status', $message);
    }
}
