<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    <title>Terms of Use · HIMS</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-neutral-900 font-sans text-neutral-200 antialiased selection:bg-primary-500 selection:text-white">
    <div class="relative min-h-screen overflow-hidden">
        {{-- Subtle background decoration --}}
        <div class="absolute inset-0 z-0 pointer-events-none" aria-hidden="true">
            <div class="absolute inset-0 bg-neutral-950/90"></div>
            <div class="absolute -left-24 top-20 h-96 w-96 rounded-full bg-primary-900/20 blur-3xl"></div>
            <div class="absolute right-10 top-1/3 h-80 w-80 rounded-full bg-primary-800/10 blur-3xl"></div>
        </div>

        <div class="relative z-10 mx-auto flex min-h-screen w-full max-w-4xl flex-col px-5 py-8 sm:px-8 lg:px-10">
            {{-- Top Navigation --}}
            <header class="flex items-center justify-between border-b border-white/10 pb-6">
                <a href="{{ url('/') }}" class="group flex items-center gap-3 rounded-lg focus-visible:ring-offset-neutral-950">
                    <img src="{{ asset('img/hims-logo.png') }}" alt="" class="h-10 w-10 rounded-lg bg-white object-cover ring-1 ring-inset ring-white/20 transition duration-300 group-hover:scale-105" />
                    <span>
                        <span class="block text-base font-semibold tracking-tight text-white">HIMS</span>
                        <span class="block text-[11px] font-medium uppercase tracking-[0.14em] text-neutral-400">Hospital Inventory Management System</span>
                    </span>
                </a>

                <div class="flex items-center gap-4 text-xs font-medium">
                    <a href="{{ route('privacy.notice') }}" class="text-neutral-400 hover:text-white transition-colors">Privacy Notice</a>
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-white/15 bg-white/5 px-3 py-1.5 text-neutral-300 hover:bg-white/10 hover:text-white transition-colors">
                        <x-ui.icon name="arrow-right-on-rectangle" class="h-3.5 w-3.5" />
                        Staff Login
                    </a>
                </div>
            </header>

            {{-- Main Content --}}
            <main class="flex-1 py-10 sm:py-12">
                {{-- Header section --}}
                <div class="border-b border-white/10 pb-8">
                    <div class="inline-flex items-center gap-2 rounded-full border border-primary-400/30 bg-primary-500/10 px-3 py-1 text-xs font-medium text-primary-300">
                        <x-ui.icon name="document-text" class="h-3.5 w-3.5" />
                        System Acceptable Use &amp; Operational Governance
                    </div>
                    <h1 class="mt-4 text-3xl font-bold tracking-tight text-white sm:text-4xl">Terms of Use</h1>
                    <p class="mt-3 text-sm text-neutral-400 sm:text-base leading-relaxed">
                        These Terms of Use establish the standards, access responsibilities, and operational conditions governing authorized access to the Hospital Information Management System (HIMS).
                    </p>
                    <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-neutral-400">
                        <span><strong>Effective Date:</strong> September 2026</span>
                        <span>&middot;</span>
                        <span><strong>Applicability:</strong> All Authorized Hospital Staff, Contractors &amp; Administrators</span>
                    </div>
                </div>

                {{-- Legal Advisory Notice --}}
                <div class="my-8 rounded-xl border border-warning-500/30 bg-warning-500/10 p-4 text-xs leading-relaxed text-warning-200">
                    <div class="flex items-start gap-3">
                        <x-ui.icon name="information-circle" class="h-5 w-5 shrink-0 text-warning-400" />
                        <div>
                            <p class="font-semibold text-warning-300">Administrative and Legal Notice</p>
                            <p class="mt-1">
                                These Terms of Use represent operational rules for hospital inventory and supply chain activities. They do not constitute legal advice or guarantee regulatory compliance on their own. Institutional policies, hospital management directives, and applicable Philippine law (including RA 10173 and RA 10175) supersede any software terms. Sections containing bracketed placeholders must be reviewed and formally authorized by <span class="font-mono text-white">[ORGANIZATION LEGAL COUNSEL / HOSPITAL MANAGEMENT]</span>.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Policy Content Body --}}
                <div class="space-y-10 text-sm leading-7 text-neutral-300">

                    {{-- 1. Authorized Access & Account Security --}}
                    <section class="space-y-3">
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-primary-500/20 text-xs font-bold text-primary-400">1</span>
                            Authorized Access &amp; Credential Responsibility
                        </h2>
                        <p>
                            HIMS is an internal hospital operations platform restricted exclusively to authenticated, authorized personnel of <strong class="text-white">[ORGANIZATION NAME]</strong>.
                        </p>
                        <ul class="list-disc list-inside space-y-1 pl-2 text-neutral-300">
                            <li><strong>Individual Responsibility:</strong> User accounts are assigned individually to designated personnel. Users must maintain strict confidentiality of their login credentials. Account sharing is strictly prohibited under hospital policy.</li>
                            <li><strong>Multi-Factor Authentication (MFA):</strong> When enabled for your account or role, you must maintain active access to your authenticator application (TOTP) or verified email channel and promptly notify an administrator if an authenticator device is lost or compromised.</li>
                            <li><strong>Session Security:</strong> In accordance with hospital information security standards, unattended sessions expire automatically after a defined inactivity period. Users must manually log out when vacating shared hospital terminals.</li>
                        </ul>
                    </section>

                    {{-- 2. Role-Based Boundaries & Principle of Least Privilege --}}
                    <section class="space-y-3">
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-primary-500/20 text-xs font-bold text-primary-400">2</span>
                            Role-Based Authorization &amp; Least Privilege
                        </h2>
                        <p>
                            Access to specific features (such as stock adjustments, batch receipts, purchase approvals, or user management) is strictly bounded by the assigned User Role:
                        </p>
                        <div class="grid gap-2 sm:grid-cols-2 text-xs pt-1">
                            <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                                <strong class="text-white">Pharmacy Staff:</strong> Dispensing to hospital wards and viewing medication inventory.
                            </div>
                            <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                                <strong class="text-white">Warehouse Staff:</strong> Stock movement, receiving deliveries, acknowledging stock alerts.
                            </div>
                            <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                                <strong class="text-white">Inventory Managers:</strong> Master item catalog, procurement, demand forecasting, stock adjustments.
                            </div>
                            <div class="rounded-lg border border-white/10 bg-white/5 p-3">
                                <strong class="text-white">Administrators:</strong> Account provisioning, role assignments, system maintenance.
                            </div>
                        </div>
                        <p class="pt-1 text-xs text-neutral-400">
                            Attempting to bypass role boundaries, access unauthorized modules, or tamper with security checks violates the Cybercrime Prevention Act of 2012 (Republic Act No. 10175).
                        </p>
                    </section>

                    {{-- 3. Data Integrity & Supply Chain Accountability --}}
                    <section class="space-y-3">
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-primary-500/20 text-xs font-bold text-primary-400">3</span>
                            Data Accuracy &amp; Supply Chain Accountability
                        </h2>
                        <p>
                            Because HIMS manages vital medical commodities, emergency supplies, and pharmaceuticals:
                        </p>
                        <ul class="list-disc list-inside space-y-1 pl-2 text-neutral-300">
                            <li><strong>Accurate Reporting:</strong> Users must record accurate quantities, valid batch numbers, exact expiry dates, and truthful movement causes. Falsification or intentional misrecording of inventory levels is grounds for disciplinary and legal action.</li>
                            <li><strong>Stock Adjustments:</strong> Balance adjustments following cycle counts must reflect actual physical counts and comply with inventory governance protocols.</li>
                            <li><strong>Procurement Integrity:</strong> Procurement requests, quotations, and purchase order records must adhere to hospital procurement guidelines and statutory public bidding or purchasing regulations where applicable.</li>
                        </ul>
                    </section>

                    {{-- 4. Audit Logging & Non-Repudiation --}}
                    <section class="space-y-3">
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-primary-500/20 text-xs font-bold text-primary-400">4</span>
                            System Monitoring &amp; Audit Logging Notice
                        </h2>
                        <p>
                            Users are advised that all system activities are actively monitored and recorded:
                        </p>
                        <ul class="list-disc list-inside space-y-1 pl-2 text-neutral-300">
                            <li>An immutable, append-only Audit Trail records the identity of the actor, employee ID, action taken, target record, old and new values, client IP address, and timestamp.</li>
                            <li>Log records are preserved for accountability, fraud prevention, forensic investigation, and regulatory compliance.</li>
                            <li>No expectation of privacy exists with respect to operational transactions conducted within the hospital inventory system.</li>
                        </ul>
                    </section>

                    {{-- 5. Account Lifecycle & Deactivation --}}
                    <section class="space-y-3">
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-primary-500/20 text-xs font-bold text-primary-400">5</span>
                            Account Lifecycle &amp; Offboarding
                        </h2>
                        <p>
                            Upon change of role, transfer, resignation, or termination of employment:
                        </p>
                        <ul class="list-disc list-inside space-y-1 pl-2 text-neutral-300">
                            <li>System accounts are deactivated by hospital administrators to terminate system access immediately.</li>
                            <li>Historical records naming the employee as the author of inventory transactions or audit events are retained to preserve chain-of-custody integrity.</li>
                        </ul>
                    </section>

                    {{-- 6. Disclaimer of Commercial E-Commerce / Consumer Features --}}
                    <section class="space-y-3">
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-primary-500/20 text-xs font-bold text-primary-400">6</span>
                            Operational Nature / No Consumer Transactions
                        </h2>
                        <p>
                            HIMS is an enterprise resource management platform and does not offer consumer sales, customer subscriptions, or payment processing services. Consumer Act (RA 7394) provisions regarding commercial retail transactions, refunds, and consumer warranties are not applicable to the internal software functions of this system.
                        </p>
                    </section>

                    {{-- 7. Governance & Contact --}}
                    <section class="space-y-3">
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-primary-500/20 text-xs font-bold text-primary-400">7</span>
                            Institutional Governance &amp; Administration Contact
                        </h2>
                        <p>
                            For inquiries regarding access permissions, account provisioning, or policy interpretation:
                        </p>
                        <div class="rounded-lg border border-white/10 bg-white/5 p-4 text-xs space-y-1.5 text-neutral-300">
                            <p class="text-white font-medium">[HIMS Administration / Hospital IT Department]</p>
                            <p><strong>Hospital IT Helpdesk:</strong> [it-helpdesk@hospital.gov.ph / support@hospital.org]</p>
                            <p><strong>System Administrator:</strong> [admin@hospital.gov.ph]</p>
                            <p><strong>Office:</strong> [Hospital IT / Management Information Systems Department]</p>
                        </div>
                    </section>

                </div>
            </main>

            {{-- Footer --}}
            <footer class="mt-12 flex flex-col gap-3 border-t border-white/10 py-6 text-xs text-neutral-500 sm:flex-row sm:items-center sm:justify-between">
                <p>&copy; {{ date('Y') }} HIMS — Hospital Supply Chain &amp; Inventory Management System.</p>
                <div class="flex items-center gap-4">
                    <a href="{{ route('privacy.notice') }}" class="text-neutral-400 hover:text-white transition-colors">Privacy Notice</a>
                    <a href="{{ route('login') }}" class="text-neutral-400 hover:text-white transition-colors">Staff Portal</a>
                    <a href="{{ url('/') }}" class="text-neutral-400 hover:text-white transition-colors">Home</a>
                </div>
            </footer>
        </div>
    </div>
</body>
</html>

