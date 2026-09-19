<?php

namespace App\Services\Privacy;

class DataClassificationService
{
    public const TIER_PUBLIC = 'public';
    public const TIER_INTERNAL = 'internal';
    public const TIER_CONFIDENTIAL = 'confidential';
    public const TIER_RESTRICTED = 'restricted';

    /**
     * Get the defined classification tiers and their security requirements.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getTiers(): array
    {
        return array_map(function ($k, $t) {
            return [
                'tier_id' => $k,
                'name' => $t['name'],
                'description' => $t['description'],
                'handling_rules' => $t['controls'],
            ];
        }, array_keys($this->tiers()), array_values($this->tiers()));
    }

    public function getCatalog(): array
    {
        return array_map(function ($key, $asset) {
            return [
                'entity' => $asset['entity'],
                'tier' => $asset['tier'],
                'attributes' => array_keys($asset['fields'] ?? []),
                'handling' => $asset['retention'] ?? ($asset['statutory_basis'] ?? 'Standard RBAC'),
            ];
        }, array_keys($this->inventory()), array_values($this->inventory()));
    }

    public function tiers(): array
    {
        return [
            self::TIER_PUBLIC => [
                'name' => 'Public',
                'description' => 'Information intended for public consumption without restriction.',
                'examples' => 'General item nomenclature, hospital facility address, terms of use, privacy notice.',
                'controls' => 'Integrity protection, public availability, no authentication required.',
                'tone' => 'neutral',
            ],
            self::TIER_INTERNAL => [
                'name' => 'Internal Operational',
                'description' => 'Operational hospital data accessible to authenticated personnel based on assigned duties.',
                'examples' => 'Stock inventory levels, storage bin locations, reorder thresholds, batch numbers, consumption forecasts.',
                'controls' => 'Authenticated session required, role-based access control (RBAC), TLS in transit.',
                'tone' => 'primary',
            ],
            self::TIER_CONFIDENTIAL => [
                'name' => 'Confidential Personal Data',
                'description' => 'Personal information relating to employees, staff, supplier contacts, and transport personnel governed by RA 10173.',
                'examples' => 'Full names, employee IDs, institutional emails, department assignments, contact phone numbers, supplier TIN.',
                'controls' => 'Strict least-privilege RBAC, access auditing, sanitized exports, masking in non-administrative views.',
                'tone' => 'warning',
            ],
            self::TIER_RESTRICTED => [
                'name' => 'Restricted / Sensitive & Secrets',
                'description' => 'Cryptographic material, authentication secrets, sensitive audit telemetry, and high-risk security data.',
                'examples' => 'Password hashes, blind fingerprints, TOTP MFA secrets, session tokens, audit GPS coordinates, security incident notes.',
                'controls' => 'Encryption at rest, HMAC hashing, fail-closed access, never serialized to JSON or exposed in public views, re-authentication required.',
                'tone' => 'danger',
            ],
        ];
    }

    /**
     * Get the complete HIMS entity classification catalog.
     *
     * @return array<string, array<string, mixed>>
     */
    public function inventory(): array
    {
        return [
            'users' => [
                'entity' => 'User & Employee Accounts',
                'module' => 'User Administration',
                'tier' => self::TIER_CONFIDENTIAL,
                'fields' => [
                    'name' => self::TIER_CONFIDENTIAL,
                    'email' => self::TIER_CONFIDENTIAL,
                    'employee_id' => self::TIER_CONFIDENTIAL,
                    'department' => self::TIER_INTERNAL,
                    'role' => self::TIER_INTERNAL,
                    'status' => self::TIER_INTERNAL,
                    'password' => self::TIER_RESTRICTED,
                    'authenticator_secret' => self::TIER_RESTRICTED,
                    'login_lockout' => self::TIER_RESTRICTED,
                ],
                'statutory_basis' => 'RA 10173 Section 12 (Contract / Legitimate Purpose)',
                'retention' => 'Retained during active employment; deactivated upon exit to preserve stock ledger traceability.',
            ],
            'suppliers' => [
                'entity' => 'Suppliers & Vendors',
                'module' => 'Supplier Management',
                'tier' => self::TIER_CONFIDENTIAL,
                'fields' => [
                    'name' => self::TIER_INTERNAL,
                    'contact_person' => self::TIER_CONFIDENTIAL,
                    'email' => self::TIER_CONFIDENTIAL,
                    'phone' => self::TIER_CONFIDENTIAL,
                    'tin' => self::TIER_CONFIDENTIAL,
                    'accreditation_files' => self::TIER_CONFIDENTIAL,
                ],
                'statutory_basis' => 'RA 12009 / RA 10173 Section 12 (Procurement Contract)',
                'retention' => '10 Years per COA Circulars and Tax Accounting regulations.',
            ],
            'inventory_items' => [
                'entity' => 'Inventory Items & Batches',
                'module' => 'Inventory Management',
                'tier' => self::TIER_INTERNAL,
                'fields' => [
                    'name' => self::TIER_PUBLIC,
                    'sku' => self::TIER_INTERNAL,
                    'quantity_on_hand' => self::TIER_INTERNAL,
                    'unit_cost' => self::TIER_CONFIDENTIAL,
                    'batch_number' => self::TIER_INTERNAL,
                    'expiry_date' => self::TIER_INTERNAL,
                ],
                'statutory_basis' => 'Hospital Operational Ledger',
                'retention' => 'Permanent inventory ledger history.',
            ],
            'audit_logs' => [
                'entity' => 'System Audit Trail',
                'module' => 'Forensic Accountability',
                'tier' => self::TIER_RESTRICTED,
                'fields' => [
                    'actor_name' => self::TIER_CONFIDENTIAL,
                    'ip_address' => self::TIER_RESTRICTED,
                    'user_agent' => self::TIER_INTERNAL,
                    'browser_location' => self::TIER_RESTRICTED,
                    'old_values' => self::TIER_CONFIDENTIAL,
                    'new_values' => self::TIER_CONFIDENTIAL,
                ],
                'statutory_basis' => 'RA 10173 Section 20(c) / ISO 27001 A.8.15',
                'retention' => 'Append-only immutable record. Never purged automatically.',
            ],
            'logistics_documents' => [
                'entity' => 'Logistics & Receiving Documents',
                'module' => 'Logistics & Documents',
                'tier' => self::TIER_INTERNAL,
                'fields' => [
                    'document_number' => self::TIER_INTERNAL,
                    'file_path' => self::TIER_RESTRICTED,
                    'sha256_checksum' => self::TIER_INTERNAL,
                    'retention_class' => self::TIER_INTERNAL,
                ],
                'statutory_basis' => 'National Archives of the Philippines (NAP) GRDS',
                'retention' => '2 to 10 years depending on NAP retention classification.',
            ],
            'privacy_requests' => [
                'entity' => 'Data Subject Requests (DSR)',
                'module' => 'Privacy & Compliance',
                'tier' => self::TIER_CONFIDENTIAL,
                'fields' => [
                    'requestor_name' => self::TIER_CONFIDENTIAL,
                    'requestor_email' => self::TIER_CONFIDENTIAL,
                    'details' => self::TIER_CONFIDENTIAL,
                    'resolution_notes' => self::TIER_CONFIDENTIAL,
                    'export_payload' => self::TIER_CONFIDENTIAL,
                ],
                'statutory_basis' => 'RA 10173 Chapter IV (Data Subject Rights)',
                'retention' => '5 Years post-resolution for regulatory compliance evidence.',
            ],
            'security_incidents' => [
                'entity' => 'Security Incidents & Breach Reports',
                'module' => 'Incident Response',
                'tier' => self::TIER_RESTRICTED,
                'fields' => [
                    'incident_number' => self::TIER_INTERNAL,
                    'title' => self::TIER_INTERNAL,
                    'description' => self::TIER_RESTRICTED,
                    'breach_assessment' => self::TIER_RESTRICTED,
                    'containment_actions' => self::TIER_RESTRICTED,
                ],
                'statutory_basis' => 'NPC Circular 16-03 / ISO 27001 A.5.24',
                'retention' => '5 Years post-containment for NPC regulatory audit.',
            ],
        ];
    }
}
