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

    <link rel="preconnect" href="https://fonts.bunny.net">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        @media print {
            body {
                background: #ffffff !important;
                color: #1a1a1a !important;
            }
            .no-print {
                display: none !important;
            }
            .doc-sheet {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body class="min-h-full bg-[#F4F5F7] font-sans text-[#4A453E] antialiased selection:bg-[#B07E48] selection:text-white">
    <div class="relative min-h-screen py-6 sm:py-10 px-4 sm:px-6 lg:px-8">

        {{-- Top Utility Bar (Screen only) --}}
        <div class="no-print mx-auto mb-6 flex max-w-4xl flex-wrap items-center justify-between gap-3 text-xs font-medium">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-1.5 text-neutral-600 hover:text-neutral-900 transition-colors">
                <x-ui.icon name="arrow-left" class="h-3.5 w-3.5" />
                <span>Return to Home</span>
            </a>

            <div class="inline-flex items-center rounded-lg bg-white p-1 shadow-sm ring-1 ring-neutral-200">
                <a href="{{ route('privacy.notice') }}" class="rounded-md px-3 py-1 text-neutral-600 hover:text-neutral-900 transition-colors">Privacy Policy</a>
                <span class="rounded-md bg-neutral-100 px-3 py-1 font-semibold text-neutral-900">Terms and Conditions</span>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-neutral-700 hover:bg-neutral-50 shadow-sm transition-colors">
                    <x-ui.icon name="printer" class="h-3.5 w-3.5" />
                    <span>Print / PDF</span>
                </button>
                <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-white hover:bg-primary-700 shadow-sm transition-colors">
                    <x-ui.icon name="arrow-right-on-rectangle" class="h-3.5 w-3.5" />
                    <span>Staff Portal</span>
                </a>
            </div>
        </div>

        {{-- Printable Document Sheet --}}
        <main class="doc-sheet mx-auto max-w-4xl rounded-lg border border-[#E5E7EB] bg-white p-8 shadow-sm sm:p-14 md:p-16">

            {{-- Document Header with Logo & Tagline (Template Style) --}}
            <header class="mb-6">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5">
                        <img src="{{ asset('img/hims-logo.png') }}" alt="HIMS Logo" class="h-10 w-10 object-contain" />
                        <div>
                            <span class="block text-[11px] font-semibold tracking-[0.22em] text-[#8C93A0] uppercase">
                                Hospital Inventory Management System
                            </span>
                            <span class="block text-xl sm:text-2xl font-bold tracking-[0.14em] text-[#19428F] uppercase">
                                HIMS HEALTHCARE
                            </span>
                        </div>
                    </div>
                    <div class="hidden sm:block text-right text-[11px] text-neutral-500 font-mono">
                        <div>REF: GOV-2026-HIMS-TOU</div>
                        <div>VER: 1.0 (Operational)</div>
                    </div>
                </div>

                {{-- Full-width divider line --}}
                <div class="mt-4 h-[1.5px] w-full bg-[#DCE1E8]"></div>
            </header>

            {{-- Document Title --}}
            <div class="mb-6">
                <h1 class="text-3xl sm:text-[34px] font-normal tracking-tight text-[#4A453E]">
                    Terms and Conditions
                </h1>
                <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wider text-[#8A7968]">
                    <span>Terms of Use</span>
                    <span>&bull;</span>
                    <span>System Acceptable Use &amp; Operational Governance</span>
                    <span>&bull;</span>
                    <span>Effective: September 2026</span>
                </div>
            </div>

            {{-- Lead / Introduction Paragraph (Template Style) --}}
            <div class="mb-6 text-[15px] sm:text-base leading-relaxed text-[#4A453E]">
                <p>
                    These are the terms and conditions of <strong class="text-neutral-800">[Hospital / Healthcare Facility Name]</strong> governing authorized access to and use of the Hospital Inventory Management System (HIMS). These Terms of Use establish the standards, access responsibilities, and operational conditions applicable to all authorized hospital staff, administrators, and designated contractors.
                </p>
            </div>

            {{-- Document Body with Bronze Headings (#B07E48) --}}
            <div class="space-y-7 text-[15px] sm:text-base leading-relaxed text-[#4A453E]">

                {{-- 1. Administrative and Legal Notice --}}
                <section>
                    <h2 class="text-xl sm:text-[22px] font-medium text-[#B07E48] mb-2.5">
                        Administrative and Legal Notice
                    </h2>
                    <p class="mb-3">
                        These Terms of Use represent operational rules for hospital inventory and supply chain activities. Institutional policies, hospital executive directives, and applicable Philippine laws (including Republic Act No. 10173 and Republic Act No. 10175) supersede any software terms. Sections containing bracketed placeholders must be reviewed and formally authorized by <strong class="text-neutral-800">[Hospital Management / Institutional Legal Counsel]</strong>.
                    </p>
                    <div class="rounded-md border border-[#E5E7EB] bg-[#F9FAFB] p-4 text-xs sm:text-sm space-y-1.5 text-neutral-700">
                        <p><strong class="text-neutral-900">Governing Institution:</strong> [Hospital / Healthcare Facility Name]</p>
                        <p><strong class="text-neutral-900">Applicability:</strong> All authorized healthcare staff, pharmacy personnel, warehouse custodians, and system administrators.</p>
                        <p><strong class="text-neutral-900">Supervising Authority:</strong> Hospital IT &amp; Materials Management Division</p>
                    </div>
                </section>

                {{-- 2. Authorized Access & Credential Responsibility --}}
                <section>
                    <h2 class="text-xl sm:text-[22px] font-medium text-[#B07E48] mb-2.5">
                        Authorized Access &amp; Credential Responsibility
                    </h2>
                    <p class="mb-3">
                        HIMS is an internal hospital operations platform restricted exclusively to authenticated, authorized personnel of <strong class="text-neutral-800">[Hospital / Healthcare Facility Name]</strong>.
                    </p>
                    <ul class="list-disc list-inside space-y-1.5 pl-2 text-neutral-700 text-sm sm:text-[15px]">
                        <li><strong class="text-neutral-900">Individual Responsibility:</strong> User accounts are assigned individually to designated personnel. Users must maintain strict confidentiality of their login credentials. Account sharing is strictly prohibited under hospital policy.</li>
                        <li><strong class="text-neutral-900">Multi-Factor Authentication (MFA):</strong> When enabled for your account or role, you must maintain active access to your authenticator application (TOTP) or verified email channel and promptly notify an administrator if an authenticator device is lost or compromised.</li>
                        <li><strong class="text-neutral-900">Session Security:</strong> In accordance with hospital information security standards, unattended sessions expire automatically after a defined inactivity period. Users must manually log out when vacating shared hospital terminals.</li>
                    </ul>
                </section>

                {{-- 3. Role-Based Authorization & Least Privilege --}}
                <section>
                    <h2 class="text-xl sm:text-[22px] font-medium text-[#B07E48] mb-2.5">
                        Role-Based Authorization &amp; Least Privilege
                    </h2>
                    <p class="mb-3">
                        Access to specific features (such as stock adjustments, batch receipts, purchase approvals, or user management) is strictly bounded by the assigned User Role:
                    </p>
                    <div class="grid gap-2.5 sm:grid-cols-2 text-xs sm:text-sm mb-3">
                        <div class="rounded border border-[#E5E7EB] bg-[#F9FAFB] p-3">
                            <strong class="text-neutral-900 block mb-1">Pharmacy Staff</strong>
                            <span>Dispensing medicines to hospital wards, viewing medication inventory, and reporting critical supplies.</span>
                        </div>
                        <div class="rounded border border-[#E5E7EB] bg-[#F9FAFB] p-3">
                            <strong class="text-neutral-900 block mb-1">Warehouse Staff</strong>
                            <span>Stock movements, receiving supplier deliveries, lot tracking, and acknowledging replenishment alerts.</span>
                        </div>
                        <div class="rounded border border-[#E5E7EB] bg-[#F9FAFB] p-3">
                            <strong class="text-neutral-900 block mb-1">Inventory Managers</strong>
                            <span>Master item catalog maintenance, procurement requests, supplier quotes, demand forecasting, and stock adjustments.</span>
                        </div>
                        <div class="rounded border border-[#E5E7EB] bg-[#F9FAFB] p-3">
                            <strong class="text-neutral-900 block mb-1">System Administrators</strong>
                            <span>Account provisioning, role assignments, security oversight, and technical system maintenance.</span>
                        </div>
                    </div>
                    <p class="text-xs sm:text-sm text-neutral-600">
                        Attempting to bypass role boundaries, access unauthorized modules, or tamper with security checks violates the Cybercrime Prevention Act of 2012 (Republic Act No. 10175).
                    </p>
                </section>

                {{-- 4. Data Accuracy & Supply Chain Accountability --}}
                <section>
                    <h2 class="text-xl sm:text-[22px] font-medium text-[#B07E48] mb-2.5">
                        Data Accuracy &amp; Supply Chain Accountability
                    </h2>
                    <p class="mb-3">
                        Because HIMS manages vital medical commodities, emergency pharmaceuticals, and surgical supplies:
                    </p>
                    <ul class="list-disc list-inside space-y-1.5 pl-2 text-neutral-700 text-sm sm:text-[15px]">
                        <li><strong class="text-neutral-900">Accurate Recording:</strong> Users must record accurate quantities, valid batch numbers, exact expiry dates, and truthful movement reasons. Falsification or intentional misrecording of inventory levels is grounds for administrative disciplinary and legal action.</li>
                        <li><strong class="text-neutral-900">Stock Adjustments:</strong> Balance adjustments following cycle counts must reflect verified physical stock and comply with hospital audit governance protocols.</li>
                        <li><strong class="text-neutral-900">Procurement Integrity:</strong> Procurement requests, quotations, and purchase order records must adhere to hospital procurement standards and statutory public bidding or purchasing regulations where applicable.</li>
                    </ul>
                </section>

                {{-- 5. System Monitoring & Audit Logging Notice --}}
                <section>
                    <h2 class="text-xl sm:text-[22px] font-medium text-[#B07E48] mb-2.5">
                        System Monitoring &amp; Audit Logging Notice
                    </h2>
                    <p class="mb-3">
                        Users are explicitly advised that all system activities are actively monitored and recorded:
                    </p>
                    <ul class="list-disc list-inside space-y-1.5 pl-2 text-neutral-700 text-sm sm:text-[15px]">
                        <li>An immutable, append-only Audit Trail records the identity of the actor, employee ID, action taken, target record, old and new values, client IP address, and timestamp.</li>
                        <li>Log records are preserved for accountability, fraud prevention, forensic investigation, and regulatory compliance.</li>
                        <li>No expectation of personal privacy exists with respect to operational transactions conducted within the hospital inventory system.</li>
                    </ul>
                </section>

                {{-- 6. Account Lifecycle & Offboarding --}}
                <section>
                    <h2 class="text-xl sm:text-[22px] font-medium text-[#B07E48] mb-2.5">
                        Account Lifecycle &amp; Offboarding
                    </h2>
                    <p class="mb-3">
                        Upon change of role, department transfer, resignation, or termination of employment:
                    </p>
                    <ul class="list-disc list-inside space-y-1.5 pl-2 text-neutral-700 text-sm sm:text-[15px]">
                        <li>System accounts are deactivated by hospital administrators to terminate system access immediately.</li>
                        <li>Historical records naming the employee as the author of inventory transactions or audit events are retained to preserve chain-of-custody integrity.</li>
                    </ul>
                </section>

                {{-- 7. Operational Nature & No Consumer Transactions --}}
                <section>
                    <h2 class="text-xl sm:text-[22px] font-medium text-[#B07E48] mb-2.5">
                        Operational Nature &amp; No Consumer Transactions
                    </h2>
                    <p class="mb-3">
                        HIMS is an enterprise operational management platform and does not offer consumer sales, customer subscriptions, or public payment processing services. Consumer Act (Republic Act No. 7394) provisions regarding commercial retail transactions, refunds, and consumer warranties are not applicable to the internal software functions of this system.
                    </p>
                </section>

                {{-- 8. Institutional Governance & Contact Information --}}
                <section>
                    <h2 class="text-xl sm:text-[22px] font-medium text-[#B07E48] mb-2.5">
                        Institutional Governance &amp; Administration Contact
                    </h2>
                    <p class="mb-3">
                        For inquiries regarding access permissions, account provisioning, or policy interpretation:
                    </p>
                    <div class="rounded-md border border-[#E5E7EB] bg-[#F9FAFB] p-4 text-xs sm:text-sm space-y-1 text-neutral-700">
                        <p class="font-semibold text-neutral-900">[HIMS Administration / Hospital IT Department]</p>
                        <p><strong>Hospital IT Helpdesk:</strong> <span class="font-mono text-[#19428F]">[it-helpdesk@hospital.gov.ph / support@hospital.org]</span></p>
                        <p><strong>System Administrator:</strong> <span class="font-mono text-[#19428F]">[admin@hospital.gov.ph]</span></p>
                        <p><strong>Office:</strong> [Hospital IT / Management Information Systems Department]</p>
                    </div>
                </section>

            </div>

            {{-- Document Footer --}}
            <footer class="mt-12 pt-6 border-t border-[#DCE1E8] flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-neutral-500">
                <p>&copy; {{ date('Y') }} [Hospital / Healthcare Facility Name] &bull; Hospital Inventory Management System.</p>
                <div class="flex items-center gap-4">
                    <a href="{{ route('privacy.notice') }}" class="hover:text-neutral-800 transition-colors">Privacy Notice</a>
                    <a href="{{ url('/') }}" class="hover:text-neutral-800 transition-colors">Home</a>
                    <a href="{{ route('login') }}" class="hover:text-neutral-800 transition-colors">Staff Login</a>
                </div>
            </footer>

        </main>
    </div>
</body>
</html>
