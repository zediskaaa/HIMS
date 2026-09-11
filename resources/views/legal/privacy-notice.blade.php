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
                <span class="rounded-md bg-neutral-100 px-3 py-1 font-semibold text-neutral-900">Privacy Policy</span>
                <a href="{{ route('terms') }}" class="rounded-md px-3 py-1 text-neutral-600 hover:text-neutral-900 transition-colors">Terms and Conditions</a>
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
                        <div>REF: DPA-2012-HIMS-POL</div>
                        <div>VER: 1.0 (Operational)</div>
                    </div>
                </div>

                {{-- Full-width divider line --}}
                <div class="mt-4 h-[1.5px] w-full bg-[#DCE1E8]"></div>
            </header>

            {{-- Document Title --}}
            <div class="mb-6">
                <h1 class="text-3xl sm:text-[34px] font-normal tracking-tight text-[#4A453E]">
                    Privacy Policy
                </h1>
                <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wider text-[#8A7968]">
                    <span>System Privacy Notice</span>
                    <span>&bull;</span>
                    <span>Republic Act No. 10173 Compliance</span>
                    <span>&bull;</span>
                    <span>Effective: September 2026</span>
                </div>
            </div>

            {{-- Lead / Introduction Paragraph (Template Style) --}}
            <div class="mb-6 text-[15px] sm:text-base leading-relaxed text-[#4A453E]">
                <p>
                    This is the privacy policy of <strong class="text-neutral-800">[Hospital / Healthcare Facility Name]</strong>. This document explains <strong class="text-neutral-800">[Hospital / Healthcare Facility Name]</strong>'s policies for the collection, use, and disclosure of personal information processed through the Hospital Inventory Management System (HIMS).
                </p>
            </div>

            {{-- Document Body with Bronze Headings (#B07E48) --}}
            <div class="space-y-7 text-[15px] sm:text-base leading-relaxed text-[#4A453E]">

                {{-- 1. Data Controller Information --}}
                <section>
                    <h2 class="text-xl sm:text-[22px] font-medium text-[#B07E48] mb-2.5">
                        Data Controller Information
                    </h2>
                    <p class="mb-3">
                        The personal data processed in this system is controlled by <strong class="text-neutral-800">[Hospital / Healthcare Facility Name]</strong> ("Hospital"), operating as the Personal Information Controller (PIC) pursuant to Republic Act No. 10173, otherwise known as the Data Privacy Act of 2012 (DPA), and its Implementing Rules and Regulations (IRR).
                    </p>
                    <div class="rounded-md border border-[#E5E7EB] bg-[#F9FAFB] p-4 text-xs sm:text-sm space-y-1.5 text-neutral-700">
                        <p><strong class="text-neutral-900">Personal Information Controller:</strong> [Hospital / Healthcare Facility Name]</p>
                        <p><strong class="text-neutral-900">Institutional Address:</strong> [Hospital Address, City, Province, Philippines]</p>
                        <p><strong class="text-neutral-900">Data Protection Officer (DPO):</strong> [Office of the Data Protection Officer]</p>
                        <p><strong class="text-neutral-900">DPO Contact Email:</strong> <span class="font-mono text-[#19428F]">[dpo@hospital.gov.ph / privacy@hospital.org]</span></p>
                        <p><strong class="text-neutral-900">Contact Telephone:</strong> [+63 (2) 8XXX-XXXX]</p>
                    </div>
                </section>

                {{-- 2. The Information We Collect --}}
                <section>
                    <h2 class="text-xl sm:text-[22px] font-medium text-[#B07E48] mb-2.5">
                        The Information We Collect
                    </h2>
                    <p class="mb-3">
                        <strong class="text-neutral-800">[Hospital / Healthcare Facility Name]</strong> collects information by various methods including information actively provided by authorized personnel, system administrators, and automated operational telemetry.
                    </p>
                    <p class="mb-3">
                        The types of personal information we collect include employee name, contact information, institutional email address, hospital employee ID number, assigned department, encrypted credentials, role permissions, and system activity logs. Account authentication credentials and security tokens are used for authentication and access control purposes only. We may record and log administrative and stock transactions for purposes of accuracy, inventory integrity, performance reviews, training, forensic accountability, and general quality assurance.
                    </p>
                    <div class="rounded-md border-l-4 border-[#B07E48] bg-[#FDFBF7] p-3.5 text-xs sm:text-sm text-neutral-700 mb-3">
                        <strong class="text-neutral-900">Clinical Data Distinction:</strong> HIMS is a specialized logistics, procurement, and warehouse inventory management platform. It records pharmaceuticals, surgical equipment, batch numbers, expiry dates, and staff movement logs. It does <em>not</em> collect or store patient medical charts, clinical diagnoses, or patient treatment records.
                    </div>
                </section>

                {{-- 3. How We Use This Information --}}
                <section>
                    <h2 class="text-xl sm:text-[22px] font-medium text-[#B07E48] mb-2.5">
                        How We Use This Information
                    </h2>
                    <p class="mb-3">
                        This information is used to aid in the provision of hospital operations, pharmaceutical tracking, procurement workflows, stock movements, and user account governance.
                    </p>
                    <p class="mb-3 font-medium text-neutral-900">
                        Lawful Basis for Processing:
                    </p>
                    <p class="mb-3">
                        Under Section 12 of Republic Act No. 10173, processing of workforce information in HIMS does not rely on generic consent checkboxes because it is lawfully grounded upon:
                    </p>
                    <ul class="list-disc list-inside space-y-1.5 pl-2 text-neutral-700 text-sm sm:text-[15px]">
                        <li><strong class="text-neutral-900">Fulfillment of Employment / Contractual Role (Sec. 12[b]):</strong> Necessary for provisioning staff login credentials, maintaining duty assignments, and executing warehouse, procurement, or pharmacy tasks.</li>
                        <li><strong class="text-neutral-900">Compliance with Legal &amp; Regulatory Obligations (Sec. 12[c]):</strong> Meeting statutory mandates of the Department of Health (DOH), Food and Drug Administration (FDA), and Commission on Audit (COA) to maintain verifiable medicine chain-of-custody.</li>
                        <li><strong class="text-neutral-900">Legitimate Interests of the Health Facility (Sec. 12[f]):</strong> Safeguarding hospital assets against theft or discrepancies, ensuring supply chain continuity, and securing internal systems.</li>
                        <li><strong class="text-neutral-900">Security Safeguards (Sec. 20):</strong> Capturing audit events, IP addresses, device and browser context, approximate IP-derived location, and session timestamps to prevent unauthorized access and protect data integrity. With the user's explicit browser permission, HIMS may instead retain rounded, device-reported coordinates for the current signed-in session and record them with subsequent audit events.</li>
                    </ul>
                </section>

                {{-- 4. Who We Share This Information With --}}
                <section>
                    <h2 class="text-xl sm:text-[22px] font-medium text-[#B07E48] mb-2.5">
                        Who We Share This Information With
                    </h2>
                    <p class="mb-3">
                        <strong class="text-neutral-800">[Hospital / Healthcare Facility Name]</strong> does not share personal information with any third parties except as disclosed in this policy or required by law. <strong class="text-neutral-800">[Hospital / Healthcare Facility Name]</strong> may provide personal information to internal institutional auditors, statutory regulatory bodies, and contracted technology service providers (which shall be bound by strict confidentiality and data protection agreements) to assist <strong class="text-neutral-800">[Hospital / Healthcare Facility Name]</strong> in the operations disclosed herein.
                    </p>
                </section>

                {{-- 5. Cookies & Technical Storage --}}
                <section>
                    <h2 class="text-xl sm:text-[22px] font-medium text-[#B07E48] mb-2.5">
                        Cookies &amp; Technical Storage
                    </h2>
                    <p class="mb-3">
                        HIMS utilizes only <strong>strictly necessary technical session tokens</strong> required for authentication, CSRF security, and automated inactivity timeouts. No marketing, advertising, or third-party tracking cookies are utilized.
                    </p>
                    <div class="overflow-x-auto rounded-md border border-[#E5E7EB] text-xs sm:text-sm">
                        <table class="w-full text-left">
                            <thead class="bg-[#F9FAFB] text-neutral-700 font-semibold border-b border-[#E5E7EB]">
                                <tr>
                                    <th class="px-3.5 py-2.5">Cookie / Token</th>
                                    <th class="px-3.5 py-2.5">Classification</th>
                                    <th class="px-3.5 py-2.5">Purpose</th>
                                    <th class="px-3.5 py-2.5">Duration</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E5E7EB] text-neutral-600">
                                <tr>
                                    <td class="px-3.5 py-2.5 font-mono text-neutral-900 font-medium">hims-session</td>
                                    <td class="px-3.5 py-2.5">Strictly Necessary</td>
                                    <td class="px-3.5 py-2.5">Maintains authenticated staff session and CSRF protection.</td>
                                    <td class="px-3.5 py-2.5">Session / Idle Timeout</td>
                                </tr>
                                <tr>
                                    <td class="px-3.5 py-2.5 font-mono text-neutral-900 font-medium">hims_inactivity</td>
                                    <td class="px-3.5 py-2.5">Strictly Necessary</td>
                                    <td class="px-3.5 py-2.5">Enforces automatic logout upon inactivity to safeguard hospital terminals.</td>
                                    <td class="px-3.5 py-2.5">Browser session</td>
                                </tr>
                                <tr>
                                    <td class="px-3.5 py-2.5 font-mono text-neutral-900 font-medium">XSRF-TOKEN</td>
                                    <td class="px-3.5 py-2.5">Strictly Necessary</td>
                                    <td class="px-3.5 py-2.5">Mitigates cross-site request forgery risks during state-changing requests.</td>
                                    <td class="px-3.5 py-2.5">Session</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                {{-- 6. Retention, Deactivation & Immutable Audit Trail --}}
                <section>
                    <h2 class="text-xl sm:text-[22px] font-medium text-[#B07E48] mb-2.5">
                        Retention, Deactivation &amp; Immutable Audit Trail
                    </h2>
                    <p class="mb-3">
                        Personal data and administrative logs are retained in accordance with hospital governance guidelines and statutory audit obligations:
                    </p>
                    <ul class="list-disc list-inside space-y-1.5 pl-2 text-neutral-700 text-sm sm:text-[15px]">
                        <li><strong class="text-neutral-900">Deactivation vs. Deletion:</strong> When staff resign or transfer departments, user accounts are deactivated to immediately revoke login access. Account records and historic transaction attributions are preserved to maintain pharmaceutical custody trails.</li>
                        <li><strong class="text-neutral-900">Immutable Audit Trail:</strong> All operations captured in the Audit Trail (<code class="font-mono text-xs bg-neutral-100 px-1 py-0.5 rounded text-neutral-800">audit_logs</code>) are append-only. They cannot be modified or purged through the user interface, ensuring complete evidentiary reliability.</li>
                        <li><strong class="text-neutral-900">Password Security:</strong> Passwords are one-way hashed using salted bcrypt and cannot be retrieved in plaintext by any user or administrator.</li>
                    </ul>
                </section>

                {{-- 7. Security Safeguards --}}
                <section>
                    <h2 class="text-xl sm:text-[22px] font-medium text-[#B07E48] mb-2.5">
                        Security Safeguards
                    </h2>
                    <p class="mb-3">
                        In adherence to National Privacy Commission recommendations and industry best practices, HIMS incorporates technical, physical, and organizational safeguards:
                    </p>
                    <ul class="list-disc list-inside space-y-1.5 pl-2 text-neutral-700 text-sm sm:text-[15px]">
                        <li>Transport Layer Security (TLS) cryptographic encryption for all transmissions.</li>
                        <li>Multi-Factor Authentication (MFA) via time-based one-time password (TOTP) protocols and secure email channels.</li>
                        <li>Granular Role-Based Access Control enforcing the Principle of Least Privilege across Pharmacy, Warehouse, Management, and Administration.</li>
                        <li>Intrusion rate-limiting and automatic account lockout defenses against brute-force credential attacks.</li>
                    </ul>
                </section>

                {{-- 8. Your Rights as a Data Subject --}}
                <section>
                    <h2 class="text-xl sm:text-[22px] font-medium text-[#B07E48] mb-2.5">
                        Your Rights as a Data Subject
                    </h2>
                    <p class="mb-3">
                        Under Chapter VIII of Republic Act No. 10173, authorized users whose personal data is processed within HIMS are entitled to the following rights:
                    </p>
                    <div class="grid gap-2.5 sm:grid-cols-2 text-xs sm:text-sm">
                        <div class="rounded border border-[#E5E7EB] bg-[#F9FAFB] p-3">
                            <strong class="text-neutral-900 block mb-1">Right to be Informed</strong>
                            <span>To be notified of the nature, purpose, and scope of data processing operations.</span>
                        </div>
                        <div class="rounded border border-[#E5E7EB] bg-[#F9FAFB] p-3">
                            <strong class="text-neutral-900 block mb-1">Right to Access</strong>
                            <span>To request reasonable access to your personal information recorded in the system.</span>
                        </div>
                        <div class="rounded border border-[#E5E7EB] bg-[#F9FAFB] p-3">
                            <strong class="text-neutral-900 block mb-1">Right to Rectification</strong>
                            <span>To dispute inaccuracy or error in your personal data and have it corrected.</span>
                        </div>
                        <div class="rounded border border-[#E5E7EB] bg-[#F9FAFB] p-3">
                            <strong class="text-neutral-900 block mb-1">Right to Lodge a Complaint</strong>
                            <span>To file a formal grievance with the National Privacy Commission (<a href="https://privacy.gov.ph" target="_blank" rel="noopener noreferrer" class="text-primary-700 underline font-medium">privacy.gov.ph</a>).</span>
                        </div>
                    </div>
                </section>

                {{-- 9. Inquiries, Concerns & DPO Contact --}}
                <section>
                    <h2 class="text-xl sm:text-[22px] font-medium text-[#B07E48] mb-2.5">
                        Inquiries, Concerns &amp; DPO Contact Information
                    </h2>
                    <p class="mb-3">
                        For inquiries concerning this Privacy Notice, the exercise of data privacy rights, or to report an information security concern, please direct communications to:
                    </p>
                    <div class="rounded-md border border-[#E5E7EB] bg-[#F9FAFB] p-4 text-xs sm:text-sm space-y-1 text-neutral-700">
                        <p class="font-semibold text-neutral-900">[Hospital / Healthcare Facility Name]</p>
                        <p><strong>Office:</strong> Office of the Data Protection Officer</p>
                        <p><strong>Email:</strong> <span class="font-mono text-[#19428F]">[dpo@hospital.gov.ph / privacy@hospital.org]</span></p>
                        <p><strong>National Privacy Commission:</strong> <span class="font-mono text-neutral-600">complaints@privacy.gov.ph</span></p>
                    </div>
                </section>

            </div>

            {{-- Document Footer --}}
            <footer class="mt-12 pt-6 border-t border-[#DCE1E8] flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-neutral-500">
                <p>&copy; {{ date('Y') }} [Hospital / Healthcare Facility Name] &bull; Hospital Inventory Management System.</p>
                <div class="flex items-center gap-4">
                    <a href="{{ route('terms') }}" class="hover:text-neutral-800 transition-colors">Terms of Use</a>
                    <a href="{{ url('/') }}" class="hover:text-neutral-800 transition-colors">Home</a>
                    <a href="{{ route('login') }}" class="hover:text-neutral-800 transition-colors">Staff Login</a>
                </div>
            </footer>

        </main>
    </div>
</body>
</html>
