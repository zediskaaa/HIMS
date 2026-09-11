<?php

namespace App\Services\Recovery;

use App\Enums\AuditAction;
use App\Models\SystemRecoveryRecord;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SystemHealthService
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Run all health diagnostics and return a comprehensive health report.
     *
     * @return array<string, mixed>
     */
    public function runFullDiagnostics(): array
    {
        $db = $this->checkDatabase();
        $queue = $this->checkQueue();
        $storage = $this->checkStorage();
        $cache = $this->checkCache();
        $backups = $this->checkBackups();

        $allStatuses = [$db['status'], $queue['status'], $storage['status'], $cache['status']];
        $overallStatus = 'healthy';

        if (in_array('unhealthy', $allStatuses, true)) {
            $overallStatus = 'unhealthy';
        } elseif (in_array('warning', $allStatuses, true) || in_array('degraded', $allStatuses, true)) {
            $overallStatus = 'warning';
        }

        return [
            'overall_status' => $overallStatus,
            'timestamp' => now()->format('Y-m-d H:i:s PHT'),
            'database' => $db,
            'queue' => $queue,
            'storage' => $storage,
            'cache' => $cache,
            'backups' => $backups,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function checkDatabase(): array
    {
        $start = microtime(true);

        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $latencyMs = round((microtime(true) - $start) * 1000, 2);

            $driver = DB::connection()->getDriverName();
            $database = DB::connection()->getDatabaseName();

            return [
                'status' => $latencyMs > 500 ? 'degraded' : 'healthy',
                'driver' => $driver,
                'database' => $database,
                'latency_ms' => $latencyMs,
                'message' => sprintf('Connected to %s (%s) with %sms query latency.', $database, $driver, $latencyMs),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unhealthy',
                'driver' => config('database.default'),
                'database' => 'Unknown',
                'latency_ms' => 0,
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function checkQueue(): array
    {
        try {
            $driver = config('queue.default');
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();

            $status = $failedJobs > 0 ? 'warning' : 'healthy';

            return [
                'status' => $status,
                'driver' => $driver,
                'pending_count' => $pendingJobs,
                'failed_count' => $failedJobs,
                'message' => sprintf(
                    '%d pending jobs in queue; %d failed jobs recorded.',
                    $pendingJobs,
                    $failedJobs
                ),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unhealthy',
                'driver' => config('queue.default'),
                'pending_count' => 0,
                'failed_count' => 0,
                'message' => 'Failed to query queue status: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function checkStorage(): array
    {
        $testFile = storage_path('app/health_test_' . uniqid() . '.tmp');

        try {
            // Write test
            File::put($testFile, 'HIMS_HEALTH_CHECK_' . time());
            // Read test
            $readContent = File::get($testFile);
            // Delete test
            File::delete($testFile);

            $freeBytes = @disk_free_space(storage_path());
            $freeGb = $freeBytes !== false ? round($freeBytes / 1024 / 1024 / 1024, 2) : null;

            $status = ($freeGb !== null && $freeGb < 1.0) ? 'warning' : 'healthy';

            return [
                'status' => $status,
                'write_permission' => true,
                'free_space_gb' => $freeGb,
                'message' => $freeGb !== null
                    ? sprintf('Storage read/write verified. %s GB free disk space.', $freeGb)
                    : 'Storage read/write verified.',
            ];
        } catch (Throwable $e) {
            if (File::exists($testFile)) {
                @File::delete($testFile);
            }

            return [
                'status' => 'unhealthy',
                'write_permission' => false,
                'free_space_gb' => null,
                'message' => 'Storage read/write error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function checkCache(): array
    {
        $start = microtime(true);
        $testKey = 'hims_health_check_' . uniqid();

        try {
            Cache::put($testKey, 'ok', 5);
            $val = Cache::get($testKey);
            Cache::forget($testKey);

            $latencyMs = round((microtime(true) - $start) * 1000, 2);

            if ($val !== 'ok') {
                return [
                    'status' => 'unhealthy',
                    'store' => config('cache.default'),
                    'latency_ms' => $latencyMs,
                    'message' => 'Cache key round-trip failed to return expected value.',
                ];
            }

            return [
                'status' => $latencyMs > 300 ? 'degraded' : 'healthy',
                'store' => config('cache.default'),
                'latency_ms' => $latencyMs,
                'message' => sprintf('Cache driver [%s] operational with %sms round-trip.', config('cache.default'), $latencyMs),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unhealthy',
                'store' => config('cache.default'),
                'latency_ms' => 0,
                'message' => 'Cache operations failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function checkBackups(): array
    {
        $backupDirs = [
            storage_path('app/backups'),
            storage_path('backups'),
            storage_path('app/private/backups'),
        ];

        $latestBackupTime = null;
        $backupCount = 0;

        foreach ($backupDirs as $dir) {
            if (File::isDirectory($dir)) {
                $files = File::files($dir);
                $backupCount += count($files);

                foreach ($files as $file) {
                    $mtime = $file->getMTime();
                    if ($latestBackupTime === null || $mtime > $latestBackupTime) {
                        $latestBackupTime = $mtime;
                    }
                }
            }
        }

        if ($backupCount > 0 && $latestBackupTime !== null) {
            return [
                'status' => 'healthy',
                'backup_count' => $backupCount,
                'last_backup_at' => date('Y-m-d H:i:s PHT', $latestBackupTime),
                'message' => sprintf('%d local backup archive(s) present. Last created %s.', $backupCount, date('Y-m-d H:i:s', $latestBackupTime)),
            ];
        }

        return [
            'status' => 'info',
            'backup_count' => 0,
            'last_backup_at' => null,
            'message' => 'No local disk backup archives detected. Remote snapshots may be managed by cloud infrastructure.',
        ];
    }

    /**
     * @return Collection<int, object>
     */
    public function getRecentFailedJobs(int $limit = 15): Collection
    {
        try {
            return DB::table('failed_jobs')
                ->select(['id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
                ->orderByDesc('failed_at')
                ->limit($limit)
                ->get()
                ->map(function ($job) {
                    $payload = json_decode($job->payload, true);
                    $displayName = $payload['displayName'] ?? 'Unknown Job';
                    $exceptionPreview = Str::limit(strtok((string) $job->exception, "\n"), 150, '...');

                    return (object) [
                        'id' => $job->id,
                        'uuid' => $job->uuid,
                        'queue' => $job->queue,
                        'name' => class_basename($displayName),
                        'exception_preview' => $exceptionPreview,
                        'failed_at' => $job->failed_at,
                    ];
                });
        } catch (Throwable $e) {
            Log::warning('Failed to query failed_jobs: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Flush and rebuild application cache.
     */
    public function rebuildCache(?User $actor = null): bool
    {
        try {
            Artisan::call('cache:clear');

            // Log Maintenance action
            if ($actor) {
                $this->auditLogger->log(
                    action: AuditAction::SystemHealthMaintenance,
                    actor: $actor,
                    description: 'Super Administrator cleared and rebuilt system application cache.',
                    target: null,
                    targetName: 'Application Cache',
                    oldValues: [],
                    newValues: ['action' => 'cache_clear_and_rebuild'],
                    module: 'System Recovery',
                    category: 'System'
                );
            }

            return true;
        } catch (Throwable $e) {
            Log::error('Cache rebuild failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retry all failed queue jobs.
     */
    public function retryAllFailedJobs(?User $actor = null): int
    {
        try {
            $exitCode = Artisan::call('queue:retry', ['id' => ['all']]);

            if ($actor) {
                $this->auditLogger->log(
                    action: AuditAction::SystemHealthMaintenance,
                    actor: $actor,
                    description: 'Super Administrator dispatched retry for all failed queue jobs.',
                    target: null,
                    targetName: 'Queue Worker',
                    oldValues: [],
                    newValues: ['action' => 'retry_all_failed_jobs', 'exit_code' => $exitCode],
                    module: 'System Recovery',
                    category: 'System'
                );
            }

            return $exitCode;
        } catch (Throwable $e) {
            Log::error('Retry all jobs failed: ' . $e->getMessage());
            return 1;
        }
    }
}
