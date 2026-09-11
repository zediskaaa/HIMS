<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\SystemRecoveryRecord;
use App\Services\AuditLogger;
use App\Services\Recovery\SmartRetryService;
use App\Services\Recovery\SystemHealthService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

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
            'status' => ['nullable', Rule::in(['pending', 'in_progress', 'resolved', 'retried', 'ignored', 'failed_permanently'])],
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
                    ->orWhere('exception_class', 'like', $term);
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

        $records = $query->paginate(15)->withQueryString();

        $failedJobsCount = 0;
        try {
            if (Schema::hasTable('failed_jobs')) {
                $failedJobsCount = DB::table('failed_jobs')->count();
            }
        } catch (Throwable) {
            $failedJobsCount = 0;
        }

        $metrics = [
            'total' => SystemRecoveryRecord::count(),
            'pending' => SystemRecoveryRecord::pending()->count(),
            'resolved' => SystemRecoveryRecord::resolved()->count(),
            'failed_jobs' => $failedJobsCount,
        ];

        $quickHealth = [
            'database' => $this->healthService->checkDatabase(),
            'queue' => $this->healthService->checkQueue(),
            'storage' => $this->healthService->checkStorage(),
            'cache' => $this->healthService->checkCache(),
        ];

        $recentFailedJobs = $this->healthService->getRecentFailedJobs(5);
        $availableModules = SystemRecoveryRecord::distinct()->pluck('module')->filter()->values();

        return view('super-admin.recovery.index', [
            'records' => $records,
            'filters' => $filters,
            'metrics' => $metrics,
            'quickHealth' => $quickHealth,
            'recentFailedJobs' => $recentFailedJobs,
            'availableModules' => $availableModules,
        ]);
    }

    public function show(SystemRecoveryRecord $record, Request $request): JsonResponse|View
    {
        $record->load(['user', 'resolvedBy']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'record' => $record,
            ]);
        }

        return view('super-admin.recovery.show', [
            'record' => $record,
        ]);
    }

    public function retry(SystemRecoveryRecord $record): RedirectResponse
    {
        /** @var \App\Models\User $actor */
        $actor = Auth::user();

        try {
            $this->retryService->retry($record, $actor);

            return back()->with('success', sprintf(
                'Incident #%s has been successfully retried and recovered.',
                $record->error_id
            ));
        } catch (Throwable $e) {
            return back()->with('error', sprintf(
                'Smart retry for incident #%s failed: %s',
                $record->error_id,
                $e->getMessage()
            ));
        }
    }

    public function resolve(Request $request, SystemRecoveryRecord $record): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var \App\Models\User $actor */
        $actor = Auth::user();

        $oldStatus = $record->status;
        $record->update([
            'status' => 'resolved',
            'resolved_by_user_id' => $actor->getKey(),
            'resolved_at' => now(),
            'resolution_notes' => $validated['notes'] ?? 'Manually marked as resolved by Super Administrator.',
        ]);

        $this->auditLogger->log(
            action: AuditAction::SystemOperationRecovered,
            actor: $actor,
            description: sprintf(
                'Super Administrator marked incident [%s] as resolved: %s',
                $record->error_id,
                $record->resolution_notes
            ),
            target: $record,
            targetName: $record->error_id,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'resolved', 'resolved_at' => now()->toIso8601String()],
            module: 'System Recovery',
            category: 'System',
            outcome: 'success'
        );

        return back()->with('success', sprintf('Incident #%s marked as resolved.', $record->error_id));
    }

    public function ignore(SystemRecoveryRecord $record): RedirectResponse
    {
        /** @var \App\Models\User $actor */
        $actor = Auth::user();

        $oldStatus = $record->status;
        $record->update([
            'status' => 'ignored',
            'resolved_by_user_id' => $actor->getKey(),
            'resolved_at' => now(),
            'resolution_notes' => 'Dismissed / ignored by Super Administrator.',
        ]);

        $this->auditLogger->log(
            action: AuditAction::TriggeredRecoveryAction,
            actor: $actor,
            description: sprintf('Super Administrator ignored incident [%s].', $record->error_id),
            target: $record,
            targetName: $record->error_id,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'ignored'],
            module: 'System Recovery',
            category: 'System'
        );

        return back()->with('info', sprintf('Incident #%s dismissed.', $record->error_id));
    }

    public function health(): View
    {
        $diagnostics = $this->healthService->runFullDiagnostics();
        $recentFailedJobs = $this->healthService->getRecentFailedJobs(15);

        return view('super-admin.recovery.health', [
            'diagnostics' => $diagnostics,
            'recentFailedJobs' => $recentFailedJobs,
        ]);
    }

    public function rebuildCache(): RedirectResponse
    {
        /** @var \App\Models\User $actor */
        $actor = Auth::user();

        $success = $this->healthService->rebuildCache($actor);

        if ($success) {
            return back()->with('success', 'Application cache was cleared and successfully rebuilt.');
        }

        return back()->with('error', 'Failed to clear application cache.');
    }

    public function retryJob(string $uuid): RedirectResponse
    {
        /** @var \App\Models\User $actor */
        $actor = Auth::user();

        $exitCode = Artisan::call('queue:retry', ['id' => [$uuid]]);

        $this->auditLogger->log(
            action: AuditAction::TriggeredRecoveryAction,
            actor: $actor,
            description: sprintf('Super Administrator retried queue job [%s] (Exit code: %d).', $uuid, $exitCode),
            target: null,
            targetName: $uuid,
            oldValues: [],
            newValues: ['queue_job_uuid' => $uuid, 'exit_code' => $exitCode],
            module: 'System Recovery',
            category: 'System'
        );

        if ($exitCode === 0) {
            return back()->with('success', sprintf('Queue job [%s] was re-queued for execution.', $uuid));
        }

        return back()->with('error', sprintf('Failed to retry queue job [%s].', $uuid));
    }

    public function retryAllJobs(): RedirectResponse
    {
        /** @var \App\Models\User $actor */
        $actor = Auth::user();

        $exitCode = $this->healthService->retryAllFailedJobs($actor);

        if ($exitCode === 0) {
            return back()->with('success', 'All recorded failed queue jobs have been re-dispatched.');
        }

        return back()->with('error', 'Failed to dispatch all queue jobs.');
    }
}
