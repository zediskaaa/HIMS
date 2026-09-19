<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Hospital / Healthcare Facility Identity
    |--------------------------------------------------------------------------
    |
    | Used in privacy notices, data processing registers, and compliance reports.
    | Defaults represent the institutional identity of DJNRMHS HIMS.
    |
    */
    'hospital_name' => env('HOSPITAL_NAME', 'Dr. Jose N. Rodriguez Memorial Hospital and Sanitarium (DJNRMHS)'),
    'hospital_short_name' => env('HOSPITAL_SHORT_NAME', 'DJNRMHS'),
    'system_name' => env('SYSTEM_NAME', 'Hospital Inventory Management System (HIMS)'),
    'hospital_address' => env('HOSPITAL_ADDRESS', 'Tala, Caloocan City, Metro Manila, Philippines'),

    /*
    |--------------------------------------------------------------------------
    | Data Protection Officer (DPO) Contact Details
    |--------------------------------------------------------------------------
    |
    | Contact information published in compliance with RA 10173 IRR Section 22.
    |
    */
    'dpo_name' => env('DPO_NAME', 'Office of the Data Protection Officer'),
    'dpo_email' => env('DPO_EMAIL', 'privacy@djnrmhs.gov.ph'),
    'dpo_phone' => env('DPO_PHONE', '+63 (2) 8962-8209'),

    /*
    |--------------------------------------------------------------------------
    | National Privacy Commission Registration Reference
    |--------------------------------------------------------------------------
    */
    'npc_registration_number' => env('NPC_REGISTRATION_NUMBER', 'NPC-REG-2026-HIMS-001'),

    /*
    |--------------------------------------------------------------------------
    | Data Retention Thresholds (Days)
    |--------------------------------------------------------------------------
    |
    | Retention periods for ephemeral and operational data.
    | Note: Audit logs are append-only and never purged automatically.
    |
    */
    'retention' => [
        'temporary_chat_attachments_days' => (int) env('RETENTION_CHAT_ATTACHMENTS_DAYS', 30),
        'expired_notifications_days' => (int) env('RETENTION_NOTIFICATIONS_DAYS', 90),
        'resolved_recovery_records_days' => (int) env('RETENTION_RECOVERY_RECORDS_DAYS', 180),
        'session_lifetime_minutes' => (int) env('SESSION_LIFETIME', 4),
    ],
];
