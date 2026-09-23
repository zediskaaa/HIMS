<?php

namespace App\Services\Privacy;

use App\Models\PrivacyRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class DsarPackageService
{
    public function __construct(
        private readonly DsarDataExtractor $extractor,
        private readonly DsarRedactionService $redactionService,
    ) {}

    /**
     * Build the complete, structured, and secure DSAR package for an approved request.
     *
     * @return array{
     *     package_path: string,
     *     package_filename: string,
     *     package_hash: string,
     *     package_size_bytes: int,
     *     manifest: array<string, mixed>,
     *     expires_at: \Illuminate\Support\Carbon
     * }
     */
    public function generatePackage(PrivacyRequest $request, User $actor): array
    {
        $user = $request->user;
        if (! $user) {
            throw new RuntimeException("Cannot generate DSAR fulfillment package: Requester account is missing.");
        }

        $ticket = $request->ticket_number;
        $tempDir = storage_path("app/private/dsar_temp/{$ticket}");
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filesToPackage = [];

        try {
            // 1. Account Profile & Roles JSON
            $profileData = $this->extractor->extractAccountProfile($user);
            $roleData = $this->extractor->extractRoleAndAccess($user);
            $exclusions = $this->redactionService->getExclusionsAndRedactionsRecord();

            $accountPayload = [
                'metadata' => [
                    'request_reference' => $ticket,
                    'hospital_identity' => config('privacy.hospital_name'),
                    'hospital_address' => config('privacy.hospital_address'),
                    'system_name' => config('privacy.system_name'),
                    'npc_registration_number' => config('privacy.npc_registration_number'),
                    'dpo_contact' => [
                        'name' => config('privacy.dpo_name'),
                        'email' => config('privacy.dpo_email'),
                        'phone' => config('privacy.dpo_phone'),
                    ],
                    'regulatory_basis' => 'Republic Act No. 10173 (Data Privacy Act of 2012) — Section 16(c) & Section 18',
                    'data_classification' => 'Restricted / Personal Data Subject Export',
                    'generated_at' => now()->toIso8601String(),
                ],
                'data_subject' => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'employee_id' => $user->employee_id,
                    'email' => $user->email,
                    'department' => $user->department?->value ?? (string) $user->department,
                ],
                'account_profile' => $profileData,
                'role_and_authorization' => $roleData,
                'statutory_exclusions_record' => $exclusions,
            ];

            $jsonFilename = 'account-profile-and-roles.json';
            $jsonPath = "{$tempDir}/{$jsonFilename}";
            file_put_contents($jsonPath, json_encode($accountPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $filesToPackage[$jsonFilename] = $jsonPath;

            // 2. Activity Metadata CSV
            $rawActivities = $this->extractor->extractPersonalActivity($user);
            $redactedActivities = $this->redactionService->redactActivityRecords($rawActivities, $user->id);

            $csvFilename = 'activity-metadata.csv';
            $csvPath = "{$tempDir}/{$csvFilename}";
            $csvHandle = fopen($csvPath, 'w');
            if ($csvHandle === false) {
                throw new RuntimeException("Unable to open {$csvPath} for writing.");
            }

            // Write UTF-8 BOM for Microsoft Excel compatibility
            fwrite($csvHandle, "\xEF\xBB\xBF");

            $csvHeaders = [
                'Timestamp (UTC)',
                'Timestamp (PHT)',
                'Event ID',
                'Event Type',
                'Action Name',
                'Category',
                'Module',
                'Relationship',
                'Actor Name',
                'Target Type',
                'Target Reference',
                'Target Name',
                'Description',
                'Business Reason',
                'Outcome',
                'IP Address',
                'Device',
                'Location',
            ];
            fputcsv($csvHandle, $csvHeaders);

            foreach ($redactedActivities as $activity) {
                $row = [
                    $this->redactionService->sanitizeForSpreadsheet($activity['timestamp_utc'] ?? ''),
                    $this->redactionService->sanitizeForSpreadsheet($activity['timestamp_display'] ?? ''),
                    $this->redactionService->sanitizeForSpreadsheet($activity['event_id'] ?? ''),
                    $this->redactionService->sanitizeForSpreadsheet($activity['event_type'] ?? ''),
                    $this->redactionService->sanitizeForSpreadsheet($activity['action_name'] ?? ''),
                    $this->redactionService->sanitizeForSpreadsheet($activity['category'] ?? ''),
                    $this->redactionService->sanitizeForSpreadsheet($activity['module'] ?? ''),
                    $this->redactionService->sanitizeForSpreadsheet($activity['relationship'] ?? ''),
                    $this->redactionService->sanitizeForSpreadsheet($activity['actor_name'] ?? ''),
                    $this->redactionService->sanitizeForSpreadsheet($activity['target_type'] ?? ''),
                    $this->redactionService->sanitizeForSpreadsheet($activity['target_reference'] ?? ''),
                    $this->redactionService->sanitizeForSpreadsheet($activity['target_name'] ?? ''),
                    $this->redactionService->sanitizeForSpreadsheet($activity['description'] ?? ''),
                    $this->redactionService->sanitizeForSpreadsheet($activity['business_reason'] ?? ''),
                    $this->redactionService->sanitizeForSpreadsheet($activity['outcome'] ?? ''),
                    $this->redactionService->sanitizeForSpreadsheet($activity['ip_address'] ?? ''),
                    $this->redactionService->sanitizeForSpreadsheet($activity['device'] ?? ''),
                    $this->redactionService->sanitizeForSpreadsheet($activity['location'] ?? ''),
                ];
                fputcsv($csvHandle, $row);
            }
            fclose($csvHandle);
            $filesToPackage[$csvFilename] = $csvPath;

            // 3. Data Access & Processing Transparency Report PDF
            $transparencyPdf = $this->buildTransparencyReportPdf($request, $user, $profileData, $roleData, $exclusions, count($redactedActivities));
            $transparencyFilename = 'data-access-and-processing-transparency-report.pdf';
            $transparencyPath = "{$tempDir}/{$transparencyFilename}";
            file_put_contents($transparencyPath, $transparencyPdf);
            $filesToPackage[$transparencyFilename] = $transparencyPath;

            // 4. Formal DPO Approval / Resolution Document PDF
            $resolutionPdf = $this->buildResolutionPdf($request, $user, $actor, $exclusions);
            $resolutionFilename = 'dpo-resolution.pdf';
            $resolutionPath = "{$tempDir}/{$resolutionFilename}";
            file_put_contents($resolutionPath, $resolutionPdf);
            $filesToPackage[$resolutionFilename] = $resolutionPath;

            // 5. Plaintext README & Verification Instructions
            $fileEntries = [];
            foreach ($filesToPackage as $name => $path) {
                $fileEntries[] = [
                    'filename' => $name,
                    'file_type' => pathinfo($name, PATHINFO_EXTENSION),
                    'size_bytes' => filesize($path),
                    'sha256_hash' => hash_file('sha256', $path),
                    'generated_at' => now()->toIso8601String(),
                ];
            }

            $expiresAt = now()->addDays(7);
            $readmeContent = $this->buildReadmeContent($ticket, $user, $fileEntries, $expiresAt);
            $readmeFilename = 'README.txt';
            $readmePath = "{$tempDir}/{$readmeFilename}";
            file_put_contents($readmePath, $readmeContent);
            $filesToPackage[$readmeFilename] = $readmePath;

            // 6. Cryptographic Integrity Manifest
            $manifestEntries = $fileEntries;
            $manifestEntries[] = [
                'filename' => $readmeFilename,
                'file_type' => 'txt',
                'size_bytes' => filesize($readmePath),
                'sha256_hash' => hash_file('sha256', $readmePath),
                'generated_at' => now()->toIso8601String(),
            ];

            $manifestData = [
                'package_reference' => "HIMS-DSAR-{$ticket}.zip",
                'request_reference' => $ticket,
                'data_subject' => $user->name,
                'employee_id' => $user->employee_id,
                'generated_at' => now()->toIso8601String(),
                'expires_at' => $expiresAt->toIso8601String(),
                'authorized_handler' => $actor->name,
                'total_files' => count($manifestEntries),
                'files' => $manifestEntries,
            ];

            $manifestFilename = 'manifest.json';
            $manifestPath = "{$tempDir}/{$manifestFilename}";
            file_put_contents($manifestPath, json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $filesToPackage[$manifestFilename] = $manifestPath;

            // 7. Assemble Secure ZIP Archive in Private Storage
            $finalDir = storage_path("app/private/dsar/{$ticket}");
            if (! is_dir($finalDir)) {
                mkdir($finalDir, 0755, true);
            }

            $zipFilename = "HIMS-DSAR-{$ticket}.zip";
            $zipPath = "{$finalDir}/{$zipFilename}";

            $zip = new ZipArchive();
            $zipOpenResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($zipOpenResult !== true) {
                throw new RuntimeException("Failed to create ZIP package at {$zipPath}. Error code: {$zipOpenResult}");
            }

            foreach ($filesToPackage as $localName => $fullPath) {
                $zip->addFile($fullPath, $localName);
            }
            $zip->close();

            $packageHash = hash_file('sha256', $zipPath);
            $packageSize = filesize($zipPath);

            return [
                'package_path' => "dsar/{$ticket}/{$zipFilename}",
                'package_filename' => $zipFilename,
                'package_hash' => $packageHash,
                'package_size_bytes' => $packageSize,
                'manifest' => $manifestData,
                'exclusions_summary' => $exclusions,
                'expires_at' => $expiresAt,
            ];
        } finally {
            // Cleanup temp directory files
            foreach ($filesToPackage as $filePath) {
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
            if (is_dir($tempDir)) {
                @rmdir($tempDir);
            }
        }
    }

    /**
     * Build the formal Data Access & Processing Transparency Report PDF.
     */
    private function buildTransparencyReportPdf(
        PrivacyRequest $request,
        User $user,
        array $profileData,
        array $roleData,
        array $exclusions,
        int $activityCount
    ): string {
        $builder = DsarPdfBuilder::make(
            'DATA ACCESS & PROCESSING TRANSPARENCY REPORT',
            'Formal Disclosure Pursuant to Republic Act No. 10173 (Data Privacy Act of 2012) — Section 16(c)',
            $request->ticket_number
        );

        // Subject summary profile card with spacious 2-column layout
        $builder->addProfileCard(
            'REQUEST & DATA SUBJECT PROFILE',
            [
                ['label' => 'Data Subject', 'value' => $user->name],
                ['label' => 'Employee ID', 'value' => $user->employee_id ?: 'N/A'],
                ['label' => 'Department', 'value' => (string) ($user->department?->value ?? $user->department ?? 'General Administration')],
                ['label' => 'Official Email', 'value' => $user->email],
            ],
            [
                ['label' => 'Request Ref', 'value' => '#' . $request->ticket_number],
                ['label' => 'System Role', 'value' => $user->role->label()],
                ['label' => 'Account Status', 'value' => ucfirst($user->status->value)],
                ['label' => 'Date Submitted', 'value' => $request->created_at->format('M d, Y H:i')],
                ['label' => 'Fulfillment Date', 'value' => now()->format('M d, Y H:i')],
                ['label' => 'Reviewing DPO', 'value' => config('privacy.dpo_name')],
            ]
        );

        // Section 1: Categories of Personal Data Processed
        $builder->addSectionHeading('1. Categories of Personal Data Maintained by HIMS');
        $builder->addParagraph(
            'The Hospital Inventory Management System (HIMS) maintains specific categories of employee personal information strictly required for operational inventory governance, supply chain accountability, and role-based access control:'
        );

        $categoriesTable = [
            ['Personal Identity', 'Full legal name, employee identification number, designated hospital department.'],
            ['Contact Information', 'Official institutional email address and hospital-registered contact number.'],
            ['Account & Access Info', 'System role designation, assigned RBAC permissions, and access status.'],
            ['Security Metadata', 'Multi-factor authentication (MFA) status, session timeouts, and login timestamps.'],
            ['Activity & Accountability', 'Transactional audit entries, stock requisition actions, and system signatures.'],
        ];
        $builder->addTable(['Data Category', 'Description of Fields Maintained'], $categoriesTable, [145, 350]);

        // Section 2: Sources of Data
        $builder->addSectionHeading('2. Sources of Personal Data');
        $builder->addParagraph(
            'In compliance with RA 10173 Section 16(c)(2), personal information processed within HIMS originates exclusively from the following institutional sources:'
        );
        $builder->addTable(
            ['Data Category', 'Institutional Source', 'Method of Collection'],
            [
                ['Identity & Account', 'Hospital HR Records & Administrator Provisioning', 'Direct administrative account creation'],
                ['Security Settings', 'User Self-Configuration & Profile Preferences', 'User-managed security setup and TOTP pairing'],
                ['Activity Metadata', 'HIMS Transaction Engine & Audit Subsystem', 'Automated event recording during system interactions'],
                ['Device & Network', 'Application Server HTTP Request Headers', 'Automated session logging (IP address, user agent)'],
            ],
            [125, 185, 185]
        );

        // Section 3: Recipients & Categories of Recipients
        $builder->addSectionHeading('3. Authorized Recipients of Personal Information');
        $builder->addParagraph(
            'Personal data processed in HIMS is not disclosed to commercial entities or external unauthorized parties. Disclosure is restricted to authorized entities with documented statutory or operational necessity:'
        );
        $builder->addTable(
            ['Recipient Category', 'Authorized Entity', 'Legal & Operational Basis'],
            [
                ['Hospital Administration', 'Super Administrators & Storeroom Managers', 'Operational supervision and inventory delegation'],
                ['Data Protection Office', 'Hospital Data Protection Officer (DPO)', 'Privacy compliance review and regulatory audits'],
                ['Statutory Auditor', 'Commission on Audit (COA) Resident Team', 'COA Circular 2012-001 public fund oversight'],
                ['National Archives', 'National Archives of the Philippines (NAP)', 'NAP General Records Schedules (GRDS-9)'],
            ],
            [125, 185, 185]
        );

        // Section 4: Manner and Methods of Processing
        $builder->addSectionHeading('4. Manner and Methods of Processing');
        $builder->addParagraph(
            'HIMS processes employee data through structured database storage, cryptographic one-way hashing for credentials, role-based access enforcement, append-only immutable audit logging, and automated session inactivity controls. All network transmissions are protected via Transport Layer Security (TLS 1.3).'
        );

        // Section 5: Documented Institutional Purposes
        $builder->addSectionHeading('5. Purpose and Statutory Grounds for Processing');
        $builder->addParagraph(
            'The processing of employee data is necessary for compliance with legal obligations to which the hospital is subject and for the performance of public functions (RA 10173 Section 12). Documented purposes include:'
        );
        $builder->addBulletPoint('Ensuring individualized accountability for the custody, movement, and dispensing of clinical drugs and hospital supplies.');
        $builder->addBulletPoint('Enforcing segregation of duties between procurement, receiving, warehousing, and clinical dispensing.');
        $builder->addBulletPoint('Complying with COA and NAP public asset preservation requirements.');

        // Section 6: Automated Decision-Making & Profiling Statement
        $builder->addSectionHeading('6. Automated Decision-Making & Profiling Statement');
        $builder->addCallout(
            'No automated decision-making or profiling that significantly affects the data subject was identified within the reviewed HIMS processing scope. All user provisioning, role adjustments, procurement sign-offs, and administrative actions are governed strictly by authorized human personnel.',
            'info'
        );

        // Section 7: Account Lifecycle & Modification History
        $builder->addSectionHeading('7. Account Lifecycle and Modification History');
        $builder->addStatsGrid([
            ['label' => 'Account Created', 'value' => $user->created_at?->format('M d, Y H:i') ?? 'N/A'],
            ['label' => 'Last Login Recorded', 'value' => $user->last_login_at?->format('M d, Y H:i') ?? 'Never'],
            ['label' => 'Password Last Changed', 'value' => $user->password_changed_at?->format('M d, Y') ?? 'Default'],
            ['label' => 'Total Activity Events', 'value' => number_format($activityCount) . ' records'],
        ]);

        // Section 8: Statutory Exclusions and Redactions
        $builder->addSectionHeading('8. Exclusions and Redactions Applied');
        $builder->addParagraph(
            'Pursuant to RA 10173 Section 20 and statutory hospital guidelines, the following categories were excluded or redacted from this release package:'
        );
        $exclRows = [];
        foreach ($exclusions as $ex) {
            $exclRows[] = [
                $ex['category'],
                $ex['statutory_basis'],
                $ex['rationale'],
            ];
        }
        $builder->addTable(['Exclusion Category', 'Statutory Basis', 'Rationale'], $exclRows, [120, 145, 230]);

        // Section 9: Personal Information Controller & DPO Information
        $builder->addSectionHeading('9. Personal Information Controller (PIC) & DPO Contact');
        $builder->addContactPanels(
            [
                ['label' => 'Controller Entity', 'value' => config('privacy.hospital_name')],
                ['label' => 'Institutional Address', 'value' => config('privacy.hospital_address')],
                ['label' => 'NPC Registration', 'value' => config('privacy.npc_registration_number')],
            ],
            [
                ['label' => 'Data Protection Officer', 'value' => config('privacy.dpo_name')],
                ['label' => 'Official DPO Email', 'value' => config('privacy.dpo_email')],
                ['label' => 'Official Telephone', 'value' => config('privacy.dpo_phone')],
            ]
        );

        // Section 10: Data Subject Follow-Up Rights
        $builder->addSectionHeading('10. Data Subject Follow-Up Rights & Remedies');
        $builder->addParagraph(
            'Under RA 10173, if you identify inaccurate personal information, you may submit a Right to Rectification request (Sec. 16d). If you have privacy inquiries or wish to escalate a concern, you may contact the institutional Data Protection Officer at ' .
            config('privacy.dpo_email') .
            ' or file a formal complaint with the National Privacy Commission (complaints@privacy.gov.ph).'
        );

        $builder->addSignatureBlock(
            config('privacy.dpo_name'),
            'Data Protection Officer | DJNRMHS',
            now()->format('F d, Y')
        );

        return $builder->render();
    }

    /**
     * Build the formal DPO Approval / Resolution Document PDF.
     */
    private function buildResolutionPdf(
        PrivacyRequest $request,
        User $user,
        User $actor,
        array $exclusions
    ): string {
        $builder = DsarPdfBuilder::make(
            'DPO RESOLUTION & COMPLIANCE ORDER',
            'Official Disposition Granting Data Subject Access & Portability under RA 10173',
            'RES-' . $request->ticket_number
        );

        $builder->addProfileCard(
            'PROCEDURAL HISTORY & CASE REFERENCE',
            [
                ['label' => 'Case Reference', 'value' => 'In Re DSAR #' . $request->ticket_number],
                ['label' => 'Data Subject', 'value' => $user->name . ' (' . ($user->employee_id ?: 'N/A') . ')'],
                ['label' => 'Department / Role', 'value' => (string) ($user->department?->value ?? $user->department ?? 'General') . ' | ' . $user->role->label()],
            ],
            [
                ['label' => 'Date Submitted', 'value' => $request->created_at->format('F d, Y')],
                ['label' => 'Date Reviewed', 'value' => $request->handled_at?->format('F d, Y') ?? now()->format('F d, Y')],
                ['label' => 'Disposition Date', 'value' => now()->format('F d, Y')],
                ['label' => 'Authorizing Officer', 'value' => $actor->name],
            ]
        );

        $builder->addSectionHeading('1. Findings of Fact');
        $builder->addParagraph(
            '1. On ' . $request->created_at->format('F d, Y') . ', the Data Subject submitted a formal request under Republic Act No. 10173 Section 16(c) (Right to Access) and Section 18 (Right to Data Portability) requesting an export of their personal data.'
        );
        $builder->addParagraph(
            '2. The identity of the Data Subject was authenticated through system credentials and validated against institutional employee records.'
        );
        $builder->addParagraph(
            '3. The Office of the Data Protection Officer reviewed the requested scope and verified that the requesting party is entitled to their account profile, assigned roles, permissions, and personal activity metadata held in HIMS.'
        );

        $builder->addSectionHeading('2. Scope of Approved Disclosure');
        $builder->addParagraph(
            'The Data Protection Officer hereby APPROVES the release of the following electronic records in commonly used, machine-readable, and human-readable formats:'
        );
        $builder->addBulletPoint('account-profile-and-roles.json: Machine-readable JSON containing verified profile, roles, and RBAC permissions.');
        $builder->addBulletPoint('activity-metadata.csv: Tabular CSV ledger of historical authentication, session, and operational actions.');
        $builder->addBulletPoint('data-access-and-processing-transparency-report.pdf: Comprehensive institutional disclosure report.');

        $builder->addSectionHeading('3. Statutory Redactions & Exclusions');
        $builder->addParagraph(
            'Pursuant to RA 10173 Section 20 and applicable public records standards, the following data categories are excluded from the disclosure package:'
        );
        $exclList = [];
        foreach ($exclusions as $ex) {
            $exclList[] = [$ex['category'], $ex['statutory_basis']];
        }
        $builder->addTable(['Excluded Category', 'Statutory Authority'], $exclList, [200, 295]);

        $builder->addSectionHeading('4. Resolution and Compliance Order');
        $builder->addCallout(
            "WHEREFORE, premises considered, it is hereby RESOLVED and ORDERED that Data Subject Access Request #{$request->ticket_number} is GRANTED. The fulfillment package shall be packaged into a secure, integrity-hashed archive (HIMS-DSAR-{$request->ticket_number}.zip) and made accessible to the Data Subject through the secure download portal for a validity period of seven (7) calendar days.",
            'info'
        );

        $builder->addSectionHeading('5. Follow-Up Privacy Rights');
        $builder->addParagraph(
            'The Data Subject is advised of their statutory rights to petition for correction of inaccurate data (Sec. 16d) or file inquiries with the institutional DPO (' .
            config('privacy.dpo_email') .
            ').'
        );

        $builder->addSignatureBlock(
            $actor->name,
            'Authorized Privacy Reviewer / DPO Delegate',
            now()->format('F d, Y')
        );

        return $builder->render();
    }

    /**
     * Build plaintext README content included in the ZIP package.
     */
    private function buildReadmeContent(string $ticket, User $user, array $manifestEntries, \Illuminate\Support\Carbon $expiresAt): string
    {
        $lines = [];
        $lines[] = "================================================================================";
        $lines[] = "HOSPITAL INVENTORY MANAGEMENT SYSTEM (HIMS) — DATA SUBJECT EXPORT PACKAGE";
        $lines[] = "Republic Act No. 10173 (Data Privacy Act of 2012) — Sections 16(c) & 18";
        $lines[] = "================================================================================";
        $lines[] = "";
        $lines[] = "Request Reference   : #{$ticket}";
        $lines[] = "Data Subject        : {$user->name} (Employee ID: " . ($user->employee_id ?: 'N/A') . ")";
        $lines[] = "Department          : " . ($user->department?->value ?? (string) $user->department);
        $lines[] = "Date Generated      : " . now()->toIso8601String();
        $lines[] = "Package Expiration  : " . $expiresAt->toIso8601String() . " (7 calendar days)";
        $lines[] = "";
        $lines[] = "--------------------------------------------------------------------------------";
        $lines[] = "PACKAGE CONTENTS & INTEGRITY CHECKSUMS (SHA-256)";
        $lines[] = "--------------------------------------------------------------------------------";
        foreach ($manifestEntries as $entry) {
            $lines[] = sprintf("%-46s %8d bytes  %s", $entry['filename'], $entry['size_bytes'], $entry['sha256_hash']);
        }
        $lines[] = "";
        $lines[] = "--------------------------------------------------------------------------------";
        $lines[] = "FILE DESCRIPTIONS";
        $lines[] = "--------------------------------------------------------------------------------";
        $lines[] = "1. account-profile-and-roles.json";
        $lines[] = "   Structured, machine-readable JSON containing your account profile, assigned";
        $lines[] = "   roles, granular RBAC permissions, and access scopes. All security secrets";
        $lines[] = "   (passwords, hashes, TOTP keys) are strictly excluded.";
        $lines[] = "";
        $lines[] = "2. activity-metadata.csv";
        $lines[] = "   Tabular CSV ledger of personal activity logs where your account was the actor";
        $lines[] = "   or administrative target. Third-party and patient PII has been redacted.";
        $lines[] = "";
        $lines[] = "3. data-access-and-processing-transparency-report.pdf";
        $lines[] = "   Print-ready institutional document explaining data categories, sources,";
        $lines[] = "   recipients, processing methods, purposes, and automated decision statements.";
        $lines[] = "";
        $lines[] = "4. dpo-resolution.pdf";
        $lines[] = "   Formal Data Protection Officer resolution order approving and releasing the request.";
        $lines[] = "";
        $lines[] = "5. manifest.json";
        $lines[] = "   Cryptographic manifest recording filenames, sizes, and SHA-256 checksums.";
        $lines[] = "";
        $lines[] = "--------------------------------------------------------------------------------";
        $lines[] = "INSTITUTIONAL CONTACT";
        $lines[] = "--------------------------------------------------------------------------------";
        $lines[] = "Personal Information Controller : " . config('privacy.hospital_name');
        $lines[] = "Address                         : " . config('privacy.hospital_address');
        $lines[] = "Data Protection Officer         : " . config('privacy.dpo_name');
        $lines[] = "Official DPO Contact            : " . config('privacy.dpo_email') . " | " . config('privacy.dpo_phone');
        $lines[] = "NPC Registration Number         : " . config('privacy.npc_registration_number');
        $lines[] = "================================================================================";

        return implode("\r\n", $lines);
    }
}
