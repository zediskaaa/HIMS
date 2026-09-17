<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Enums\RecoveryAttemptOutcome;
use App\Enums\RecoveryStatus;
use App\Exceptions\RecoveryNotRetryableException;
use App\Http\Controllers\Controller;
use App\Models\SystemRecoveryAttempt;
use App\Models\SystemRecoveryRecord;
use App\Services\AuditLogger;
use App\Services\Recovery\SmartRetryService;
use App\Services\Recovery\SystemHealthService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Super Administrator surface for recorded system failures.
 *
 * Every number here is counted from the incident table, one row per incident, so
 * a retried incident is never counted twice. Recovery actions delegate to
 * SmartRetryService, which re-executes the operation and reports what it
 * actually observed.
 */
class RecoveryCenterController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly SystemHealthService $healthService,
        private readonly SmartRetryService $retryService,
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * @return array<int, string>
     */
    public static function middleware(): array
    {
        return [
            'auth:super_admin,web,admin',
            'can:' . Permission::ManageSystemRecovery->value,
        ];
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(RecoveryStatus::class)],
            'module' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $query = SystemRecoveryRecord::with(['user', 'resolvedBy'])->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }

        if (! empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('error_id', 'like', $term)
                    ->orWhere('error_summary', 'like', $term)
                    ->orWhere('operation', 'like', $term)
                    ->orWhere('reference_id', 'like', $term);
            });
        }

        if (! empty($filters['date_from'])) {
            $from = CarbonImmutable::parse($filters['date_from'], config('app.timezone'))->startOfDay();
            $query->where('created_at', '>=', $from);
        }

        if (! empty($filters['date_to'])) {
            $to = CarbonImmutable::parse($filters['date_to'], config('app.timezone'))->endOfDay();
            $query->where('created_at', '<=', $to);
        }

        return view('super-admin.recovery.index', [
            'records' => $query->paginate(15)->withQueryString(),
            'filters' => $filters,
            'metrics' => $this->metrics(),
            'statuses' => RecoveryStatus::cases(),
            'availableModules' => SystemRecoveryRecord::query()->distinct()->pluck('module')->filter()->sort()->values(),
        ]);
    }

    /**
     * Aggregate figures, each counted from the incident table directly.
     *
     * @return array<string, mixed>
     */
    private function metrics(): array
    {
        $total = SystemRecoveryRecord::count();
        $open = SystemRecoveryRecord::open()->count();
        $recovered = SystemRecoveryRecord::recovered()->count();
        $resolved = SystemRecoveryRecord::where('status', RecoveryStatus::Resolved)->count();
        $recoveryFailed = SystemRecoveryRecord::where('status', RecoveryStatus::RecoveryFailed)->count();

        // Only incidents whose recovery attempt produced a verdict can be judged,
        // and each incident contributes exactly once regardless of attempt count.
        $withVerdict = $recovered + $recoveryFailed;

        return [
            'total' => $total,
            'open' => $open,
            'recovered' => $recovered,
            'resolved' => $resolved,
            'recovery_failed' => $recoveryFailed,
            'awaiting_worker' => SystemRecoveryRecord::where('status', RecoveryStatus::RecoveryPending)->count(),
            'failed_attempts' => SystemRecoveryAttempt::where('outcome', RecoveryAttemptOutcome::Failed)->count(),
            'new_last_24h' => SystemRecoveryRecord::where('created_at', '>=', now()->subDay())->count(),
            'success_rate' => $withVerdict > 0 ? (int) round($recovered / $withVerdict * 100) : null,
        ];
    }

    public function show(SystemRecoveryRecord $record): View
    {
        $record->load(['user', 'resolvedBy', 'attempts.actor']);

        return view('super-admin.recovery.show', [
            'record' => $record,
        ]);
    }

    public function retry(SystemRecoveryRecord $record): RedirectResponse
    {
        /** @var \App\Models\User $actor */
        $actor = Auth::user();

        try {
            $outcome = $this->retryService->retry($record, $actor);
        } catch (RecoveryNotRetryableException $e) {
            // The refusal is a state condition, not a fault; its message is safe
            // to show because it never carries internal exception detail.
            return back()->with('error', sprintf('Incident %s was not retried. %s', $record->error_id, $e->getMessage()));
        } catch (Throwable $e) {
            Log::error('Recovery retry failed unexpectedly.', [
                'recovery_record_id' => $record->getKey(),
                'error_id' => $record->error_id,
                'exception' => $e,
            ]);

            return back()->with('error', sprintf(
                'Incident %s could not be retried because of an unexpected error. Technical details were written to the application log.',
                $record->error_id
            ));
        }

        return back()->with(
            match ($outcome->outcome) {
                RecoveryAttemptOutcome::Succeeded => 'success',
                RecoveryAttemptOutcome::Dispatched => 'info',
                default => 'error',
            },
            sprintf('Incident %s. %s', $record->error_id, $outcome->message)
        );
    }

    /**
     * Close an incident after the Super Administrator verified the underlying
     * problem was dealt with outside the automated recovery path.
     */
    public function resolve(Request $request, SystemRecoveryRecord $record): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        if ($record->status->isTerminal()) {
            return back()->with('error', sprintf('Incident %s is already closed as %s.', $record->error_id, $record->status->label()));
        }

        if ($record->status->isInFlight()) {
            return back()->with('error', sprintf(
                'Incident %s is still waiting on a recovery attempt. Let the attempt finish before closing it.',
                $record->error_id
            ));
        }

        /** @var \App\Models\User $actor */
        $actor = Auth::user();
        $previousStatus = $record->status;

        $record->forceFill([
            'status' => RecoveryStatus::Resolved,
            'resolved_by_user_id' => $actor->getKey(),
            'resolved_at' => now(),
            'resolution_notes' => $validated['notes'],
        ])->save();

        $this->auditLogger->log(
            action: AuditAction::ResolvedRecoveryIncident,
            actor: $actor,
            description: sprintf(
                'Super Administrator closed incident [%s] (%s in %s) as resolved after verifying the underlying problem outside the automated recovery path. No retry was executed by this action. Reason: %s',
                $record->error_id,
                $record->operation,
                $record->module,
                $validated['notes']
            ),
            target: $record,
            targetName: $record->error_id,
            oldValues: ['status' => $previousStatus->value],
            newValues: [
                'status' => RecoveryStatus::Resolved->value,
                'resolution_notes' => $validated['notes'],
            ],
            businessReason: $validated['notes'],
        );

        return back()->with('success', sprintf('Incident %s closed as resolved.', $record->error_id));
    }

    public function health(): View
    {
        return view('super-admin.recovery.health', [
            'diagnostics' => $this->healthService->runFullDiagnostics(),
        ]);
    }

    public function rebuildCache(): RedirectResponse
    {
        /** @var \App\Models\User $actor */
        $actor = Auth::user();

        $result = $this->healthService->rebuildCache($actor);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
