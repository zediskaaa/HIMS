<x-app-layout>
    <x-ui.page-header
        title="Privacy & Security Governance"
        subtitle="Technical controls, data subject rights processing, and security incident management aligned with RA 10173 and ISO/IEC 27001:2022."
        :breadcrumbs="[
            'Home' => route(\App\Support\AuthenticationContext::dashboardRoute()),
            'Administration' => route('admin.users.index'),
            'Privacy & Governance' => null,
        ]"
    >
        <x-slot:actions>
            <div class="flex items-center gap-2 flex-wrap">
                <a
                    href="{{ route('privacy.notice') }}"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold text-neutral-700 shadow-sm transition hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800"
                >
                    <x-ui.icon name="document-text" class="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
                    <span>Public Privacy Notice</span>
                </a>

                <button
                    type="button"
                    x-data
                    x-on:click="$dispatch('open-modal', 'record-incident-modal')"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-danger-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-danger-700 focus:outline-none focus:ring-2 focus:ring-danger-500 focus:ring-offset-2"
                >
                    <x-ui.icon name="exclamation-triangle" class="h-4 w-4" />
                    <span>Report Security Incident</span>
                </button>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert variant="success" title="Action Completed" dismissible class="mb-6">
            {{ session('status') }}
        </x-ui.alert>
    @endif

    {{-- Top Governance Metrics --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <x-ui.stat
            label="Verified Technical Controls"
            :value="$posture['passed_count'] . ' / ' . $posture['total_controls']"
            icon="shield-check"
            :tone="$posture['passed_count'] === $posture['total_controls'] ? 'success' : 'warning'"
            :hint="$posture['attention_count'] . ' control(s) require technical attention'"
        />

        <x-ui.stat
            label="Registered Processing Flows (ROPA)"
            :value="(string) count($ropaActivities)"
            icon="document-text"
            tone="neutral"
            hint="Cataloged under DPA Sec. 16 / ISO A.5.9"
        />

        <x-ui.stat
            label="Open Data Subject Requests"
            :value="(string) $openDsrCount"
            icon="user-circle"
            :tone="$openDsrCount > 0 ? 'warning' : 'neutral'"
            hint="Pending review or fulfillment by DPO"
        />

        <x-ui.stat
            label="Active Security Incidents"
            :value="(string) $openIncidentsCount"
            icon="exclamation-triangle"
            :tone="$openIncidentsCount > 0 ? 'danger' : 'success'"
            hint="ISO A.5.24 incident response registry"
        />
    </div>

    {{-- Governance Disclaimer (Strictly avoiding false claims of compliance) --}}
    <div class="mb-6 rounded-lg border border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-900/60 p-3.5 text-xs text-neutral-600 dark:text-neutral-400">
        <strong class="text-neutral-900 dark:text-neutral-200 font-semibold">Technical Control Verification Notice:</strong>
        This console monitors live software safeguards, data boundaries, and operational audit trails within HIMS. Technical control verification supports organizational compliance efforts but does not constitute legal counsel, statutory immunity, or ISO accreditation.
    </div>

    {{-- Navigation Tabs --}}
    <div class="mb-6 border-b border-neutral-200 dark:border-neutral-800">
        <nav class="flex space-x-6 overflow-x-auto" aria-label="Privacy Tabs">
            <a
                href="{{ route('admin.privacy.index', ['tab' => 'posture']) }}"
                class="whitespace-nowrap pb-3 text-sm font-medium border-b-2 transition {{ $activeTab === 'posture' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300 dark:text-neutral-400 dark:hover:text-neutral-300' }}"
            >
                Security Posture ({{ $posture['passed_count'] }}/{{ $posture['total_controls'] }})
            </a>

            <a
                href="{{ route('admin.privacy.index', ['tab' => 'ropa']) }}"
                class="whitespace-nowrap pb-3 text-sm font-medium border-b-2 transition {{ $activeTab === 'ropa' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300 dark:text-neutral-400 dark:hover:text-neutral-300' }}"
            >
                Processing Register (ROPA)
            </a>

            <a
                href="{{ route('admin.privacy.index', ['tab' => 'classification']) }}"
                class="whitespace-nowrap pb-3 text-sm font-medium border-b-2 transition {{ $activeTab === 'classification' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300 dark:text-neutral-400 dark:hover:text-neutral-300' }}"
            >
                Data Classification
            </a>

            <a
                href="{{ route('admin.privacy.index', ['tab' => 'dsr']) }}"
                class="whitespace-nowrap pb-3 text-sm font-medium border-b-2 transition {{ $activeTab === 'dsr' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300 dark:text-neutral-400 dark:hover:text-neutral-300' }}"
            >
                Data Subject Requests
                @if ($openDsrCount > 0)
                    <span class="ml-1.5 rounded-full bg-warning-100 text-warning-800 dark:bg-warning-900/60 dark:text-warning-300 px-2 py-0.5 text-xs font-semibold">
                        {{ $openDsrCount }}
                    </span>
                @endif
            </a>

            <a
                href="{{ route('admin.privacy.index', ['tab' => 'incidents']) }}"
                class="whitespace-nowrap pb-3 text-sm font-medium border-b-2 transition {{ $activeTab === 'incidents' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300 dark:text-neutral-400 dark:hover:text-neutral-300' }}"
            >
                Security Incidents
                @if ($openIncidentsCount > 0)
                    <span class="ml-1.5 rounded-full bg-danger-100 text-danger-800 dark:bg-danger-900/60 dark:text-danger-300 px-2 py-0.5 text-xs font-semibold">
                        {{ $openIncidentsCount }}
                    </span>
                @endif
            </a>

            <a
                href="{{ route('admin.privacy.index', ['tab' => 'retention']) }}"
                class="whitespace-nowrap pb-3 text-sm font-medium border-b-2 transition {{ $activeTab === 'retention' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300 dark:text-neutral-400 dark:hover:text-neutral-300' }}"
            >
                Retention &amp; Lifecycle
            </a>
        </nav>
    </div>

    {{-- TAB 1: POSTURE ASSESSMENT --}}
    @if ($activeTab === 'posture')
        <div class="space-y-4">
            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Live Technical Controls Checklist</h3>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">Automated verification of server-side boundaries, encryption, headers, and logs.</p>
                    </div>
                    <span class="text-xs font-mono text-neutral-500">Evaluated at {{ now()->format('Y-m-d H:i:s') }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-800 text-left text-xs">
                        <thead class="bg-neutral-50 dark:bg-neutral-800/50 text-neutral-600 dark:text-neutral-400 font-semibold uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3">Control Code</th>
                                <th class="px-4 py-3">Requirement</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Verified Evidence</th>
                                <th class="px-4 py-3">Required Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800 font-normal">
                            @foreach ($posture['checks'] as $check)
                                <tr class="hover:bg-neutral-50/60 dark:hover:bg-neutral-800/30 transition">
                                    <td class="px-4 py-3.5 font-mono text-xs font-semibold text-neutral-900 dark:text-neutral-100 whitespace-nowrap">
                                        {{ $check['id'] }}
                                    </td>
                                    <td class="px-4 py-3.5 font-medium text-neutral-900 dark:text-neutral-100 max-w-xs">
                                        {{ $check['title'] }}
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        @if ($check['status'] === 'pass')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                Active Control
                                            </span>
                                        @elseif ($check['status'] === 'critical')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                                                Critical Gap
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                                Attention Needed
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-neutral-600 dark:text-neutral-300 font-mono text-[11px] max-w-md break-words">
                                        {{ $check['evidence'] }}
                                    </td>
                                    <td class="px-4 py-3.5 text-neutral-500 dark:text-neutral-400 text-xs">
                                        {{ $check['action_required'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- TAB 2: ROPA REGISTER --}}
    @if ($activeTab === 'ropa')
        <div class="space-y-4">
            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800">
                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Records of Processing Activities (ROPA)</h3>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">Formal inventory of personal data processing under RA 10173 Section 16 &amp; ISO/IEC 27001:2022 Control A.5.9.</p>
                </div>

                <div class="divide-y divide-neutral-200 dark:divide-neutral-800">
                    @foreach ($ropaActivities as $activity)
                        <div class="p-5 space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2.5">
                                    <span class="font-mono text-xs font-bold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-950/50 px-2 py-1 rounded">
                                        {{ $activity['activity_id'] }}
                                    </span>
                                    <h4 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                        {{ $activity['process_name'] }}
                                    </h4>
                                </div>
                                <span class="text-xs font-mono text-neutral-500">System: {{ $activity['system_module'] }}</span>
                            </div>

                            <p class="text-xs text-neutral-600 dark:text-neutral-300">
                                <strong>Purpose:</strong> {{ $activity['purpose'] }}
                            </p>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs bg-neutral-50 dark:bg-neutral-800/40 p-3 rounded-lg border border-neutral-200 dark:border-neutral-700/60">
                                <div>
                                    <strong class="text-neutral-900 dark:text-neutral-100 block mb-1">Legal Basis:</strong>
                                    <span class="text-neutral-600 dark:text-neutral-400">{{ $activity['legal_basis'] }}</span>
                                </div>
                                <div>
                                    <strong class="text-neutral-900 dark:text-neutral-100 block mb-1">Retention Schedule:</strong>
                                    <span class="text-neutral-600 dark:text-neutral-400">{{ $activity['retention_period'] }}</span>
                                </div>
                                <div>
                                    <strong class="text-neutral-900 dark:text-neutral-100 block mb-1">Data Categories:</strong>
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        @foreach ($activity['data_categories'] as $cat)
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-neutral-200 dark:bg-neutral-700 text-neutral-800 dark:text-neutral-200">
                                                {{ $cat }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <div class="text-xs text-neutral-500 dark:text-neutral-400">
                                <strong>Security Measures:</strong> {{ implode(', ', $activity['security_measures']) }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- TAB 3: DATA CLASSIFICATION --}}
    @if ($activeTab === 'classification')
        <div class="space-y-6">
            {{-- Classification Tiers --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                @foreach ($classificationTiers as $tier)
                    <div class="rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-4 shadow-sm space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-mono font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">Tier {{ $loop->iteration }}</span>
                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200">
                                {{ $tier['name'] }}
                            </span>
                        </div>
                        <p class="text-xs text-neutral-600 dark:text-neutral-400">{{ $tier['description'] }}</p>
                        <div class="pt-2 border-t border-neutral-100 dark:border-neutral-800 text-[11px] text-neutral-500">
                            <strong>Rules:</strong> {{ $tier['handling_rules'] }}
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Entity Catalog --}}
            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800">
                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Data Asset Classification Catalog</h3>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">HIMS database entities categorized by data sensitivity and confidentiality tiers.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-800 text-left text-xs">
                        <thead class="bg-neutral-50 dark:bg-neutral-800/50 text-neutral-600 dark:text-neutral-400 font-semibold uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3">Asset / Entity</th>
                                <th class="px-4 py-3">Classification Tier</th>
                                <th class="px-4 py-3">Example Attributes</th>
                                <th class="px-4 py-3">Handling &amp; Masking Guidelines</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800 font-normal">
                            @foreach ($classificationCatalog as $asset)
                                <tr class="hover:bg-neutral-50/60 dark:hover:bg-neutral-800/30 transition">
                                    <td class="px-4 py-3 font-semibold text-neutral-900 dark:text-neutral-100">
                                        {{ $asset['entity'] }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold
                                            {{ $asset['tier'] === 'restricted_spi' ? 'bg-danger-100 text-danger-800 dark:bg-danger-950/60 dark:text-danger-300' :
                                               ($asset['tier'] === 'confidential_personal' ? 'bg-warning-100 text-warning-800 dark:bg-warning-950/60 dark:text-warning-300' :
                                               'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200') }}">
                                            {{ strtoupper(str_replace('_', ' ', $asset['tier'])) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-neutral-600 dark:text-neutral-300 text-xs">
                                        {{ implode(', ', $asset['attributes']) }}
                                    </td>
                                    <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400 text-xs">
                                        {{ $asset['handling'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- TAB 4: DATA SUBJECT REQUESTS (DSR) --}}
    @if ($activeTab === 'dsr')
        <div class="space-y-4">
            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Data Subject Requests Registry (RA 10173)</h3>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">Formal rights petitions submitted by authenticated employees and data subjects.</p>
                    </div>
                </div>

                @if ($privacyRequests->isEmpty())
                    <div class="py-12 text-center text-xs text-neutral-500">
                        No Data Subject Requests have been submitted yet.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-800 text-left text-xs">
                            <thead class="bg-neutral-50 dark:bg-neutral-800/50 text-neutral-600 dark:text-neutral-400 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-3">Ticket</th>
                                    <th class="px-4 py-3">Requester</th>
                                    <th class="px-4 py-3">Type</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Details / Request</th>
                                    <th class="px-4 py-3">Submitted</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800 font-normal">
                                @foreach ($privacyRequests as $requestItem)
                                    <tr class="hover:bg-neutral-50/60 dark:hover:bg-neutral-800/30 transition">
                                        <td class="px-4 py-3.5 font-mono text-xs font-semibold text-neutral-900 dark:text-neutral-100 whitespace-nowrap">
                                            #{{ $requestItem->ticket_number }}
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap">
                                            <div class="font-medium text-neutral-900 dark:text-neutral-100">{{ $requestItem->user?->name ?? 'External / Deleted User' }}</div>
                                            <div class="text-[11px] text-neutral-500 font-mono">{{ $requestItem->user?->employee_id ?? 'N/A' }}</div>
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200">
                                                {{ $requestItem->type_label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold
                                                {{ $requestItem->status === 'fulfilled' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' :
                                                   ($requestItem->status === 'rejected' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300' :
                                                   'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300') }}">
                                                {{ $requestItem->status_label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3.5 text-neutral-600 dark:text-neutral-300 max-w-xs break-words">
                                            <p class="line-clamp-2">{{ $requestItem->details }}</p>
                                            @if ($requestItem->resolution_notes)
                                                <div class="mt-1 text-[11px] text-neutral-500 italic">
                                                    <strong>Resolution:</strong> {{ $requestItem->resolution_notes }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap text-neutral-500 text-[11px]">
                                            {{ $requestItem->created_at->format('M d, Y H:i') }}
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap text-right space-x-1">
                                            @if ($requestItem->user)
                                                <a
                                                    href="{{ route('admin.privacy.requests.export', $requestItem) }}"
                                                    class="inline-flex items-center px-2 py-1 text-xs font-medium text-neutral-700 dark:text-neutral-300 bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 rounded transition"
                                                    title="Download Sanitized Personal Data Export (JSON)"
                                                >
                                                    <x-ui.icon name="arrow-down-tray" class="h-3.5 w-3.5 mr-1" />
                                                    Export
                                                </a>
                                            @endif

                                            @if ($requestItem->status !== 'fulfilled' && $requestItem->status !== 'rejected')
                                                <button
                                                    type="button"
                                                    x-data
                                                    x-on:click="$dispatch('open-modal', 'fulfill-dsr-modal-{{ $requestItem->id }}')"
                                                    class="inline-flex items-center px-2 py-1 text-xs font-medium text-emerald-700 bg-emerald-50 dark:bg-emerald-950/60 dark:text-emerald-300 hover:bg-emerald-100 rounded transition"
                                                >
                                                    Fulfill
                                                </button>

                                                <button
                                                    type="button"
                                                    x-data
                                                    x-on:click="$dispatch('open-modal', 'reject-dsr-modal-{{ $requestItem->id }}')"
                                                    class="inline-flex items-center px-2 py-1 text-xs font-medium text-rose-700 bg-rose-50 dark:bg-rose-950/60 dark:text-rose-300 hover:bg-rose-100 rounded transition"
                                                >
                                                    Refuse (DPA Sec. 16)
                                                </button>
                                            @endif
                                        </td>
                                    </tr>

                                    {{-- Fulfill Modal --}}
                                    <x-ui.modal name="fulfill-dsr-modal-{{ $requestItem->id }}" title="Fulfill Data Subject Request #{{ $requestItem->ticket_number }}" maxWidth="md">
                                        <form method="POST" action="{{ route('admin.privacy.requests.fulfill', $requestItem) }}" class="space-y-4">
                                            @csrf
                                            <p class="text-xs text-neutral-600 dark:text-neutral-400">
                                                Confirm fulfillment of this request. The requester will be notified of the resolution and portable data export availability.
                                            </p>

                                            <div>
                                                <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">Resolution Notes (Optional)</label>
                                                <textarea name="resolution_notes" rows="3" class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-xs shadow-sm" placeholder="Actions taken (e.g. data verified, export generated)..."></textarea>
                                            </div>

                                            <div class="flex justify-end gap-2 pt-3 border-t border-neutral-200 dark:border-neutral-800">
                                                <button type="button" x-on:click="$dispatch('close-modal', 'fulfill-dsr-modal-{{ $requestItem->id }}')" class="px-3 py-1.5 text-xs rounded border border-neutral-300">Cancel</button>
                                                <button type="submit" class="px-3 py-1.5 text-xs rounded bg-emerald-600 text-white font-semibold">Mark as Fulfilled</button>
                                            </div>
                                        </form>
                                    </x-ui.modal>

                                    {{-- Reject Modal --}}
                                    <x-ui.modal name="reject-dsr-modal-{{ $requestItem->id }}" title="Refuse Data Subject Request #{{ $requestItem->ticket_number }}" maxWidth="md">
                                        <form method="POST" action="{{ route('admin.privacy.requests.reject', $requestItem) }}" class="space-y-4">
                                            @csrf
                                            <x-ui.alert variant="warning" title="Mandatory Statutory Ground">
                                                Under RA 10173 Section 16, refusal to fulfill a Data Subject Request requires specific legal or statutory justification (e.g. NAP GRDS-9, COA retention requirements, or ongoing audit inspection).
                                            </x-ui.alert>

                                            <div>
                                                <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">Statutory Justification Reason <span class="text-danger-600">*</span></label>
                                                <textarea name="reason" rows="3" required class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-xs shadow-sm" placeholder="Cite specific statutory requirement (e.g. NAP GRDS-9 retains logistics logs for 5 years)..."></textarea>
                                            </div>

                                            <div class="flex justify-end gap-2 pt-3 border-t border-neutral-200 dark:border-neutral-800">
                                                <button type="button" x-on:click="$dispatch('close-modal', 'reject-dsr-modal-{{ $requestItem->id }}')" class="px-3 py-1.5 text-xs rounded border border-neutral-300">Cancel</button>
                                                <button type="submit" class="px-3 py-1.5 text-xs rounded bg-rose-600 text-white font-semibold">Confirm Statutory Refusal</button>
                                            </div>
                                        </form>
                                    </x-ui.modal>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-5 py-3 border-t border-neutral-200 dark:border-neutral-800">
                        {{ $privacyRequests->links() }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- TAB 5: SECURITY INCIDENTS --}}
    @if ($activeTab === 'incidents')
        <div class="space-y-4">
            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Security Incident Log (ISO 27001 A.5.24)</h3>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">Incident containment tracking and NPC Circular 16-03 statutory breach assessment.</p>
                    </div>
                    <button
                        type="button"
                        x-data
                        x-on:click="$dispatch('open-modal', 'record-incident-modal')"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-danger-600 text-white hover:bg-danger-700 transition"
                    >
                        + Record Incident
                    </button>
                </div>

                @if ($securityIncidents->isEmpty())
                    <div class="py-12 text-center text-xs text-neutral-500">
                        No security incidents have been recorded.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-800 text-left text-xs">
                            <thead class="bg-neutral-50 dark:bg-neutral-800/50 text-neutral-600 dark:text-neutral-400 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-3">Incident #</th>
                                    <th class="px-4 py-3">Title &amp; Type</th>
                                    <th class="px-4 py-3">Severity</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">NPC Breach Assessment</th>
                                    <th class="px-4 py-3">Reported</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800 font-normal">
                                @foreach ($securityIncidents as $incident)
                                    <tr class="hover:bg-neutral-50/60 dark:hover:bg-neutral-800/30 transition">
                                        <td class="px-4 py-3.5 font-mono text-xs font-semibold text-neutral-900 dark:text-neutral-100 whitespace-nowrap">
                                            {{ $incident->incident_number }}
                                        </td>
                                        <td class="px-4 py-3.5 max-w-xs">
                                            <div class="font-medium text-neutral-900 dark:text-neutral-100">{{ $incident->title }}</div>
                                            <div class="text-[11px] text-neutral-500 font-mono">{{ $incident->incident_type }}</div>
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold
                                                {{ $incident->severity === 'critical' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300' :
                                                   ($incident->severity === 'high' ? 'bg-orange-100 text-orange-800 dark:bg-orange-950/60 dark:text-orange-300' :
                                                   ($incident->severity === 'medium' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' :
                                                   'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200')) }}">
                                                {{ $incident->severity_label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200">
                                                {{ $incident->status_label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap">
                                            @if ($incident->is_reportable_breach)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                                                    Mandatory Breach (NPC 72h)
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                    Non-Breach / Contained
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap text-neutral-500 text-[11px]">
                                            {{ $incident->reported_at?->format('M d, Y H:i') ?? $incident->created_at->format('M d, Y H:i') }}
                                        </td>
                                        <td class="px-4 py-3.5 whitespace-nowrap text-right">
                                            <button
                                                type="button"
                                                x-data
                                                x-on:click="$dispatch('open-modal', 'edit-incident-modal-{{ $incident->id }}')"
                                                class="px-2.5 py-1 text-xs font-medium rounded border border-neutral-300 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition"
                                            >
                                                Manage
                                            </button>
                                        </td>
                                    </tr>

                                    {{-- Edit Incident Modal --}}
                                    <x-ui.modal name="edit-incident-modal-{{ $incident->id }}" title="Manage Incident {{ $incident->incident_number }}" maxWidth="lg">
                                        <form method="POST" action="{{ route('admin.privacy.incidents.update', $incident) }}" class="space-y-4">
                                            @csrf
                                            @method('PUT')

                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">Status</label>
                                                    <select name="status" class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-700 text-xs">
                                                        @foreach (['reported' => 'Reported', 'investigating' => 'Investigating', 'contained' => 'Contained', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $key => $lbl)
                                                            <option value="{{ $key }}" @selected($incident->status === $key)>{{ $lbl }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">Severity</label>
                                                    <select name="severity" class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-700 text-xs">
                                                        @foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'] as $key => $lbl)
                                                            <option value="{{ $key }}" @selected($incident->severity === $key)>{{ $lbl }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">Containment Actions</label>
                                                <textarea name="containment_actions" rows="2" class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-700 text-xs">{{ $incident->containment_actions }}</textarea>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">Remediation Notes</label>
                                                <textarea name="remediation_notes" rows="2" class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-700 text-xs">{{ $incident->remediation_notes }}</textarea>
                                            </div>

                                            <div class="pt-3 border-t border-neutral-200 dark:border-neutral-800 space-y-3">
                                                <div class="flex items-center gap-2">
                                                    <input type="checkbox" id="breach_{{ $incident->id }}" name="is_reportable_breach" value="1" @checked($incident->is_reportable_breach) class="rounded border-neutral-300 text-danger-600">
                                                    <label for="breach_{{ $incident->id }}" class="text-xs font-medium text-neutral-700 dark:text-neutral-300">
                                                        Classified as Reportable Personal Data Breach under NPC Circular 16-03
                                                    </label>
                                                </div>

                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">Affected Subjects Count</label>
                                                        <input type="number" name="affected_subjects_count" value="{{ $incident->affected_subjects_count }}" min="0" class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-700 text-xs">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">NPC Notified Date</label>
                                                        <input type="date" name="npc_notified_at" value="{{ $incident->npc_notified_at?->format('Y-m-d') }}" class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-700 text-xs">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex justify-end gap-2 pt-3 border-t border-neutral-200 dark:border-neutral-800">
                                                <button type="button" x-on:click="$dispatch('close-modal', 'edit-incident-modal-{{ $incident->id }}')" class="px-3 py-1.5 text-xs rounded border border-neutral-300">Cancel</button>
                                                <button type="submit" class="px-3 py-1.5 text-xs rounded bg-primary-600 text-white font-semibold">Save Incident Updates</button>
                                            </div>
                                        </form>
                                    </x-ui.modal>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-5 py-3 border-t border-neutral-200 dark:border-neutral-800">
                        {{ $securityIncidents->links() }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- TAB 6: RETENTION & LIFECYCLE --}}
    @if ($activeTab === 'retention')
        <div class="space-y-6">
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Data Lifecycle &amp; Retention Schedules</h3>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">Enforcement of statutory retention schedules under RA 10173 Sec. 11(e) and NAP General Circulars.</p>
                    </div>
                    <form method="POST" action="{{ route('admin.privacy.retention.sweep') }}">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg bg-primary-600 text-white hover:bg-primary-700 shadow-sm transition"
                        >
                            <x-ui.icon name="arrow-path" class="h-4 w-4" />
                            <span>Execute Ephemeral Retention Sweep</span>
                        </button>
                    </form>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                    <div class="p-3.5 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/40 space-y-1">
                        <strong class="text-neutral-900 dark:text-neutral-100 block">Ephemeral Notifications</strong>
                        <span class="text-neutral-600 dark:text-neutral-400">Read notifications older than {{ config('privacy.retention.expired_notifications_days', 90) }} days are pruned automatically.</span>
                    </div>
                    <div class="p-3.5 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/40 space-y-1">
                        <strong class="text-neutral-900 dark:text-neutral-100 block">Resolved Recovery Logs</strong>
                        <span class="text-neutral-600 dark:text-neutral-400">Resolved system error records older than {{ config('privacy.retention.resolved_recovery_records_days', 180) }} days are archived.</span>
                    </div>
                    <div class="p-3.5 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/40 space-y-1">
                        <strong class="text-neutral-900 dark:text-neutral-100 block">Temporary Chat Scratch Files</strong>
                        <span class="text-neutral-600 dark:text-neutral-400">Temporary AI assistant upload attachments older than {{ config('privacy.retention.temporary_chat_attachments_days', 30) }} days are scrubbed.</span>
                    </div>
                </div>

                <div class="rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 p-4 text-xs space-y-2">
                    <div class="flex items-center gap-2 text-emerald-800 dark:text-emerald-300 font-semibold">
                        <x-ui.icon name="shield-check" class="h-4 w-4" />
                        <span>Permanent Audit Trail Invariant</span>
                    </div>
                    <p class="text-emerald-700 dark:text-emerald-400 leading-relaxed">
                        In accordance with hospital forensic accountability and National Archives of the Philippines (NAP) evidentiary guidelines, all rows in the <code class="font-mono text-emerald-900 dark:text-emerald-200">audit_logs</code> table are strictly append-only and cannot be altered or purged by any retention sweep or user role.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Record Incident Modal (Global) --}}
    <x-ui.modal name="record-incident-modal" title="Record Security Incident (ISO 27001 Control A.5.24)" maxWidth="lg">
        <form method="POST" action="{{ route('admin.privacy.incidents.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">Incident Title <span class="text-danger-600">*</span></label>
                <input type="text" name="title" required class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-700 text-xs" placeholder="Brief summary of security event (e.g. Repeated unauthorized login attempts)...">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">Incident Type <span class="text-danger-600">*</span></label>
                    <select name="incident_type" required class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-700 text-xs">
                        <option value="unauthorized_access_attempt">Unauthorized Access Attempt</option>
                        <option value="credential_compromise">Credential Compromise</option>
                        <option value="data_leakage_suspected">Suspected Data Leakage</option>
                        <option value="system_availability_interruption">System Availability Interruption</option>
                        <option value="policy_violation">Internal Policy Violation</option>
                        <option value="malware_suspicion">Suspicious File / Malware Activity</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">Severity Level <span class="text-danger-600">*</span></label>
                    <select name="severity" required class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-700 text-xs">
                        <option value="low">Low (Negligible risk)</option>
                        <option value="medium" selected>Medium (Internal anomaly)</option>
                        <option value="high">High (Elevated security concern)</option>
                        <option value="critical">Critical (Potential data breach)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">Incident Narrative &amp; Observed Indicators <span class="text-danger-600">*</span></label>
                <textarea name="description" rows="4" required class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-700 text-xs" placeholder="Detail observed telemetry, affected IPs, timestamps, and impacted components..."></textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">Initial Containment Actions</label>
                <textarea name="containment_actions" rows="2" class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-700 text-xs" placeholder="Immediate mitigations applied (e.g. account locked, session revoked)..."></textarea>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" id="is_breach_initial" name="is_reportable_breach" value="1" class="rounded border-neutral-300 text-danger-600">
                <label for="is_breach_initial" class="text-xs text-neutral-700 dark:text-neutral-300 font-medium">
                    Preliminary indication of Personal Data Breach (NPC Circular 16-03 statutory 72h window)
                </label>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-neutral-200 dark:border-neutral-800">
                <button type="button" x-on:click="$dispatch('close-modal', 'record-incident-modal')" class="px-3 py-1.5 text-xs rounded border border-neutral-300">Cancel</button>
                <button type="submit" class="px-3 py-1.5 text-xs rounded bg-danger-600 text-white font-semibold">Log Security Incident</button>
            </div>
        </form>
    </x-ui.modal>
</x-app-layout>
