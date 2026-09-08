<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    <title>Privacy Notice · HIMS</title>

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
                    <a href="{{ route('terms') }}" class="text-neutral-400 hover:text-white transition-colors">Terms of Use</a>
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
                        <x-ui.icon name="shield-check" class="h-3.5 w-3.5" />
                        Data Privacy Act Compliance (Republic Act No. 10173)
                    </div>
                    <h1 class="mt-4 text-3xl font-bold tracking-tight text-white sm:text-4xl">System Privacy Notice</h1>
                    <p class="mt-3 text-sm text-neutral-400 sm:text-base leading-relaxed">
                        This Privacy Notice explains how the Hospital Information Management System (HIMS) collects, uses, stores, and safeguards personal data processed through its supply chain, inventory, and procurement modules.
                    </p>
                    <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-neutral-400">
                        <span><strong>Effective Date:</strong> September 2026</span>
                        <span>&middot;</span>
                        <span><strong>Version:</strong> 1.0 (Initial Operational Baseline)</span>
                        <span>&middot;</span>
                        <span><strong>Scope:</strong> Hospital Workforce &amp; Operational Accounts</span>
                    </div>
                </div>

                {{-- Legal Advisory Notice --}}
                <div class="my-8 rounded-xl border border-warning-500/30 bg-warning-500/10 p-4 text-xs leading-relaxed text-warning-200">
                    <div class="flex items-start gap-3">
                        <x-ui.icon name="information-circle" class="h-5 w-5 shrink-0 text-warning-400" />
                        <div>
                            <p class="font-semibold text-warning-300">Notice to System Administrators and Legal Counsel</p>
                            <p class="mt-1">
                                This technical privacy notice describes actual data handling practices in this HIMS installation. Bracketed fields such as <span class="font-mono text-white">[ORGANIZATION NAME]</span> and <span class="font-mono text-white">[DPO CONTACT EMAIL]</span> represent placeholders that must be tailored and formally verified by your designated Data Protection Officer (DPO) and institutional legal department prior to production certification.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Policy Content Body --}}
                <div class="space-y-10 text-sm leading-7 text-neutral-300">

                    {{-- 1. Identity of PIC --}}
                    <section class="space-y-3">
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-primary-500/20 text-xs font-bold text-primary-400">1</span>
                            Data Controller Information
                        </h2>
                        <p>
                            The personal data processed in this system is controlled by <strong class="text-white">[ORGANIZATION NAME / HOSPITAL HEALTH FACILITY]</strong> ("Hospital"), operating as the Personal Information Controller (PIC) pursuant to Republic Act No. 10173, otherwise known as the Data Privacy Act of 2012 (DPA), and its Implementing Rules and Regulations (IRR).
                        </p>
                        <div class="rounded-lg border border-white/10 bg-white/5 p-4 text-xs space-y-1 text-neutral-300">
                            <p><strong>Personal Information Controller:</strong> [Hospital / Health Facility Name]</p>
                            <p><strong>Institutional Address:</strong> [Hospital Address, City, Province, Philippines]</p>
                            <p><strong>Data Protection Officer (DPO):</strong> [DPO Name / Office of the Data Protection Officer]</p>
                            <p><strong>DPO Contact Email:</strong> [dpo@hospital.gov.ph / privacy@hospital.org]</p>
                            <p><strong>Contact Telephone:</strong> [+63 (2) 8XXX-XXXX]</p>
                        </div>
                    </section>

                    {{-- 2. System Scope & Health Data Distinction --}}
                    <section class="space-y-3">
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-primary-500/20 text-xs font-bold text-primary-400">2</span>
                            Scope of System &amp; Personal Data Collected
                        </h2>
                        <p>
                            HIMS is a dedicated <strong>hospital logistics, warehousing, procurement, and inventory management system</strong>. It is designed to track pharmaceuticals, medical supplies, warehouse stock, and vendor transactions.
                        </p>

                        <div class="rounded-lg border border-primary-500/30 bg-primary-900/20 p-3.5 text-xs text-primary-200">
                            <strong>Health and Patient Data Statement:</strong> This HIMS installation does <em>not</em> store patient clinical records, medical diagnoses, diagnostic test results, or patient treatment charts. It records only hospital materials, items, medicine stock levels, batch numbers, expiration dates, and the employee actions required to manage them.
                        </div>

                        <p class="pt-2 font-medium text-white">We collect and process the following categories of personal data from authorized personnel:</p>
                        <ul class="list-disc list-inside space-y-1.5 pl-2 text-neutral-300">
                            <li><strong>Account Identifiers:</strong> First name, middle name, surname, work/institutional email address, system-generated employee ID number, assigned hospital department, and official contact phone number.</li>
                            <li><strong>Authentication &amp; Security Data:</strong> Cryptographic password hashes (salted using bcrypt), multi-factor authentication (MFA) TOTP secrets (AES-256 encrypted at rest), email verification status, failed login counts, and lockout timestamps.</li>
                            <li><strong>Audit Trail &amp; System Telemetry:</strong> Actor employee ID, full name snapshot, action performed, target record identifiers, previous and updated values, internet protocol (IP) address, browser user-agent string, and timestamp of system events.</li>
                            <li><strong>Session State Information:</strong> Essential session cookies, inactivity deadline tokens, and last activity timestamps.</li>
                        </ul>
                    </section>

                    {{-- 3. Lawful Basis --}}
                    <section class="space-y-3">
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-primary-500/20 text-xs font-bold text-primary-400">3</span>
                            Lawful Basis for Processing
                        </h2>
                        <p>
                            Under Section 12 of Republic Act No. 10173, the processing of employee and user personal information in HIMS does not rely on generic consent checkboxes because it is lawfully justified under the following statutory grounds:
                        </p>
                        <div class="grid gap-3 sm:grid-cols-2 pt-1 text-xs">
                            <div class="rounded-lg border border-white/10 bg-white/5 p-3.5">
                                <p class="font-semibold text-white">Fulfillment of Employment / Contractual Role (Sec. 12[b])</p>
                                <p class="mt-1 text-neutral-400">
                                    Processing is necessary for the performance of work assignments, provisioning of staff login credentials, and the fulfillment of job responsibilities in warehouse, pharmacy, or inventory operations.
                                </p>
                            </div>
                            <div class="rounded-lg border border-white/10 bg-white/5 p-3.5">
                                <p class="font-semibold text-white">Compliance with Legal &amp; Regulatory Obligations (Sec. 12[c])</p>
                                <p class="mt-1 text-neutral-400">
                                    Hospitals are mandated under Philippine health regulations, public procurement rules, and auditing standards to maintain verifiable records of medicine custody, stock disbursement, and purchase approvals.
                                </p>
                            </div>
                            <div class="rounded-lg border border-white/10 bg-white/5 p-3.5">
                                <p class="font-semibold text-white">Legitimate Interests of the Health Facility (Sec. 12[f])</p>
                                <p class="mt-1 text-neutral-400">
                                    Ensuring hospital supply chain resilience, safeguarding medical assets against pilferage or loss, investigating discrepancies, and maintaining secure information technology infrastructure.
                                </p>
                            </div>
                            <div class="rounded-lg border border-white/10 bg-white/5 p-3.5">
                                <p class="font-semibold text-white">Security Safeguards (Sec. 20)</p>
                                <p class="mt-1 text-neutral-400">
                                    Maintaining audit logs and tracking IP addresses to detect, investigate, and prevent unauthorized system intrusion or cybersecurity incidents.
                                </p>
                            </div>
                        </div>
                    </section>

                    {{-- 4. Cookies & Tracking --}}
                    <section class="space-y-3">
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-primary-500/20 text-xs font-bold text-primary-400">4</span>
                            Cookies &amp; Technical Storage
                        </h2>
                        <p>
                            HIMS utilizes only <strong>strictly necessary, functional cookies</strong> required for authentication, CSRF attack defense, and session security. <strong>No analytics, advertising, tracking pixels, or third-party marketing cookies are utilized in this system.</strong>
                        </p>
                        <div class="overflow-x-auto rounded-lg border border-white/10 text-xs">
                            <table class="w-full divide-y divide-white/10 text-left">
                                <thead class="bg-white/5 text-neutral-300">
                                    <tr>
                                        <th class="px-3 py-2.5 font-semibold">Cookie Name</th>
                                        <th class="px-3 py-2.5 font-semibold">Classification</th>
                                        <th class="px-3 py-2.5 font-semibold">Purpose</th>
                                        <th class="px-3 py-2.5 font-semibold">Duration</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/10 text-neutral-400">
                                    <tr>
                                        <td class="px-3 py-2.5 font-mono text-white">hims-session</td>
                                        <td class="px-3 py-2.5">Strictly Necessary</td>
                                        <td class="px-3 py-2.5">Maintains authenticated staff session and CSRF security token.</td>
                                        <td class="px-3 py-2.5">Session / Idle Timeout</td>
                                    </tr>
                                    <tr>
                                        <td class="px-3 py-2.5 font-mono text-white">hims_inactivity</td>
                                        <td class="px-3 py-2.5">Strictly Necessary</td>
                                        <td class="px-3 py-2.5">Tracks client-side inactivity deadline to enforce automatic logout.</td>
                                        <td class="px-3 py-2.5">Browser session</td>
                                    </tr>
                                    <tr>
                                        <td class="px-3 py-2.5 font-mono text-white">XSRF-TOKEN</td>
                                        <td class="px-3 py-2.5">Strictly Necessary</td>
                                        <td class="px-3 py-2.5">Cross-Site Request Forgery mitigation for API interactions.</td>
                                        <td class="px-3 py-2.5">Session</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    {{-- 5. Data Retention & Immutability of Audit Trails --}}
                    <section class="space-y-3">
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-primary-500/20 text-xs font-bold text-primary-400">5</span>
                            Retention, Deactivation &amp; Audit Trail Integrity
                        </h2>
                        <p>
                            Personal data is retained in accordance with statutory hospital record retention requirements and institutional audit policies:
                        </p>
                        <ul class="list-disc list-inside space-y-1.5 pl-2 text-neutral-300">
                            <li><strong>Account Deactivation vs. Deletion:</strong> In order to maintain strict chain-of-custody for pharmaceutical supplies and audit compliance, user accounts are <em>deactivated</em> rather than permanently deleted when staff resign or change roles. Deactivated accounts cannot sign in or access any module, but their historic transactions (e.g., stock receipt entries, movement records) remain attributable to their employee record.</li>
                            <li><strong>Immutable Audit Trail:</strong> Records stored in the Audit Trail (`audit_logs`) capture system actions and are append-only. They cannot be altered or removed through the application interface, ensuring complete forensic accountability.</li>
                            <li><strong>Password History:</strong> Prior password hashes and fingerprints are retained solely to enforce password reuse restrictions and are stored as one-way non-reversible hashes.</li>
                        </ul>
                    </section>

                    {{-- 6. Security Safeguards --}}
                    <section class="space-y-3">
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-primary-500/20 text-xs font-bold text-primary-400">6</span>
                            Security Safeguards
                        </h2>
                        <p>
                            Consistent with NPC Advisory No. 2017-01 and international security standards, the system employs defense-in-depth measures to protect personal data:
                        </p>
                        <ul class="list-disc list-inside space-y-1 pl-2 text-neutral-300">
                            <li>Transport Layer Security (TLS) encryption for all client-to-server and database communications.</li>
                            <li>Multi-Factor Authentication (MFA) utilizing time-based one-time passwords (TOTP) and email security verification codes.</li>
                            <li>Brute-force mitigation with progressive rate limiting and account lockout mechanisms.</li>
                            <li>Automated session inactivity timeout with pre-expiry audio and visual alerts.</li>
                            <li>Granular role-based access control (Super Admin, Admin, Inventory Manager, Warehouse Staff, Pharmacy Staff, Viewer).</li>
                        </ul>
                    </section>

                    {{-- 7. Data Subject Rights --}}
                    <section class="space-y-3">
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-primary-500/20 text-xs font-bold text-primary-400">7</span>
                            Your Rights as a Data Subject
                        </h2>
                        <p>
                            Under Chapter VIII of Republic Act No. 10173, authorized users whose personal data is processed have the following rights, subject to lawful statutory exemptions:
                        </p>
                        <div class="grid gap-2 text-xs sm:grid-cols-2 pt-1">
                            <div class="border-l-2 border-primary-500/70 pl-3 py-1">
                                <strong class="text-white">Right to be Informed:</strong> To know that personal data is being processed, the purposes, and the recipients.
                            </div>
                            <div class="border-l-2 border-primary-500/70 pl-3 py-1">
                                <strong class="text-white">Right to Access:</strong> To request reasonable access to your personal information recorded in the system.
                            </div>
                            <div class="border-l-2 border-primary-500/70 pl-3 py-1">
                                <strong class="text-white">Right to Rectification:</strong> To correct inaccurate or outdated information via the Profile Settings module or through your administrator.
                            </div>
                            <div class="border-l-2 border-primary-500/70 pl-3 py-1">
                                <strong class="text-white">Right to Object:</strong> To contest processing, where not overridden by statutory obligations or legitimate operational mandates.
                            </div>
                            <div class="border-l-2 border-primary-500/70 pl-3 py-1">
                                <strong class="text-white">Right to File a Complaint:</strong> To lodge complaints before the National Privacy Commission (<a href="https://privacy.gov.ph" target="_blank" rel="noopener noreferrer" class="text-primary-400 underline">privacy.gov.ph</a>) if privacy rights have been violated.
                            </div>
                        </div>
                    </section>

                    {{-- 8. Contact & Complaints --}}
                    <section class="space-y-3">
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-primary-500/20 text-xs font-bold text-primary-400">8</span>
                            Inquiries, Concerns &amp; DPO Contact
                        </h2>
                        <p>
                            If you have questions regarding this Privacy Notice, wish to exercise your data subject rights, or wish to report a privacy concern, please contact the Data Protection Officer:
                        </p>
                        <div class="rounded-lg border border-white/10 bg-white/5 p-4 text-xs space-y-1.5 text-neutral-300">
                            <p class="text-white font-medium">[Hospital Name / Health Organization]</p>
                            <p><strong>Attention:</strong> Data Protection Officer</p>
                            <p><strong>Email:</strong> <span class="text-primary-400">[dpo@hospital.gov.ph / privacy@hospital.org]</span></p>
                            <p><strong>Office:</strong> [DPO Office Location / Hospital Administration Building]</p>
                            <p><strong>National Privacy Commission:</strong> Complaints may also be addressed to the NPC at <span class="text-neutral-400">complaints@privacy.gov.ph</span>.</p>
                        </div>
                    </section>

                </div>
            </main>

            {{-- Footer --}}
            <footer class="mt-12 flex flex-col gap-3 border-t border-white/10 py-6 text-xs text-neutral-500 sm:flex-row sm:items-center sm:justify-between">
                <p>&copy; {{ date('Y') }} HIMS — Hospital Supply Chain &amp; Inventory Management System.</p>
                <div class="flex items-center gap-4">
                    <a href="{{ route('terms') }}" class="text-neutral-400 hover:text-white transition-colors">Terms of Use</a>
                    <a href="{{ route('login') }}" class="text-neutral-400 hover:text-white transition-colors">Staff Portal</a>
                    <a href="{{ url('/') }}" class="text-neutral-400 hover:text-white transition-colors">Home</a>
                </div>
            </footer>
        </div>
    </div>
</body>
</html>

