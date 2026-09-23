<?php

namespace App\Services\Privacy;

use Illuminate\Support\Collection;

class DsarRedactionService
{
    /**
     * Redact third-party identifying info and sensitive patterns from personal activity records.
     *
     * @param  Collection<int, array<string, mixed>>  $activities
     * @return Collection<int, array<string, mixed>>
     */
    public function redactActivityRecords(Collection $activities, int $requesterUserId): Collection
    {
        return $activities->map(function (array $record) use ($requesterUserId) {
            $record['description'] = $this->redactSensitiveString($record['description'] ?? '');
            $record['target_name'] = $this->redactSensitiveString($record['target_name'] ?? '');
            $record['business_reason'] = $this->redactSensitiveString($record['business_reason'] ?? '');

            return $record;
        });
    }

    /**
     * Sanitize a string against spreadsheet formula injection (CSV Injection / CWE-1236).
     */
    public function sanitizeForSpreadsheet(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $firstChar = substr($value, 0, 1);
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Redact patient and third-party PII patterns from text while preserving transaction context.
     */
    public function redactSensitiveString(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        // Mask patient identifiers / clinical reference markers
        $text = preg_replace(
            '/\b(patient(?:\s*(?:name|id|mrn|no|#))?[:\s]+)([a-z0-9\-]+)/i',
            '$1[REDACTED - PATIENT PRIVACY]',
            $text
        );

        // Mask email addresses that do not belong to institutional domains or are external
        $text = preg_replace(
            '/\b[A-Za-z0-9._%+-]+@(?!djnrmhs\.gov\.ph|hospital\.gov\.ph)[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/i',
            '[REDACTED - THIRD PARTY EMAIL]',
            $text
        );

        // Mask phone numbers (PH mobile / landline format)
        $text = preg_replace(
            '/\b(\+?63|0)9\d{9}\b/',
            '[REDACTED - PHONE]',
            $text
        );

        return $text;
    }

    /**
     * Build the structured Exclusions and Redactions Record explaining what categories
     * were excluded or redacted and the statutory/legal rationale.
     *
     * @return array<int, array{category: string, scope_affected: string, statutory_basis: string, rationale: string}>
     */
    public function getExclusionsAndRedactionsRecord(): array
    {
        return [
            [
                'category' => 'Authentication Secrets & Cryptographic Keys',
                'scope_affected' => 'Password hashes, blind fingerprints, TOTP/authenticator seeds, OTP tokens, API keys',
                'statutory_basis' => 'RA 10173 Section 20 (Security of Personal Information) & ISO/IEC 27001 Control A.8.24',
                'rationale' => 'Authentication secrets are cryptographically sensitive credentials whose disclosure would compromise account security and system integrity.',
            ],
            [
                'category' => 'Patient Medical & Identifying Data',
                'scope_affected' => 'Patient names, hospital numbers (MRN), diagnoses, and clinical dispensing notes in storeroom ledgers',
                'statutory_basis' => 'RA 10173 Section 13 (Sensitive Personal Information) & National Ethical Guidelines for Health Research',
                'rationale' => 'Patient health information constitutes sensitive personal data of third parties and is strictly segregated from employee access requests.',
            ],
            [
                'category' => 'Third-Party Employee Private Data',
                'scope_affected' => 'Personal email addresses, private phone numbers, and non-work details of colleagues appearing in review notes',
                'statutory_basis' => 'RA 10173 Section 16(c) (Proportionality and Data Minimization)',
                'rationale' => 'Information strictly pertaining to other employees is redacted to prevent unauthorized disclosure of third-party personal data.',
            ],
            [
                'category' => 'Statutory Audit and Inventory Ledgers',
                'scope_affected' => 'Immutable audit logs, material requisitions, purchase orders, and stock movement ledger entries',
                'statutory_basis' => 'National Archives of the Philippines (NAP) GRDS-9 & COA Circular No. 2012-001',
                'rationale' => 'Historical government inventory transactions and append-only audit trails are legally mandated public records requiring 10-year preservation and are not subject to erasure.',
            ],
            [
                'category' => 'Proprietary System & Infrastructure Secrets',
                'scope_affected' => 'Database connection strings, server private keys, internal network architecture parameters',
                'statutory_basis' => 'RA 10173 Section 20 & RA 8293 (Intellectual Property Code)',
                'rationale' => 'Internal infrastructure specifications and server configurations do not constitute the data subject\'s personal information.',
            ],
        ];
    }
}
