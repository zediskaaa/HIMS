<?php

namespace App\Services\Recovery;

use App\Enums\AuditAction;
use App\Exceptions\SafeOperationException;
use App\Models\SystemRecoveryRecord;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDOException;
use Throwable;

class SafeExecutionService
{
    private const MAX_DEADLOCK_RETRIES = 3;

    private const SENSITIVE_KEY_PATTERN = '/(?:password|passphrase|token|secret|otp|totp|authorization|cookie|session|api[_-]?key|private[_-]?key|file[_-]?(?:content|contents)|document[_-]?content)/i';

    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Execute a critical database operation within an atomic transaction.
     * Automatically handles transient deadlocks, executes rollback on failure,
     * logs a recovery record, and raises a user-safe exception.
     *
     * @template T
     * @param callable(): T $callback
     * @param array<string, mixed> $context
     * @param array<string, mixed>|null $retryPayload
     * @return T
     *
     * @throws SafeOperationException
     */
    public function executeTransaction(
        string $module,
        string $operation,
        callable $callback,
        array $context = [],
        bool $isRetryable = false,
        ?string $retryHandler = null,
        ?array $retryPayload = null
    ): mixed {
        $attempts = 0;
        $delayMs = 50;

        while ($attempts < self::MAX_DEADLOCK_RETRIES) {
            $attempts++;

            try {
                return DB::transaction(function () use ($callback) {
                    return $callback();
                }, 3);
            } catch (Throwable $e) {
                if ($this->isTransientDeadlock($e) && $attempts < self::MAX_DEADLOCK_RETRIES) {
                    usleep($delayMs * 1000);
                    $delayMs *= 2;
                    continue;
                }

                // Unrecoverable transaction failure: record failure & safe exception
                $record = $this->recordFailure(
                    exception: $e,
                    module: $module,
                    operation: $operation,
                    context: $context,
                    isRetryable: $isRetryable,
                    retryHandler: $retryHandler,
                    retryPayload: $retryPayload,
                    strategy: 'automatic_rollback'
                );

                $userMessage = $this->getUserFriendlyMessage($module, $operation);

                throw new SafeOperationException(
                    message: $userMessage,
                    errorId: $record->error_id,
                    module: $module,
                    operation: $operation,
                    previous: $e
                );
            }
        }

        throw new SafeOperationException(
            message: 'Operation failed after multiple deadlock retries.',
            errorId: 'REC-TIMEOUT',
            module: $module,
            operation: $operation
        );
    }

    /**
     * Execute a primary operation, falling back to a safe default if an exception occurs.
     *
     * @template T
     * @param callable(): T $primary
     * @param callable(Throwable): T $fallback
     * @param array<string, mixed> $context
     * @return T
     */
    public function executeWithFallback(
        string $module,
        string $operation,
        callable $primary,
        callable $fallback,
        array $context = []
    ): mixed {
        try {
            return $primary();
        } catch (Throwable $e) {
            $this->recordFailure(
                exception: $e,
                module: $module,
                operation: $operation,
                context: $context,
                isRetryable: false,
                strategy: 'safe_default'
            );

            return $fallback($e);
        }
    }

    /**
     * Persist an error failure record with sanitized diagnostics and audit attribution.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed>|null $retryPayload
     */
    public function recordFailure(
        Throwable $exception,
        string $module,
        string $operation,
        array $context = [],
        bool $isRetryable = false,
        ?string $retryHandler = null,
        ?array $retryPayload = null,
        string $strategy = 'automatic_rollback'
    ): SystemRecoveryRecord {
        $errorId = 'REC-' . strtoupper(Str::random(10));
        /** @var User|null $actor */
        $actor = Auth::user();
        $request = app()->bound('request') ? app(Request::class) : null;

        $userSnapshot = $actor !== null
            ? sprintf('%s (%s, %s)', $actor->name, $actor->employee_id ?? 'No ID', $actor->role?->label() ?? 'Unknown')
            : 'System / Automated';

        $sanitizedContext = $this->sanitize($context);
        $sanitizedPayload = $retryPayload !== null ? $this->sanitize($retryPayload) : null;

        $technicalDetails = [
            'error_id' => $errorId,
            'message' => $exception->getMessage(),
            'file' => $this->sanitizePath($exception->getFile()),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'trace' => $this->formatStackTrace($exception),
            'context' => $sanitizedContext,
            'url' => $request?->fullUrl(),
            'method' => $request?->method(),
            'user_agent' => $request?->userAgent(),
        ];

        $summary = Str::limit($exception->getMessage(), 490, '...');
        if (empty(trim($summary))) {
            $summary = sprintf('System exception [%s] during %s in %s.', class_basename($exception), $operation, $module);
        }

        $record = SystemRecoveryRecord::create([
            'error_id' => $errorId,
            'user_id' => $actor?->getKey(),
            'user_snapshot' => $userSnapshot,
            'module' => $module,
            'operation' => $operation,
            'error_summary' => $summary,
            'exception_class' => get_class($exception),
            'technical_details' => $technicalDetails,
            'status' => 'pending',
            'strategy_applied' => $strategy,
            'is_retryable' => $isRetryable,
            'retry_handler' => $retryHandler,
            'retry_payload' => $sanitizedPayload,
            'retry_count' => 0,
            'ip_address' => $request?->ip(),
        ]);

        // Audit Trail Integration
        try {
            $this->auditLogger->log(
                action: AuditAction::SystemOperationFailed,
                actor: $actor,
                description: sprintf(
                    'Operation [%s] in [%s] failed: %s (Incident ID: %s, Strategy: %s).',
                    $operation,
                    $module,
                    $summary,
                    $errorId,
                    $strategy
                ),
                target: $record,
                targetName: $errorId,
                oldValues: [],
                newValues: [
                    'module' => $module,
                    'operation' => $operation,
                    'error_id' => $errorId,
                    'exception_class' => class_basename($exception),
                    'strategy' => $strategy,
                    'is_retryable' => $isRetryable,
                ],
                module: 'System Recovery',
                category: 'System',
                outcome: 'failure'
            );
        } catch (Throwable $auditException) {
            Log::error('Failed to write audit log for recovery record: ' . $auditException->getMessage());
        }

        return $record;
    }

    private function isTransientDeadlock(Throwable $e): bool
    {
        if ($e instanceof QueryException) {
            $code = (string) $e->getCode();
            $msg = strtolower($e->getMessage());

            return $code === '40001'
                || str_contains($msg, 'deadlock')
                || str_contains($msg, 'database is locked')
                || str_contains($msg, 'lock wait timeout');
        }

        if ($e instanceof PDOException) {
            $msg = strtolower($e->getMessage());

            return str_contains($msg, 'deadlock')
                || str_contains($msg, 'database is locked');
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function sanitize(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY_PATTERN, $key)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);
            } elseif (is_string($value) && strlen($value) > 1000) {
                $sanitized[$key] = Str::limit($value, 500, '... [TRUNCATED]');
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    private function sanitizePath(string $path): string
    {
        $base = base_path();
        if (str_starts_with($path, $base)) {
            return ltrim(substr($path, strlen($base)), '/\\');
        }

        return basename($path);
    }

    /**
     * @return array<int, string>
     */
    private function formatStackTrace(Throwable $e): array
    {
        $lines = [];
        $trace = $e->getTrace();
        $count = 0;

        foreach ($trace as $frame) {
            if ($count++ >= 15) {
                $lines[] = '... and more frames';
                break;
            }

            $file = isset($frame['file']) ? $this->sanitizePath($frame['file']) : '[internal function]';
            $line = $frame['line'] ?? '?';
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $func = $frame['function'] ?? '';

            $lines[] = sprintf('#%d %s(%s): %s%s%s()', $count, $file, $line, $class, $type, $func);
        }

        return $lines;
    }

    private function getUserFriendlyMessage(string $module, string $operation): string
    {
        return match ($operation) {
            'data_import' => 'The data import could not be completed. All database records were automatically rolled back to prevent partial corruption.',
            'stock_movement' => 'The inventory movement could not be recorded. Ledger and stock balances remain unchanged.',
            'stock_adjustment' => 'The stock adjustment failed. Inventory quantities have been restored to their previous state.',
            'report_export' => 'The report export could not be generated. Please try again or contact system administration.',
            'purchase_order' => 'The procurement transaction could not be processed. No purchase commitments were recorded.',
            default => 'An unexpected error interrupted this operation. All changes were safely rolled back to preserve data integrity.',
        };
    }
}
