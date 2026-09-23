<x-ui.card
    :title="__('Data Retention & Privacy Rights')"
    :subtitle="__('Employee user records and transactional ledgers are retained in accordance with Philippine statutory standards.')"
>
    <details class="rounded-lg border border-neutral-200 dark:border-neutral-700">
        <summary class="cursor-pointer px-3 py-2.5 text-xs leading-normal text-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:text-neutral-200">
            <span class="font-semibold">{{ __('Statutory Audit Retention') }}</span>
            <span class="ml-1 text-neutral-600 dark:text-neutral-300">{{ __('Clinical supply and inventory records are preserved; staff accounts are deactivated during offboarding.') }}</span>
        </summary>
        <p class="border-t border-neutral-200 px-3 py-2.5 text-xs leading-relaxed text-neutral-600 dark:border-neutral-700 dark:text-neutral-300">
            {{ __('Under National Archives of the Philippines (NAP) General Records Schedules and Commission on Audit (COA) rules, clinical supply and inventory transaction records are legally preserved. During staff offboarding, user accounts are deactivated rather than deleted.') }}
        </p>
    </details>

    <p class="mt-2 text-xs leading-normal text-neutral-600 dark:text-neutral-300">
        <span class="font-semibold text-neutral-800 dark:text-neutral-200">{{ __('Account Retention') }}:</span>
        {{ __('Your account cannot be permanently deleted.') }}
    </p>

    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="min-w-0 text-xs leading-normal text-neutral-600 dark:text-neutral-300">
            {{ __('Under Republic Act No. 10173, you may request an export copy of your data or petition for rectification/restriction.') }}
        </p>
        <x-ui.button
            variant="secondary"
            size="sm"
            icon="document-text"
            x-data
            x-on:click="$dispatch('open-modal', 'submit-privacy-request')"
            class="w-full shrink-0 sm:w-auto"
        >
            {{ __('Exercise Privacy Rights') }}
        </x-ui.button>
    </div>

    @php
        $myRequests = $user->privacyRequests()->latest()->take(5)->get();
    @endphp

    @if ($myRequests->isNotEmpty())
        <div class="mt-4 pt-3 border-t border-neutral-200 dark:border-neutral-800 space-y-2.5">
            <p class="text-xs font-bold text-neutral-800 dark:text-neutral-200 uppercase tracking-wider">
                {{ __('Your Privacy & Data Portability Requests') }}
            </p>
            <div class="space-y-2">
                @foreach ($myRequests as $myReq)
                    <div class="rounded-lg border border-neutral-200 dark:border-neutral-700 p-3 bg-neutral-50/60 dark:bg-neutral-800/40 text-xs space-y-2">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <span class="font-mono font-semibold text-neutral-900 dark:text-neutral-100">#{{ $myReq->ticket_number }}</span>
                            <span class="font-medium text-neutral-600 dark:text-neutral-300">{{ $myReq->type_label }}</span>
                            @php
                                $b = $myReq->statusBadge();
                                $bClasses = match ($b['tone']) {
                                    'success' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
                                    'primary' => 'bg-primary-100 text-primary-800 dark:bg-primary-950/60 dark:text-primary-300',
                                    'danger' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300',
                                    'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
                                    default => 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-300',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold {{ $bClasses }}">
                                {{ $b['label'] }}
                            </span>
                        </div>

                        @if ($myReq->isDownloadable())
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pt-2 border-t border-neutral-200/80 dark:border-neutral-700/80">
                                <div class="text-[11px] text-neutral-600 dark:text-neutral-400">
                                    <span class="font-semibold text-emerald-700 dark:text-emerald-400">Export Ready:</span>
                                    <span>{{ $myReq->formattedPackageSize() }}</span>
                                    <span> · Valid until {{ $myReq->package_expires_at?->format('M d, Y H:i') }}</span>
                                </div>
                                <a
                                    href="{{ route('privacy.requests.download', $myReq) }}"
                                    class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-primary-600 hover:bg-primary-700 transition shadow-2xs"
                                >
                                    <x-ui.icon name="arrow-down-tray" class="h-3.5 w-3.5 mr-1.5" />
                                    Download Data Package (ZIP)
                                </a>
                            </div>
                        @elseif ($myReq->isExpired())
                            <p class="text-[11px] text-neutral-500 italic">
                                {{ __('Export archive expired on :date. Submit a new request to generate a refreshed package.', ['date' => $myReq->package_expires_at?->format('M d, Y')]) }}
                            </p>
                        @elseif ($myReq->status === 'rejected')
                            <p class="text-[11px] text-rose-600 dark:text-rose-400 italic">
                                {{ __('Refused pursuant to statutory retention: :reason', ['reason' => $myReq->resolution_notes]) }}
                            </p>
                        @else
                            <p class="text-[11px] text-neutral-500">
                                {{ __('Submitted on :date. Under review by Data Protection Officer.', ['date' => $myReq->created_at->format('M d, Y')]) }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-ui.card>
