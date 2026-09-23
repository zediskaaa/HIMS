<?php

namespace App\Http\Controllers\Privacy;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\PrivacyRequest;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DsarDownloadController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array<int, string>
     */
    public static function middleware(): array
    {
        return ['auth:web,admin,super_admin'];
    }

    /**
     * Securely download the fulfilled DSAR package archive.
     */
    public function download(Request $request, PrivacyRequest $privacyRequest): BinaryFileResponse
    {
        $currentUser = $request->user();

        // 1. Server-side Authorization: Must be the data subject OR have ManagePrivacyCompliance permission
        $isOwner = $privacyRequest->user_id !== null && $privacyRequest->user_id === $currentUser->id;
        $isAuthorizedPrivacyOfficer = $currentUser->can(Permission::ManagePrivacyCompliance->value);

        if (! $isOwner && ! $isAuthorizedPrivacyOfficer) {
            $this->auditLogger->record(
                action: AuditAction::DownloadedPrivacyPackage,
                actor: $currentUser,
                target: $privacyRequest,
                targetName: "Data Subject Request {$privacyRequest->ticket_number}",
                description: "Unauthorized download attempt blocked for Data Subject Request {$privacyRequest->ticket_number}.",
                outcome: 'forbidden',
                businessReason: 'Cross-user IDOR access violation blocked server-side'
            );

            abort(403, 'You are not authorized to download this personal data package.');
        }

        // 2. Check Request Status
        if (! in_array($privacyRequest->status, [PrivacyRequest::STATUS_FULFILLED, PrivacyRequest::STATUS_RELEASED, PrivacyRequest::STATUS_READY_FOR_RELEASE], true)) {
            abort(400, 'The requested data package has not yet been approved or fulfilled.');
        }

        // 3. Check Package Expiration
        if ($privacyRequest->isExpired()) {
            $this->auditLogger->record(
                action: AuditAction::DownloadedPrivacyPackage,
                actor: $currentUser,
                target: $privacyRequest,
                targetName: "Data Subject Request {$privacyRequest->ticket_number}",
                description: "Download blocked: Package for Data Subject Request {$privacyRequest->ticket_number} has expired.",
                outcome: 'expired',
                businessReason: 'Package validity window (7 days) exceeded'
            );

            abort(410, 'This data subject export package has expired for security and confidentiality reasons. Please submit a new request to generate a refreshed package.');
        }

        // 4. Validate Package Path & Prevent Directory Traversal
        $packagePath = $privacyRequest->package_path;
        if (empty($packagePath) || str_contains($packagePath, '..')) {
            abort(404, 'Package file record is missing or invalid.');
        }

        $fullPath = storage_path("app/private/{$packagePath}");
        if (! file_exists($fullPath) || ! is_readable($fullPath)) {
            abort(404, 'The export package archive could not be located on the secure storage disk.');
        }

        // 5. Update Download Accountability & Record Audit Trail
        $privacyRequest->increment('download_count');
        $privacyRequest->update(['last_downloaded_at' => now()]);

        $this->auditLogger->record(
            action: AuditAction::DownloadedPrivacyPackage,
            actor: $currentUser,
            target: $privacyRequest,
            targetName: "Data Subject Request {$privacyRequest->ticket_number}",
            description: "Downloaded DSAR fulfillment package ({$privacyRequest->package_filename}) for ticket {$privacyRequest->ticket_number}.",
            outcome: 'success',
            newValues: [
                'package_filename' => $privacyRequest->package_filename,
                'package_hash' => $privacyRequest->package_hash,
                'download_count' => $privacyRequest->download_count,
            ]
        );

        $downloadName = $privacyRequest->package_filename ?: "HIMS-DSAR-{$privacyRequest->ticket_number}.zip";

        return response()->download($fullPath, $downloadName, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
