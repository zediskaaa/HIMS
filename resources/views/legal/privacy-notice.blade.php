<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    <title>Privacy Policy · HIMS</title>

    {{-- Early zero-flicker theme script --}}
    @include('layouts.partials.theme-script')
    @include('layouts.partials.navigation-loading-state')

    <link rel="preconnect" href="https://fonts.bunny.net">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Official Institutional Document Engine & Print Safeguards --}}
    @include('legal.partials.official_document_styles', ['documentRef' => 'DPA-2012-HIMS-POL'])
</head>
<body class="min-h-full bg-neutral-100 dark:bg-neutral-950 font-sans text-neutral-800 dark:text-neutral-200 antialiased selection:bg-primary-600 selection:text-white transition-colors duration-150">
@include('layouts.partials.loading-overlay')

@php
    $rawReturn = request()->query('return');
    $candidateUrl = null;
    $currentHost = request()->getHost();

    if ($rawReturn) {
        if (str_starts_with($rawReturn, '/') && ! str_starts_with($rawReturn, '//')) {
            $candidateUrl = url($rawReturn);
        } elseif (filter_var($rawReturn, FILTER_VALIDATE_URL)) {
            $parsedHost = parse_url($rawReturn, PHP_URL_HOST);
            if ($parsedHost === $currentHost) {
                $candidateUrl = $rawReturn;
            }
        }
    }

    if (! $candidateUrl) {
        $prevUrl = url()->previous();
        if ($prevUrl && $prevUrl !== url()->current()) {
            $prevPath = parse_url($prevUrl, PHP_URL_PATH) ?? '';
            $isLegalAlias = in_array($prevPath, ['/privacy-notice', '/privacy-policy', '/privacy', '/terms-of-use', '/terms-and-conditions', '/terms'], true);
            $parsedHost = parse_url($prevUrl, PHP_URL_HOST);
            if (! $isLegalAlias && $parsedHost === $currentHost) {
                $candidateUrl = $prevUrl;
            }
        }
    }

    if (! $candidateUrl) {
        if (auth()->check()) {
            $candidateUrl = auth()->user()->isSuperAdministrator()
                ? route('super-admin.dashboard')
                : (auth()->user()->isAdministrator() ? route('admin.users.index') : route('dashboard'));
        } else {
            $candidateUrl = url('/');
        }
    }

    $candidatePath = parse_url($candidateUrl, PHP_URL_PATH) ?? '/';
    if (str_contains($candidatePath, '/admin/users/create')) {
        $backLabel = 'Back to Create User';
    } elseif (str_contains($candidatePath, '/admin/users')) {
        $backLabel = 'Back to User Management';
    } elseif (str_contains($candidatePath, '/admin/audit-logs')) {
        $backLabel = 'Back to Audit Trail';
    } elseif (str_starts_with($candidatePath, '/admin/permissions')) {
        $backLabel = 'Back to Permissions';
    } elseif (str_starts_with($candidatePath, '/admin/')) {
        $backLabel = 'Back to Admin Panel';
    } elseif (str_starts_with($candidatePath, '/super-admin/')) {
        $backLabel = 'Back to Super Admin';
    } elseif ($candidatePath === '/dashboard' || str_starts_with($candidatePath, '/inventory/')) {
        $backLabel = 'Back to Dashboard';
    } elseif (str_contains($candidatePath, '/login')) {
        $backLabel = 'Back to Login';
    } elseif (str_contains($candidatePath, '/register')) {
        $backLabel = 'Back to Registration';
    } elseif ($candidatePath === '/' || $candidatePath === '') {
        $backLabel = 'Return to Home';
    } else {
        $backLabel = 'Go Back';
    }

    $isAuth = auth()->check();
    $portalUrl = $isAuth
        ? (auth()->user()->isSuperAdministrator()
            ? route('super-admin.dashboard')
            : (auth()->user()->isAdministrator() ? route('admin.users.index') : route('dashboard')))
        : route('login');
    $portalLabel = $isAuth
        ? (auth()->user()->isAdministrator() || auth()->user()->isSuperAdministrator() ? 'Admin Panel' : 'Dashboard')
        : 'Staff Portal';
    $portalIcon = $isAuth ? 'squares-2x2' : 'arrow-right-on-rectangle';

    $hospitalName = config('privacy.hospital_name', 'Dr. Jose N. Rodriguez Memorial Hospital and Sanitarium (DJNRMHS)');
@endphp

    {{-- Screen Document Canvas --}}
    <div class="relative min-h-screen py-6 sm:py-10 px-3 sm:px-6 lg:px-8">

        {{-- Top Utility Bar (Screen View Only — Excluded from Print & Export) --}}
        <header class="no-print mx-auto mb-6 flex max-w-[216mm] flex-wrap items-center justify-between gap-3 text-xs font-medium">
            <a href="{{ $candidateUrl }}"
               onclick="if (window.opener && !window.opener.closed) { window.close(); setTimeout(() => { window.location.href = '{{ $candidateUrl }}'; }, 150); return false; }"
               class="inline-flex items-center gap-1.5 text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100 transition-colors">
                <x-ui.icon name="arrow-left" class="h-3.5 w-3.5" />
                <span>{{ $backLabel }}</span>
            </a>

            <div class="inline-flex items-center rounded-lg bg-white dark:bg-neutral-900 p-1 shadow-2xs ring-1 ring-neutral-200 dark:ring-neutral-800">
                <span class="rounded-md bg-neutral-100 dark:bg-neutral-800 px-3 py-1 font-semibold text-neutral-900 dark:text-neutral-100">Privacy Policy</span>
                <a href="{{ route('terms', request()->query()) }}" class="rounded-md px-3 py-1 text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100 transition-colors">Terms and Conditions</a>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                <x-ui.theme-toggle class="text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100 p-1.5 rounded-lg hover:bg-neutral-200/70 dark:hover:bg-neutral-800 transition-colors" />

                <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-lg bg-neutral-900 dark:bg-white px-3.5 py-1.5 text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 shadow-sm transition-colors font-medium">
                    <x-ui.icon name="printer" class="h-3.5 w-3.5" />
                    <span>Print / Save as PDF</span>
                </button>
                <a href="{{ $portalUrl }}" class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3 py-1.5 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-750 shadow-2xs transition-colors">
                    <x-ui.icon :name="$portalIcon" class="h-3.5 w-3.5" />
                    <span>{{ $portalLabel }}</span>
                </a>
            </div>
        </header>

        {{-- ======================================================================
             FORMAL INSTITUTIONAL DOCUMENT SHEET (A4 Standard Specification)
             ====================================================================== --}}
        <main class="doc-sheet mx-auto max-w-[216mm] min-h-[279mm] rounded-sm border border-neutral-400 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-8 sm:p-14 lg:p-16 text-neutral-900 dark:text-neutral-100 shadow-md transition-colors duration-150 text-[10.5pt] leading-[1.55]">

            {{-- 1. Institutional Masthead Header --}}
            <header class="doc-masthead border-b-[2pt] border-neutral-900 dark:border-neutral-100 pb-3 mb-5">
                <div class="flex items-start justify-between gap-4">
                    {{-- Logo & Entity Identity --}}
                    <div class="flex items-center gap-4">
                        <img src="{{ asset('img/hims-logo.png') }}" alt="HIMS Logo" class="h-14 w-14 object-contain shrink-0" />
                        <div>
                            <p class="text-[8.5pt] font-bold uppercase tracking-[0.16em] text-neutral-700 dark:text-neutral-300">
                                Republic of the Philippines &bull; Department of Health
                            </p>
                            <h2 class="text-base sm:text-lg font-bold tracking-tight text-neutral-900 dark:text-white uppercase leading-snug">
                                {{ $hospitalName }}
                            </h2>
                            <p class="text-[8.5pt] font-bold tracking-wider text-primary-900 dark:text-primary-300 uppercase">
                                Hospital Inventory Management System (HIMS)
                            </p>
                        </div>
                    </div>

                    {{-- Document Tracking Block --}}
                    <div class="hidden sm:block text-right text-[8.5pt] font-mono leading-tight text-neutral-800 dark:text-neutral-200 border-l border-neutral-400 dark:border-neutral-600 pl-4 shrink-0">
                        <div><strong class="text-neutral-900 dark:text-white">REF:</strong> DPA-2012-HIMS-POL</div>
                        <div><strong class="text-neutral-900 dark:text-white">VER:</strong> 1.0 (Operational)</div>
                        <div><strong class="text-neutral-900 dark:text-white">DATE:</strong> September 2026</div>
                        <div class="text-[8pt] font-bold text-neutral-700 dark:text-neutral-300 mt-1 uppercase">Institutional Policy</div>
                    </div>
                </div>

                {{-- Formal Institutional Double Rule Accent --}}
                <div class="doc-rule mt-3 border-t border-neutral-400 dark:border-neutral-600"></div>
            </header>

            {{-- 2. Document Title --}}
            <div class="mb-5 text-center sm:text-left">
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-neutral-900 dark:text-white uppercase">
                    Privacy Policy
                </h1>
                <p class="mt-0.5 text-[9pt] font-bold uppercase tracking-widest text-neutral-700 dark:text-neutral-300">
                    System Privacy Notice &bull; Republic Act No. 10173 Compliance &bull; Effective: September 2026
                </p>
            </div>

            {{-- 3. Document Governance Information Block (Formal Metadata Table) --}}
            <section class="mb-6 keep-together">
                <table class="doc-table w-full text-left text-[9pt] border border-neutral-600 dark:border-neutral-600 border-collapse">
                    <tbody>
                        <tr class="border-b border-neutral-400 dark:border-neutral-600">
                            <th class="w-1/4 doc-meta-label">Document Title</th>
                            <td class="w-1/4 doc-meta-value font-semibold">System Privacy Notice &amp; Data Protection Policy</td>
                            <th class="w-1/4 doc-meta-label">Document Reference</th>
                            <td class="w-1/4 doc-meta-value font-mono font-bold">DPA-2012-HIMS-POL</td>
                        </tr>
                        <tr class="border-b border-neutral-400 dark:border-neutral-600">
                            <th class="doc-meta-label">Release Version</th>
                            <td class="doc-meta-value font-mono font-medium">Version 1.0 (Operational)</td>
                            <th class="doc-meta-label">Effective Date</th>
                            <td class="doc-meta-value font-medium">September 2026</td>
                        </tr>
                        <tr class="border-b border-neutral-400 dark:border-neutral-600">
                            <th class="doc-meta-label">Governing Institution</th>
                            <td colspan="3" class="doc-meta-value font-bold">{{ $hospitalName }}</td>
                        </tr>
                        <tr class="border-b border-neutral-400 dark:border-neutral-600">
                            <th class="doc-meta-label">Data Protection Officer</th>
                            <td class="doc-meta-value">{{ config('privacy.dpo_name', 'Office of the Data Protection Officer') }}</td>
                            <th class="doc-meta-label">DPO Contact Email</th>
                            <td class="doc-meta-value font-mono font-medium">{{ config('privacy.dpo_email', 'dpo@djnrmhs.gov.ph') }}</td>
                        </tr>
                        <tr class="border-b border-neutral-400 dark:border-neutral-600">
                            <th class="doc-meta-label">Applicability</th>
                            <td colspan="3" class="doc-meta-value">
                                All authorized healthcare personnel, pharmacists, supply chain officers, warehouse custodians, and system administrators.
                            </td>
                        </tr>
                        <tr>
                            <th class="doc-meta-label">Supervising Authority</th>
                            <td class="doc-meta-value">National Privacy Commission (NPC) &amp; Institutional Governance</td>
                            <th class="doc-meta-label">Statutory Framework</th>
                            <td class="doc-meta-value font-medium">Republic Act No. 10173 (DPA 2012), IRR, NPC Circulars</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            {{-- Document Preamble / Introduction --}}
            <section class="mb-5">
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-3 text-[10.5pt]">
                    This is the privacy policy of <strong>{{ $hospitalName }}</strong>. This document explains <strong>{{ $hospitalName }}</strong>'s policies for the collection, use, and disclosure of personal information processed through the Hospital Inventory Management System (HIMS).
                </p>
            </section>

            {{-- Section 1: Data Controller Information --}}
            <section class="mb-5 keep-together">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 mb-2.5">
                    1. Data Controller Information
                </h2>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-3 text-[10.5pt]">
                    The personal data processed in this system is controlled by <strong>{{ $hospitalName }}</strong> ("Hospital"), operating as the Personal Information Controller (PIC) pursuant to Republic Act No. 10173, otherwise known as the Data Privacy Act of 2012 (DPA), and its Implementing Rules and Regulations (IRR).
                </p>
                <div class="doc-callout text-[9.5pt] border-l-4 border-primary-700 dark:border-primary-400 pl-4 py-2.5 space-y-1.5 text-neutral-900 dark:text-neutral-100">
                    <div><strong class="text-neutral-900 dark:text-white">Personal Information Controller:</strong> {{ $hospitalName }}</div>
                    <div><strong class="text-neutral-900 dark:text-white">Institutional Address:</strong> {{ config('privacy.hospital_address', 'Tala, Caloocan City, Metro Manila, Philippines') }}</div>
                    <div><strong class="text-neutral-900 dark:text-white">Data Protection Officer (DPO):</strong> {{ config('privacy.dpo_name', 'Office of the Data Protection Officer') }}</div>
                    <div><strong class="text-neutral-900 dark:text-white">DPO Contact Email:</strong> <span class="font-mono font-medium text-primary-900 dark:text-primary-300">{{ config('privacy.dpo_email', 'dpo@djnrmhs.gov.ph') }}</span></div>
                    <div><strong class="text-neutral-900 dark:text-white">Contact Telephone:</strong> {{ config('privacy.dpo_phone', '+63 (2) 8962-8209') }}</div>
                </div>
            </section>

            {{-- Section 2: The Information We Collect --}}
            <section class="mb-5 keep-together">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 mb-2.5">
                    2. The Information We Collect
                </h2>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-2.5 text-[10.5pt]">
                    <strong>{{ $hospitalName }}</strong> collects information by various methods including information actively provided by authorized personnel, system administrators, and automated operational telemetry.
                </p>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-3 text-[10.5pt]">
                    The types of personal information we collect include employee name, contact information, institutional email address, hospital employee ID number, assigned department, encrypted credentials, role permissions, and system activity logs. Account authentication credentials and security tokens are used for authentication and access control purposes only. We may record and log administrative and stock transactions for purposes of accuracy, inventory integrity, performance reviews, training, forensic accountability, and general quality assurance.
                </p>
                <div class="doc-callout text-[9.5pt] border-l-4 border-amber-600 dark:border-amber-400 pl-4 py-2.5 space-y-1 text-neutral-900 dark:text-neutral-100 mb-2">
                    <strong class="text-neutral-900 dark:text-white">Clinical Data Distinction:</strong> HIMS is a specialized logistics, procurement, and warehouse inventory management platform. It records pharmaceuticals, surgical equipment, batch numbers, expiry dates, and staff movement logs. It does <em>not</em> collect or store patient medical charts, clinical diagnoses, or patient treatment records.
                </div>
            </section>

            {{-- Section 3: How We Use This Information / Lawful Basis --}}
            <section class="mb-5 keep-together">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 mb-2.5">
                    3. How We Use This Information
                </h2>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-2.5 text-[10.5pt]">
                    This information is used to aid in the provision of hospital operations, pharmaceutical tracking, procurement workflows, stock movements, and user account governance.
                </p>
                <h3 class="doc-subsec-title text-[10.5pt] font-bold text-neutral-900 dark:text-white mb-2">
                    Lawful Basis for Processing
                </h3>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-2.5 text-[10.5pt]">
                    Under Section 12 of Republic Act No. 10173, processing of workforce information in HIMS does not rely on generic consent checkboxes because it is lawfully grounded upon:
                </p>
                <ul class="list-disc list-inside space-y-2 pl-3 text-neutral-900 dark:text-neutral-100 text-[10.5pt]">
                    <li><strong class="font-bold text-neutral-900 dark:text-white">Fulfillment of Employment / Contractual Role (Sec. 12[b]):</strong> Necessary for provisioning staff login credentials, maintaining duty assignments, and executing warehouse, procurement, or pharmacy tasks.</li>
                    <li><strong class="font-bold text-neutral-900 dark:text-white">Compliance with Legal &amp; Regulatory Obligations (Sec. 12[c]):</strong> Meeting statutory mandates of the Department of Health (DOH), Food and Drug Administration (FDA), and Commission on Audit (COA) to maintain verifiable medicine chain-of-custody.</li>
                    <li><strong class="font-bold text-neutral-900 dark:text-white">Legitimate Interests of the Health Facility (Sec. 12[f]):</strong> Safeguarding hospital assets against theft or discrepancies, ensuring supply chain continuity, and securing internal systems.</li>
                    <li><strong class="font-bold text-neutral-900 dark:text-white">Security Safeguards (Sec. 20):</strong> Capturing audit events, IP addresses, device and browser context, approximate IP-derived location, and session timestamps to prevent unauthorized access and protect data integrity. With the user's explicit browser permission, HIMS may instead retain rounded, device-reported coordinates for the current signed-in session and record them with subsequent audit events.</li>
                </ul>
            </section>

            {{-- Section 4: Who We Share This Information With --}}
            <section class="mb-5 keep-together">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 mb-2.5">
                    4. Who We Share This Information With
                </h2>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 text-[10.5pt]">
                    <strong>{{ $hospitalName }}</strong> does not share personal information with any third parties except as disclosed in this policy or required by law. <strong>{{ $hospitalName }}</strong> may provide personal information to internal institutional auditors, statutory regulatory bodies, and contracted technology service providers (which shall be bound by strict confidentiality and data protection agreements) to assist <strong>{{ $hospitalName }}</strong> in the operations disclosed herein.
                </p>
            </section>

            {{-- Section 5: Cookies & Technical Storage --}}
            <section class="mb-5 keep-together">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 mb-2.5">
                    5. Cookies &amp; Technical Storage
                </h2>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-3 text-[10.5pt]">
                    HIMS utilizes only <strong>strictly necessary technical session tokens</strong> required for authentication, CSRF security, and automated inactivity timeouts. No marketing, advertising, or third-party tracking cookies are utilized.
                </p>

                {{-- Cookies Table --}}
                <table class="doc-table w-full text-left text-[9pt] border border-neutral-600 dark:border-neutral-600 border-collapse mb-3">
                    <thead>
                        <tr class="border-b border-neutral-400 dark:border-neutral-600">
                            <th class="w-1/4 doc-meta-label">Cookie / Token</th>
                            <th class="w-1/4 doc-meta-label">Classification</th>
                            <th class="w-1/3 doc-meta-label">Purpose</th>
                            <th class="w-1/6 doc-meta-label">Duration</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-300 dark:divide-neutral-700">
                        <tr>
                            <td class="px-3 py-2 font-mono font-bold text-neutral-900 dark:text-white border-r border-neutral-400 dark:border-neutral-700">hims-session</td>
                            <td class="px-3 py-2 font-semibold text-neutral-900 dark:text-neutral-200 border-r border-neutral-400 dark:border-neutral-700">Strictly Necessary</td>
                            <td class="px-3 py-2 text-neutral-900 dark:text-neutral-100 border-r border-neutral-400 dark:border-neutral-700">Maintains authenticated staff session and CSRF protection.</td>
                            <td class="px-3 py-2 text-neutral-900 dark:text-neutral-200">Session / Idle Timeout</td>
                        </tr>
                        <tr class="doc-zebra bg-neutral-50/75 dark:bg-neutral-850/50">
                            <td class="px-3 py-2 font-mono font-bold text-neutral-900 dark:text-white border-r border-neutral-400 dark:border-neutral-700">hims_inactivity</td>
                            <td class="px-3 py-2 font-semibold text-neutral-900 dark:text-neutral-200 border-r border-neutral-400 dark:border-neutral-700">Strictly Necessary</td>
                            <td class="px-3 py-2 text-neutral-900 dark:text-neutral-100 border-r border-neutral-400 dark:border-neutral-700">Enforces automatic logout upon inactivity to safeguard hospital terminals.</td>
                            <td class="px-3 py-2 text-neutral-900 dark:text-neutral-200">Browser session</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 font-mono font-bold text-neutral-900 dark:text-white border-r border-neutral-400 dark:border-neutral-700">XSRF-TOKEN</td>
                            <td class="px-3 py-2 font-semibold text-neutral-900 dark:text-neutral-200 border-r border-neutral-400 dark:border-neutral-700">Strictly Necessary</td>
                            <td class="px-3 py-2 text-neutral-900 dark:text-neutral-100 border-r border-neutral-400 dark:border-neutral-700">Mitigates cross-site request forgery risks during state-changing requests.</td>
                            <td class="px-3 py-2 text-neutral-900 dark:text-neutral-200">Session</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            {{-- Section 6: Retention, Deactivation & Immutable Audit Trail --}}
            <section class="mb-5 keep-together">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 mb-2.5">
                    6. Retention, Deactivation &amp; Immutable Audit Trail
                </h2>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-2.5 text-[10.5pt]">
                    Personal data and administrative logs are retained in accordance with hospital governance guidelines and statutory audit obligations:
                </p>
                <ul class="list-disc list-inside space-y-2 pl-3 text-neutral-900 dark:text-neutral-100 text-[10.5pt]">
                    <li><strong class="font-bold text-neutral-900 dark:text-white">Deactivation vs. Deletion:</strong> When staff resign or transfer departments, user accounts are deactivated to immediately revoke login access. Account records and historic transaction attributions are preserved to maintain pharmaceutical custody trails.</li>
                    <li><strong class="font-bold text-neutral-900 dark:text-white">Immutable Audit Trail:</strong> All operations captured in the Audit Trail (<code class="font-mono text-xs font-bold bg-neutral-100 dark:bg-neutral-800 px-1 py-0.5 rounded text-neutral-900 dark:text-neutral-100 border border-neutral-300 dark:border-neutral-700">audit_logs</code>) are append-only. They cannot be modified or purged through the user interface, ensuring complete evidentiary reliability.</li>
                    <li><strong class="font-bold text-neutral-900 dark:text-white">Password Security:</strong> Passwords are one-way hashed using salted bcrypt and cannot be retrieved in plaintext by any user or administrator.</li>
                </ul>
            </section>

            {{-- Section 7: Security Safeguards --}}
            <section class="mb-5 keep-together">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 mb-2.5">
                    7. Security Safeguards
                </h2>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-2.5 text-[10.5pt]">
                    In adherence to National Privacy Commission recommendations and industry best practices, HIMS incorporates technical, physical, and organizational safeguards:
                </p>
                <ul class="list-disc list-inside space-y-2 pl-3 text-neutral-900 dark:text-neutral-100 text-[10.5pt]">
                    <li>Transport Layer Security (TLS) cryptographic encryption for all transmissions.</li>
                    <li>Multi-Factor Authentication (MFA) via time-based one-time password (TOTP) protocols and secure email channels.</li>
                    <li>Granular Role-Based Access Control enforcing the Principle of Least Privilege across Pharmacy, Warehouse, Management, and Administration.</li>
                    <li>Intrusion rate-limiting and automatic account lockout defenses against brute-force credential attacks.</li>
                </ul>
            </section>

            {{-- Section 8: Your Rights as a Data Subject --}}
            <section class="mb-5 keep-together">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 mb-2.5">
                    8. Your Rights as a Data Subject
                </h2>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-3 text-[10.5pt]">
                    Under Chapter VIII of Republic Act No. 10173, authorized users whose personal data is processed within HIMS are entitled to statutory rights including Information, Access, Rectification, Erasure/Deactivation, and Objection:
                </p>

                {{-- Rights Matrix Table --}}
                <table class="doc-table w-full text-left text-[9pt] border border-neutral-600 dark:border-neutral-600 border-collapse mb-3">
                    <thead>
                        <tr class="border-b border-neutral-400 dark:border-neutral-600">
                            <th class="w-1/3 doc-meta-label">Statutory Right</th>
                            <th class="w-2/3 doc-meta-label">Legal Scope &amp; Application in HIMS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-300 dark:divide-neutral-700">
                        <tr>
                            <td class="px-3 py-2 font-bold text-neutral-900 dark:text-white border-r border-neutral-400 dark:border-neutral-700">Right to be Informed (Sec. 16a)</td>
                            <td class="px-3 py-2 text-neutral-900 dark:text-neutral-100">To be notified of the nature, purpose, and legal basis of inventory data processing operations.</td>
                        </tr>
                        <tr class="doc-zebra bg-neutral-50/75 dark:bg-neutral-850/50">
                            <td class="px-3 py-2 font-bold text-neutral-900 dark:text-white border-r border-neutral-400 dark:border-neutral-700">Right to Access &amp; Portability (Sec. 16c)</td>
                            <td class="px-3 py-2 text-neutral-900 dark:text-neutral-100">To request an electronic export of your personal information recorded in the system.</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 font-bold text-neutral-900 dark:text-white border-r border-neutral-400 dark:border-neutral-700">Right to Rectification (Sec. 16d)</td>
                            <td class="px-3 py-2 text-neutral-900 dark:text-neutral-100">To dispute inaccuracy or error in your personal employee data and have it corrected.</td>
                        </tr>
                        <tr class="doc-zebra bg-neutral-50/75 dark:bg-neutral-850/50">
                            <td class="px-3 py-2 font-bold text-neutral-900 dark:text-white border-r border-neutral-400 dark:border-neutral-700">Right to File a Complaint (Sec. 16a)</td>
                            <td class="px-3 py-2 text-neutral-900 dark:text-neutral-100">To lodge a formal complaint with the National Privacy Commission (<a href="https://privacy.gov.ph" target="_blank" rel="noopener noreferrer" class="font-bold underline text-primary-900 dark:text-primary-300">privacy.gov.ph</a>).</td>
                        </tr>
                    </tbody>
                </table>

                <div class="doc-callout text-[9.5pt] border-l-4 border-primary-700 dark:border-primary-400 pl-4 py-2.5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div class="text-neutral-900 dark:text-neutral-100">
                        <strong class="font-bold text-neutral-900 dark:text-white">Exercising Your Rights:</strong> Authorized staff may submit a formal Data Subject Request directly to the Data Protection Officer through your Account Settings.
                    </div>
                    @if ($isAuth)
                        <a href="{{ route('profile.edit') }}" class="no-print inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 font-medium text-xs hover:bg-neutral-800 dark:hover:bg-neutral-100 transition shrink-0">
                            Open Profile Rights
                            <x-ui.icon name="arrow-right" class="h-3 w-3" />
                        </a>
                    @endif
                </div>
            </section>

            {{-- Section 9: Inquiries, Concerns & DPO Contact Information --}}
            <section class="mb-6 keep-together">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 mb-2.5">
                    9. Inquiries, Concerns &amp; DPO Contact Information
                </h2>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-2.5 text-[10.5pt]">
                    For inquiries concerning this Privacy Notice, the exercise of data privacy rights, or to report an information security concern, please direct communications to:
                </p>
                <div class="doc-callout text-[9.5pt] border-l-4 border-primary-700 dark:border-primary-400 pl-4 py-2.5 space-y-1.5 text-neutral-900 dark:text-neutral-100">
                    <p class="font-bold text-neutral-900 dark:text-white">{{ $hospitalName }}</p>
                    <p><strong class="font-bold text-neutral-900 dark:text-white">Office:</strong> Office of the Data Protection Officer</p>
                    <p><strong class="font-bold text-neutral-900 dark:text-white">Data Protection Officer:</strong> {{ config('privacy.dpo_name', 'Office of the Data Protection Officer') }}</p>
                    <p><strong class="font-bold text-neutral-900 dark:text-white">Email:</strong> <a href="mailto:{{ config('privacy.dpo_email', 'dpo@djnrmhs.gov.ph') }}" class="font-mono font-medium text-primary-900 dark:text-primary-300 underline">{{ config('privacy.dpo_email', 'dpo@djnrmhs.gov.ph') }}</a></p>
                    <p><strong class="font-bold text-neutral-900 dark:text-white">NPC Registration:</strong> <span class="font-mono font-medium text-neutral-900 dark:text-neutral-200">{{ config('privacy.npc_registration_number', 'PIC-2026-HIMS-001') }}</span></p>
                    <p><strong class="font-bold text-neutral-900 dark:text-white">National Privacy Commission:</strong> <span class="font-mono font-medium text-neutral-900 dark:text-neutral-200">complaints@privacy.gov.ph</span></p>
                </div>
            </section>

            {{-- Section 10: Document Control & Administrative Approval Record --}}
            <section class="keep-together doc-control-block pt-2">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white mb-2">
                    10. Document Control &amp; Approval Record
                </h2>
                <table class="doc-table w-full text-left text-[8.5pt] border border-neutral-600 dark:border-neutral-600 border-collapse">
                    <thead>
                        <tr class="border-b border-neutral-400 dark:border-neutral-600">
                            <th class="w-1/4 doc-meta-label">Role / Action</th>
                            <th class="w-1/3 doc-meta-label">Designated Office / Authority</th>
                            <th class="w-1/4 doc-meta-label">Formal Verification</th>
                            <th class="w-1/6 doc-meta-label">Action Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-300 dark:divide-neutral-700">
                        <tr>
                            <td class="px-3 py-2 font-bold uppercase text-neutral-900 dark:text-white border-r border-neutral-400 dark:border-neutral-700">Prepared By:</td>
                            <td class="px-3 py-2 text-neutral-900 dark:text-neutral-100 border-r border-neutral-400 dark:border-neutral-700">Hospital Data Privacy Compliance Team</td>
                            <td class="px-3 py-2 font-mono text-[8pt] font-medium text-neutral-900 dark:text-neutral-200 border-r border-neutral-400 dark:border-neutral-700">[Compliance Team Verified]</td>
                            <td class="px-3 py-2 font-mono text-neutral-900 dark:text-neutral-200">September 2026</td>
                        </tr>
                        <tr class="doc-zebra bg-neutral-50/75 dark:bg-neutral-850/50">
                            <td class="px-3 py-2 font-bold uppercase text-neutral-900 dark:text-white border-r border-neutral-400 dark:border-neutral-700">Reviewed By:</td>
                            <td class="px-3 py-2 text-neutral-900 dark:text-neutral-100 border-r border-neutral-400 dark:border-neutral-700">Institutional Data Protection Officer</td>
                            <td class="px-3 py-2 font-mono text-[8pt] font-medium text-neutral-900 dark:text-neutral-200 border-r border-neutral-400 dark:border-neutral-700">[DPO Statutory Review Verified]</td>
                            <td class="px-3 py-2 font-mono text-neutral-900 dark:text-neutral-200">September 2026</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 font-bold uppercase text-neutral-900 dark:text-white border-r border-neutral-400 dark:border-neutral-700">Approved By:</td>
                            <td class="px-3 py-2 text-neutral-900 dark:text-neutral-100 border-r border-neutral-400 dark:border-neutral-700">Medical Center Chief / Hospital Administrator</td>
                            <td class="px-3 py-2 font-mono text-[8pt] font-medium text-neutral-900 dark:text-neutral-200 border-r border-neutral-400 dark:border-neutral-700">[Executive Directive Approved]</td>
                            <td class="px-3 py-2 font-mono text-neutral-900 dark:text-neutral-200">September 2026</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            {{-- Document Footer Rule --}}
            <footer class="mt-8 pt-3 border-t border-neutral-400 dark:border-neutral-600 flex flex-col sm:flex-row items-center justify-between gap-2 text-[8pt] text-neutral-700 dark:text-neutral-300 font-mono">
                <div>{{ $hospitalName }} &bull; Data Privacy Policy</div>
                <div>DPA-2012-HIMS-POL &bull; Version 1.0 (Operational)</div>
                <div>Official Hospital Document</div>
            </footer>

        </main>
    </div>
</body>
</html>
