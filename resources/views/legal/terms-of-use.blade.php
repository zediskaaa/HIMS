<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    <title>Terms and Conditions · HIMS</title>

    {{-- Early zero-flicker theme script for screen viewing --}}
    @include('layouts.partials.theme-script')
    @include('layouts.partials.navigation-loading-state')

    <link rel="preconnect" href="https://fonts.bunny.net">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Official Institutional Document Engine & Print Safeguards --}}
    @include('legal.partials.official_document_styles', ['documentRef' => 'GOV-2026-HIMS-TOU'])
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
                <a href="{{ route('privacy.notice', request()->query()) }}" class="rounded-md px-3 py-1 text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100 transition-colors">Privacy Policy</a>
                <span class="rounded-md bg-neutral-100 dark:bg-neutral-800 px-3 py-1 font-semibold text-neutral-900 dark:text-neutral-100">Terms and Conditions</span>
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
                        <div><strong class="text-neutral-900 dark:text-white">REF:</strong> GOV-2026-HIMS-TOU</div>
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
                    Terms and Conditions
                </h1>
                <p class="mt-0.5 text-[9pt] font-bold uppercase tracking-widest text-neutral-700 dark:text-neutral-300">
                    System Acceptable Use &amp; Operational Governance Policy
                </p>
            </div>

            {{-- 3. Document Governance Information Block (Formal Metadata Table) --}}
            <section class="mb-6 keep-together">
                <table class="doc-table w-full text-left text-[9pt] border border-neutral-600 dark:border-neutral-600 border-collapse">
                    <tbody>
                        <tr class="border-b border-neutral-400 dark:border-neutral-600">
                            <th class="w-1/4 doc-meta-label">Document Title</th>
                            <td class="w-1/4 doc-meta-value font-semibold">Terms of Use &amp; System Governance</td>
                            <th class="w-1/4 doc-meta-label">Document Reference</th>
                            <td class="w-1/4 doc-meta-value font-mono font-bold">GOV-2026-HIMS-TOU</td>
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
                            <th class="doc-meta-label">Applicability</th>
                            <td colspan="3" class="doc-meta-value">
                                All authorized healthcare staff, pharmacy personnel, warehouse custodians, inventory managers, and system administrators.
                            </td>
                        </tr>
                        <tr>
                            <th class="doc-meta-label">Supervising Authority</th>
                            <td class="doc-meta-value">Hospital IT &amp; Materials Management Division</td>
                            <th class="doc-meta-label">Statutory Framework</th>
                            <td class="doc-meta-value font-medium">R.A. 10173, R.A. 10175, R.A. 7394</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            {{-- Document Preamble / Section 1: Terms of Use --}}
            <section class="mb-5">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 mb-2.5">
                    1. Terms of Use
                </h2>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-3 text-[10.5pt]">
                    These are the terms and conditions of <strong>{{ $hospitalName }}</strong> governing authorized access to and use of the Hospital Inventory Management System (HIMS). These Terms of Use establish the standards, access responsibilities, and operational conditions applicable to all authorized hospital staff, administrators, and designated contractors. Access to the system constitutes an agreement to strictly comply with the administrative rules and institutional directives contained herein.
                </p>
            </section>

            {{-- Section 2: Administrative and Legal Notice --}}
            <section class="mb-5 keep-together">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 mb-2.5">
                    2. Administrative and Legal Notice
                </h2>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-2.5 text-[10.5pt]">
                    These Terms of Use represent operational rules for hospital inventory and supply chain activities. Institutional policies, hospital executive directives, and applicable Philippine laws (including Republic Act No. 10173, otherwise known as the Data Privacy Act of 2012, and Republic Act No. 10175, otherwise known as the Cybercrime Prevention Act of 2012) supersede any software terms.
                </p>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 text-[10.5pt]">
                    Sections containing bracketed placeholders must be reviewed and formally authorized by <strong>Hospital Management / Institutional Legal Counsel</strong>. No individual hospital user may alter, waive, or create unilateral exceptions to these terms.
                </p>
            </section>

            {{-- Section 3: Authorized Access & Credential Responsibility --}}
            <section class="mb-5 keep-together">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 mb-2.5">
                    3. Authorized Access &amp; Credential Responsibility
                </h2>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-3 text-[10.5pt]">
                    HIMS is an internal hospital operations platform restricted exclusively to authenticated, authorized personnel of <strong>{{ $hospitalName }}</strong>. Every authorized user is subject to strict credential custody standards:
                </p>
                <div class="space-y-3 pl-3">
                    <div>
                        <h3 class="doc-subsec-title text-[10.5pt] font-bold text-neutral-900 dark:text-white">
                            3.1 Individual Responsibility
                        </h3>
                        <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 text-[10.5pt]">
                            Each user account shall be assigned to a designated individual. Users are strictly responsible for maintaining the confidentiality of their authentication credentials. Account sharing, credential disclosure, and allowing unauthorized individuals to execute transactions under one's identity are strictly prohibited under hospital administrative policy.
                        </p>
                    </div>
                    <div>
                        <h3 class="doc-subsec-title text-[10.5pt] font-bold text-neutral-900 dark:text-white">
                            3.2 Multi-Factor Authentication (MFA)
                        </h3>
                        <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 text-[10.5pt]">
                            When Multi-Factor Authentication is enabled for an account or role, users must maintain active, exclusive control of their time-based one-time password (TOTP) authenticator device or verified email communication channel. Users must promptly report lost, damaged, or compromised authenticator devices to a system administrator.
                        </p>
                    </div>
                    <div>
                        <h3 class="doc-subsec-title text-[10.5pt] font-bold text-neutral-900 dark:text-white">
                            3.3 Session Security and Terminal Discipline
                        </h3>
                        <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 text-[10.5pt]">
                            In accordance with hospital information security standards, unattended sessions expire automatically after a defined inactivity period. Users must manually log out when vacating shared hospital terminals, dispensary workstations, or warehouse handheld units.
                        </p>
                    </div>
                </div>
            </section>

            {{-- Section 4: Role-Based Authorization & Least Privilege --}}
            <section class="mb-5 keep-together">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 mb-2.5">
                    4. Role-Based Authorization &amp; Least Privilege
                </h2>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-3 text-[10.5pt]">
                    Access to specific features (such as stock adjustments, batch receipts, purchase approvals, or user management) is strictly bounded by the assigned User Role under the Principle of Least Privilege:
                </p>

                {{-- Role Scope Table --}}
                <table class="doc-table w-full text-left text-[9pt] border border-neutral-600 dark:border-neutral-600 border-collapse mb-3">
                    <thead>
                        <tr class="border-b border-neutral-400 dark:border-neutral-600">
                            <th class="w-1/4 doc-meta-label">Assigned User Role</th>
                            <th class="w-3/4 doc-meta-label">Authorized Functional Scope &amp; Boundaries</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-300 dark:divide-neutral-700">
                        <tr>
                            <td class="px-3 py-2 font-bold text-neutral-900 dark:text-white border-r border-neutral-400 dark:border-neutral-700">Pharmacy Staff</td>
                            <td class="px-3 py-2 text-neutral-900 dark:text-neutral-100">Dispensing medicines to hospital wards, viewing medication inventory balances, recording lot numbers, and reporting critical supply shortages.</td>
                        </tr>
                        <tr class="doc-zebra bg-neutral-50/75 dark:bg-neutral-850/50">
                            <td class="px-3 py-2 font-bold text-neutral-900 dark:text-white border-r border-neutral-400 dark:border-neutral-700">Warehouse Staff</td>
                            <td class="px-3 py-2 text-neutral-900 dark:text-neutral-100">Managing physical stock movements, staging deliveries, receiving supplier shipments, conducting bin putaway, lot tracking, and executing replenishment tasks.</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 font-bold text-neutral-900 dark:text-white border-r border-neutral-400 dark:border-neutral-700">Inventory Managers</td>
                            <td class="px-3 py-2 text-neutral-900 dark:text-neutral-100">Maintaining master item catalogs, authorizing procurement requests, reviewing supplier price quotes, generating demand forecasts, and approving verified stock adjustments.</td>
                        </tr>
                        <tr class="doc-zebra bg-neutral-50/75 dark:bg-neutral-850/50">
                            <td class="px-3 py-2 font-bold text-neutral-900 dark:text-white border-r border-neutral-400 dark:border-neutral-700">System Administrators</td>
                            <td class="px-3 py-2 text-neutral-900 dark:text-neutral-100">User account provisioning, institutional role assignment, system security oversight, audit trail inspection, and technical maintenance.</td>
                        </tr>
                    </tbody>
                </table>

                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-200 text-[9.5pt]">
                    Attempting to bypass role boundaries, access unauthorized modules, or tamper with security checks violates institutional governance policy and the Cybercrime Prevention Act of 2012 (Republic Act No. 10175).
                </p>
            </section>

            {{-- Section 5: Data Accuracy & Supply Chain Accountability --}}
            <section class="mb-5 keep-together">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 mb-2.5">
                    5. Data Accuracy &amp; Supply Chain Accountability
                </h2>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-2.5 text-[10.5pt]">
                    Because HIMS manages vital medical commodities, emergency pharmaceuticals, and surgical supplies, accurate record-keeping directly impacts patient care and public safety:
                </p>
                <div class="space-y-3 pl-3">
                    <div>
                        <h3 class="doc-subsec-title text-[10.5pt] font-bold text-neutral-900 dark:text-white">
                            5.1 Accurate Commodity Recording
                        </h3>
                        <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 text-[10.5pt]">
                            Users must record accurate quantities, valid manufacturer batch and lot numbers, exact expiration dates, and truthful transaction reasons. Falsification, intentional misrecording, or negligent entry of inventory levels constitutes severe administrative misconduct.
                        </p>
                    </div>
                    <div>
                        <h3 class="doc-subsec-title text-[10.5pt] font-bold text-neutral-900 dark:text-white">
                            5.2 Physical Stock Adjustments
                        </h3>
                        <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 text-[10.5pt]">
                            Balance adjustments following cycle counts or physical inventories must reflect verified physical stock counts and strictly adhere to hospital audit governance and dual-custody verification protocols.
                        </p>
                    </div>
                    <div>
                        <h3 class="doc-subsec-title text-[10.5pt] font-bold text-neutral-900 dark:text-white">
                            5.3 Procurement Integrity
                        </h3>
                        <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 text-[10.5pt]">
                            Procurement requests, supplier quotations, and purchase order records must adhere to hospital procurement standards, Commission on Audit (COA) rules, and statutory government procurement regulations (Republic Act No. 9184) where applicable.
                        </p>
                    </div>
                </div>
            </section>

            {{-- Section 6: System Monitoring & Audit Logging Notice --}}
            <section class="mb-5 keep-together">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 mb-2.5">
                    6. System Monitoring &amp; Audit Logging Notice
                </h2>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-2.5 text-[10.5pt]">
                    Users are explicitly advised that all system activities within HIMS are actively monitored, recorded, and attributable:
                </p>
                <ul class="list-disc list-inside space-y-2 pl-3 text-neutral-900 dark:text-neutral-100 text-[10.5pt]">
                    <li>
                        <strong>Immutable Audit Trail:</strong> An append-only audit trail records the identity of the actor, employee ID number, exact action taken, target record, old and new values, client IP address, device and browser context, approximate location, and timestamp.
                    </li>
                    <li>
                        <strong>Evidence Preservation:</strong> Audit logs are preserved permanently for administrative accountability, forensic fraud investigation, and statutory oversight.
                    </li>
                    <li>
                        <strong>No Expectation of Privacy:</strong> No expectation of personal privacy exists with respect to operational or inventory transactions conducted within the hospital inventory system.
                    </li>
                </ul>
            </section>

            {{-- Section 7: Account Lifecycle & Offboarding --}}
            <section class="mb-5 keep-together">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 mb-2.5">
                    7. Account Lifecycle &amp; Offboarding
                </h2>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-2.5 text-[10.5pt]">
                    Upon a change of clinical role, inter-departmental transfer, resignation, retirement, or termination of employment:
                </p>
                <ul class="list-disc list-inside space-y-2 pl-3 text-neutral-900 dark:text-neutral-100 text-[10.5pt]">
                    <li>
                        <strong>Access Termination:</strong> System accounts are immediately deactivated by hospital administrators to prevent unauthorized access.
                    </li>
                    <li>
                        <strong>Preservation of Chain-of-Custody:</strong> Historical inventory records, transaction ledgers, and electronic signatures naming the employee as author or custodian are permanently retained to preserve clinical traceability and audit integrity.
                    </li>
                </ul>
            </section>

            {{-- Section 8: Operational Nature & No Consumer Transactions --}}
            <section class="mb-5 keep-together">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 mb-2.5">
                    8. Operational Nature &amp; No Consumer Transactions
                </h2>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 text-[10.5pt]">
                    HIMS is strictly an enterprise institutional management platform. It does not provide consumer retail sales, customer subscriptions, or public payment processing services. Provisions of the Consumer Act of the Philippines (Republic Act No. 7394) concerning commercial consumer transactions, refunds, and warranties do not apply to the internal operational functions of this hospital platform.
                </p>
            </section>

            {{-- Section 9: Institutional Governance & Administration Contact --}}
            <section class="mb-6 keep-together">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 mb-2.5">
                    9. Institutional Governance &amp; Administration Contact
                </h2>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-2.5 text-[10.5pt]">
                    For inquiries regarding access permissions, credential resets, account provisioning, or policy interpretation, official communications should be directed to:
                </p>
                <div class="doc-callout text-[9.5pt] border-l-4 border-primary-700 dark:border-primary-400 pl-4 py-2.5 space-y-1.5 text-neutral-900 dark:text-neutral-100">
                    <div><strong class="text-neutral-900 dark:text-white">Hospital IT Helpdesk:</strong> <span class="font-mono font-medium text-primary-900 dark:text-primary-300">[it-helpdesk@hospital.gov.ph / support@hospital.org]</span></div>
                    <div><strong class="text-neutral-900 dark:text-white">System Administrator:</strong> <span class="font-mono font-medium text-primary-900 dark:text-primary-300">[admin@hospital.gov.ph]</span></div>
                    <div><strong class="text-neutral-900 dark:text-white">Supervising Office:</strong> Hospital IT &amp; Materials Management Information Systems Department</div>
                    <div><strong class="text-neutral-900 dark:text-white">Data Protection Officer:</strong> {{ config('privacy.dpo_name', 'Office of the Data Protection Officer') }} (<span class="font-mono font-medium text-primary-900 dark:text-primary-300">{{ config('privacy.dpo_email', 'dpo@djnrmhs.gov.ph') }}</span>)</div>
                </div>
            </section>

            {{-- Section 10: Formal User Acknowledgment and Undertaking --}}
            <section class="mb-7 keep-together doc-sig-block border-t-2 border-neutral-900 dark:border-neutral-100 pt-4">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white mb-2">
                    10. User Acknowledgment and Compliance Undertaking
                </h2>
                <p class="doc-body-p text-justify text-neutral-900 dark:text-neutral-100 mb-4 text-[10pt]">
                    I hereby acknowledge that I have read, understood, and agree to strictly comply with the HIMS Terms and Conditions, institutional operational governance policies, and statutory mandates governing the custody of hospital inventory. I understand that violation of these terms may result in administrative disciplinary action, revocation of system access, and legal prosecution under applicable Philippine laws.
                </p>

                {{-- Printable Signature Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 pt-2 text-[9pt]">
                    <div>
                        <div class="border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 h-8 flex items-end">
                            @if($isAuth)
                                <span class="font-bold text-neutral-900 dark:text-white">{{ auth()->user()->name }}</span>
                            @endif
                        </div>
                        <p class="text-[8pt] font-bold uppercase text-neutral-800 dark:text-neutral-300 mt-1">Printed Name of Authorized Personnel</p>
                    </div>

                    <div>
                        <div class="border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 h-8 flex items-end">
                            @if($isAuth && auth()->user()->employee_id)
                                <span class="font-mono font-bold text-neutral-900 dark:text-white">{{ auth()->user()->employee_id }}</span>
                            @endif
                        </div>
                        <p class="text-[8pt] font-bold uppercase text-neutral-800 dark:text-neutral-300 mt-1">Employee ID Number</p>
                    </div>

                    <div>
                        <div class="border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 h-8 flex items-end">
                            @if($isAuth && auth()->user()->department)
                                <span class="font-semibold text-neutral-900 dark:text-white">{{ auth()->user()->department }}</span>
                            @endif
                        </div>
                        <p class="text-[8pt] font-bold uppercase text-neutral-800 dark:text-neutral-300 mt-1">Designated Department / Unit</p>
                    </div>

                    <div>
                        <div class="border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 h-8 flex items-end">
                            @if($isAuth)
                                <span class="font-mono text-[8.5pt] font-bold text-primary-900 dark:text-primary-300">[Authenticated HIMS User Signature on File]</span>
                            @endif
                        </div>
                        <p class="text-[8pt] font-bold uppercase text-neutral-800 dark:text-neutral-300 mt-1">Signature of Personnel</p>
                    </div>

                    <div class="sm:col-span-2 sm:w-1/2">
                        <div class="border-b-2 border-neutral-900 dark:border-neutral-100 pb-1 h-8 flex items-end">
                            <span class="font-mono font-bold text-neutral-900 dark:text-white">{{ date('F d, Y') }}</span>
                        </div>
                        <p class="text-[8pt] font-bold uppercase text-neutral-800 dark:text-neutral-300 mt-1">Date Signed</p>
                    </div>
                </div>
            </section>

            {{-- Section 11: Document Control & Administrative Approval Record --}}
            <section class="keep-together doc-control-block pt-2">
                <h2 class="doc-sec-title text-[12.5pt] font-bold uppercase tracking-wider text-neutral-900 dark:text-white mb-2">
                    11. Document Control &amp; Approval Record
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
                            <td class="px-3 py-2 text-neutral-900 dark:text-neutral-100 border-r border-neutral-400 dark:border-neutral-700">Hospital IT &amp; Materials Management Division</td>
                            <td class="px-3 py-2 font-mono text-[8pt] font-medium text-neutral-900 dark:text-neutral-200 border-r border-neutral-400 dark:border-neutral-700">[Technical Custodian]</td>
                            <td class="px-3 py-2 font-mono text-neutral-900 dark:text-neutral-200">September 2026</td>
                        </tr>
                        <tr class="doc-zebra bg-neutral-50/75 dark:bg-neutral-850/50">
                            <td class="px-3 py-2 font-bold uppercase text-neutral-900 dark:text-white border-r border-neutral-400 dark:border-neutral-700">Reviewed By:</td>
                            <td class="px-3 py-2 text-neutral-900 dark:text-neutral-100 border-r border-neutral-400 dark:border-neutral-700">Office of the Legal Counsel &amp; Compliance Officer</td>
                            <td class="px-3 py-2 font-mono text-[8pt] font-medium text-neutral-900 dark:text-neutral-200 border-r border-neutral-400 dark:border-neutral-700">[Legal Compliance Verified]</td>
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
                <div>{{ $hospitalName }} &bull; HIMS Governance Policy</div>
                <div>GOV-2026-HIMS-TOU &bull; Version 1.0 (Operational)</div>
                <div>Official Hospital Document</div>
            </footer>

        </main>
    </div>
</body>
</html>
