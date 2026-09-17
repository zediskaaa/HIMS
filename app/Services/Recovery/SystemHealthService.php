<?php

namespace App\Services\Recovery;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read-only diagnostics for the Recovery Center health panel, plus the one
 * maintenance action that can be verified on the spot (cache clear).
 *
 * Diagnostics never report a status they did not observe, and never surface
 * driver-level error text: connection strings, usernames, and filesystem paths
 * stay in the server log.
 */
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
            'timestamp' => now()->format('Y-m-d H:i:s T'),
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
            $this->logDiagnosticFailure('database connectivity check', $e);

            return [
                'status' => 'unhealthy',
                'driver' => config('database.default'),
                'database' => 'Unknown',
                'latency_ms' => 0,
                'message' => 'The database connection could not be established. See the application log for details.',
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

            return [
                'status' => $failedJobs > 0 ? 'warning' : 'healthy',
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
            $this->logDiagnosticFailure('queue status check', $e);

            return [
                'status' => 'unhealthy',
                'driver' => config('queue.default'),
                'pending_count' => 0,
                'failed_count' => 0,
                'message' => 'The queue tables could not be read. See the application log for details.',
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function checkStorage(): array
    {
        $testFile = storage_path('app/health_test_' . uniqid() . '.tmp');
        $probe = 'HIMS_HEALTH_CHECK_' . time();

        try {
            File::put($testFile, $probe);

            if (File::get($testFile) !== $probe) {
                throw new \RuntimeException('Storage probe wrote but did not read back the same content.');
            }

            File::delete($testFile);

            $freeBytes = @disk_free_space(storage_path());
            $freeGb = $freeBytes !== false ? round($freeBytes / 1024 / 1024 / 1024, 2) : null;

            return [
                'status' => ($freeGb !== null && $freeGb < 1.0) ? 'warning' : 'healthy',
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

            $this->logDiagnosticFailure('storage read/write check', $e);

            return [
                'status' => 'unhealthy',
                'write_permission' => false,
                'free_space_gb' => null,
                'message' => 'The application storage directory could not be written to. See the application log for details.',
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
            $store = config('cache.default');

            if ($val !== 'ok') {
                return [
                    'status' => 'unhealthy',
                    'store' => $store,
                    'latency_ms' => $latencyMs,
                    'message' => 'Cache key round-trip failed to return the expected value.',
                ];
            }

            return [
                'status' => $latencyMs > 300 ? 'degraded' : 'healthy',
                'store' => $store,
                'latency_ms' => $latencyMs,
                'message' => sprintf('Cache driver [%s] operational with %sms round-trip.', $store, $latencyMs),
            ];
        } catch (Throwable $e) {
            $this->logDiagnosticFailure('cache round-trip check', $e);

            return [
                'status' => 'unhealthy',
                'store' => config('cache.default'),
                'latency_ms' => 0,
                'message' => 'The cache store could not be reached. See the application log for details.',
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
            if (! File::isDirectory($dir)) {
                continue;
            }

            foreach (File::files($dir) as $file) {
                $backupCount++;
                $mtime = $file->getMTime();

                if ($latestBackupTime === null || $mtime > $latestBackupTime) {
                    $latestBackupTime = $mtime;
                }
            }
        }

        if ($backupCount > 0 && $latestBackupTime !== null) {
            $lastBackupAt = now()->setTimestamp($latestBackupTime);

            return [
                'status' => 'healthy',
                'backup_count' => $backupCount,
                'last_backup_at' => $lastBackupAt->format('Y-m-d H:i:s T'),
                'message' => sprintf(
                    '%d local backup archive(s) present. Most recent file written %s.',
                    $backupCount,
                    $lastBackupAt->diffForHumans()
                ),
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
     * Failed queue jobs, with only the exception type exposed.
     *
     * A raw exception message routinely carries the SQL statement, filesystem
     * paths, or connection details, so it is never handed to the UI.
     *
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
                    $payload = json_decode((string) $job->payload, true);
                    $displayName = is_array($payload) ? ($payload['displayName'] ?? null) : null;

                    return (object) [
                        'id' => $job->id,
                        'uuid' => $job->uuid,
                        'queue' => $job->queue,
                        'name' => is_string($displayName) ? class_basename($displayName) : 'Unrecognised job',
                        'exception_type' => $this->exceptionType((string) $job->exception),
                        'failed_at' => $job->failed_at,
                    ];
                });
        } catch (Throwable $e) {
            $this->logDiagnosticFailure('failed jobs query', $e);

            return collect();
        }
    }

    /**
     * Clear the application cache, verifying that the clear actually ran and
     * that the store still works afterwards.
     *
     * @return array{success: bool, message: string}
     */
    public function rebuildCache(?User $actor = null): array
    {
        try {
            $exitCode = Artisan::call('cache:clear');
        } catch (Throwable $e) {
            $this->logDiagnosticFailure('cache clear', $e);
            $result = ['success' => false, 'message' => 'The cache could not be cleared. See the application log for details.'];

            $this->auditCacheClear($actor, $result);

            return $result;
        }

        if ($exitCode !== 0) {
            $result = ['success' => false, 'message' => sprintf('The cache clear command exited with status %d.', $exitCode)];
            $this->auditCacheClear($actor, $result);

            return $result;
        }

        // A clean exit only means the command ran; confirm the store still
        // round-trips before telling the operator the cache is usable.
        $testKey = 'hims_cache_rebuild_check_' . uniqid();

        try {
            Cache::put($testKey, 'ok', 5);
            $verified = Cache::get($testKey) === 'ok';
            Cache::forget($testKey);
        } catch (Throwable $e) {
            $this->logDiagnosticFailure('post-clear cache verification', $e);
            $verified = false;
        }

        $result = $verified
            ? ['success' => true, 'message' => 'The application cache was cleared and the cache store verified as operational.']
            : ['success' => false, 'message' => 'The cache was cleared but the store did not respond to a verification write.'];

        $this->auditCacheClear($actor, $result);

        return $result;
    }

    /**
     * @param  array{success: bool, message: string}  $result
     */
    private function auditCacheClear(?User $actor, array $result): void
    {
        if ($actor === null) {
            return;
        }

        try {
            $this->auditLogger->log(
                action: AuditAction::SystemHealthMaintenance,
                actor: $actor,
                description: 'Super Administrator cleared the application cache. ' . $result['message'],
                targetName: 'Application Cache',
                newValues: ['action' => 'cache_clear', 'success' => $result['success']],
                outcome: $result['success'] ? 'success' : 'failure'
            );
        } catch (Throwable $e) {
            Log::error('Failed to audit a cache clear action.', ['exception' => $e]);
        }
    }

    /**
     * Extract the exception class from a stored failure without exposing the
     * message. Laravel stores "{Class}: {message}" followed by a stack trace.
     */
    private function exceptionType(string $exception): string
    {
        $firstLine = trim(strtok($exception, "\n") ?: '');

        if ($firstLine === '') {
            return 'Unknown failure';
        }

        $candidate = trim(strtok($firstLine, ':') ?: '');

        // Only accept something shaped like a class name; anything else means the
        // stored text cannot be summarised safely.
        if ($candidate === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $candidate) !== 1) {
            return 'Unknown failure';
        }

        return class_basename($candidate);
    }

    private function logDiagnosticFailure(string $check, Throwable $e): void
    {
        Log::warning(sprintf('System health %s failed.', $check), ['exception' => $e]);
    }
}
