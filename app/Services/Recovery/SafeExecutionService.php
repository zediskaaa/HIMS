<?php

namespace App\Services\Recovery;

use App\Enums\AuditAction;
use App\Enums\NotificationDestination;
use App\Enums\NotificationPriority;
use App\Enums\Permission;
use App\Enums\RecoveryFailureType;
use App\Enums\RecoveryRetryHandler;
use App\Enums\RecoveryStatus;
use App\Exceptions\SafeOperationException;
use App\Models\SystemRecoveryRecord;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\HimsNotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDOException;
use Throwable;

/**
 * Records genuine operation failures as recovery incidents.
 *
 * A record is written only when an operation actually failed; nothing here
 * fabricates an incident. Stack traces are never persisted — they belong in the
 * server log, and the stored copy of the failure message is redacted so that
 * credentials captured in an exception never reach the Recovery Center or the
 * Audit Trail.
 */
class SafeExecutionService
{
    private const MAX_DEADLOCK_RETRIES = 3;

    private const SENSITIVE_KEY_PATTERN = '/(?:password|passphrase|token|secret|otp|totp|authorization|cookie|session|api[_-]?key|private[_-]?key|file[_-]?(?:content|contents)|document[_-]?content)/i';

    /**
     * Credential shapes that commonly appear inside an exception message, such as
     * a DSN echoed back by the database driver.
     */
    private const MESSAGE_REDACTIONS = [
        '/(?<=:\/\/)[^:\/\s@]+:[^@\/\s]+(?=@)/' => '[REDACTED]',
        '/\b(password|passwd|pwd|secret|token|api[_-]?key|authorization)\s*[=:]\s*[^\s,;)\]]+/i' => '$1=[REDACTED]',
        '/\bBearer\s+[A-Za-z0-9\-._~+\/]{8,}=*/i' => 'Bearer [REDACTED]',
    ];

    /**
     * Retry-payload keys that name an operational handle rather than a credential.
     *
     * These are exempt from the sensitive-key redaction: the handler reads them
     * back to re-run the operation, so redacting them would silently disable the
     * recovery path they exist to enable. The values still pass through
     * `redact()`, so a credential embedded in the value itself is still removed.
     */
    private const RETRY_PAYLOAD_IDENTIFIER_KEYS = [
        'import_token',
        'failed_job_uuid',
        'job_uuid',
        'job_id',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly HimsNotificationService $notifications,
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
        ?RecoveryRetryHandler $retryHandler = null,
        ?array $retryPayload = null,
        ?string $affectedResource = null,
        ?string $referenceId = null
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
                    strategy: 'automatic_rollback',
                    affectedResource: $affectedResource,
                    referenceId: $referenceId
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
     * The fallback swallows the exception, so this method is responsible for
     * putting the full detail into the server log; the incident itself keeps only
     * the redacted summary.
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
            Log::error('Operation fell back to a safe default after a failure.', [
                'module' => $module,
                'operation' => $operation,
                'exception' => $e,
            ]);

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
     * Callers that swallow the exception are responsible for logging it; the
     * original throwable is deliberately not written to the database.
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
        ?RecoveryRetryHandler $retryHandler = null,
        ?array $retryPayload = null,
        string $strategy = 'automatic_rollback',
        ?RecoveryFailureType $failureType = null,
        ?string $affectedResource = null,
        ?string $referenceId = null
    ): SystemRecoveryRecord {
        $errorId = $this->generateErrorId();
        /** @var User|null $actor */
        $actor = Auth::user();
        $request = app()->bound('request') ? app(Request::class) : null;

        $userSnapshot = $actor !== null
            ? sprintf('%s (%s, %s)', $actor->name, $actor->employee_id ?? 'No ID', $actor->role?->label() ?? 'Unknown')
            : 'System / Automated';

        $sanitizedContext = $this->sanitize($context);
        $sanitizedPayload = $retryPayload !== null
            ? $this->sanitize($retryPayload, self::RETRY_PAYLOAD_IDENTIFIER_KEYS)
            : null;
        $redactedMessage = $this->redact($exception->getMessage());

        $summary = Str::limit($redactedMessage, 490, '...');
        if (trim($summary) === '') {
            $summary = sprintf('System exception [%s] during %s in %s.', class_basename($exception), $operation, $module);
        }

        // Only retryable incidents get a handler; the status vocabulary follows
        // from that so the UI never offers a retry that cannot run.
        $handler = $isRetryable ? $retryHandler : null;
        $status = $handler !== null ? RecoveryStatus::Failed : RecoveryStatus::NotRecoverable;

        $record = SystemRecoveryRecord::create([
            'error_id' => $errorId,
            'user_id' => $actor?->getKey(),
            'user_snapshot' => $userSnapshot,
            'module' => $module,
            'failure_type' => $failureType ?? $this->inferFailureType($module, $operation, $handler),
            'operation' => $operation,
            'error_summary' => $summary,
            'affected_resource' => $affectedResource,
            'reference_id' => $referenceId,
            'exception_class' => $exception::class,
            'technical_details' => [
                'error_id' => $errorId,
                'message' => $redactedMessage,
                'file' => $this->sanitizePath($exception->getFile()),
                'line' => $exception->getLine(),
                'code' => $exception->getCode(),
                'context' => $sanitizedContext,
                'url' => $request?->fullUrl(),
                'method' => $request?->method(),
            ],
            'status' => $status,
            'strategy_applied' => $strategy,
            'is_retryable' => $handler !== null,
            'retry_handler' => $handler,
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
                    'is_retryable' => $handler !== null,
                ],
                outcome: 'failure'
            );
        } catch (Throwable $auditException) {
            Log::error('Failed to write audit log for recovery record: ' . $auditException->getMessage());
        }

        try {
            $this->notifications->sendToPermission(
                Permission::ManageSystemRecovery,
                "system-recovery-record:{$record->id}",
                'Critical system event',
                "Incident {$errorId} in {$module} requires review in the Recovery Center.",
                NotificationPriority::Critical,
                NotificationDestination::RecoveryRecord,
                ['record' => $record->id],
            );
        } catch (Throwable $notificationException) {
            Log::error('Failed to create a recovery notification.', [
                'recovery_record_id' => $record->id,
                'exception_class' => $notificationException::class,
            ]);
        }

        return $record;
    }

    /**
     * Incident references are quoted to operators and used to look incidents up,
     * so a collision would be a real traceability defect rather than a cosmetic one.
     */
    private function generateErrorId(): string
    {
        do {
            $errorId = 'REC-' . strtoupper(Str::random(10));
        } while (SystemRecoveryRecord::where('error_id', $errorId)->exists());

        return $errorId;
    }

    private function inferFailureType(
        string $module,
        string $operation,
        ?RecoveryRetryHandler $handler
    ): RecoveryFailureType {
        if ($handler === RecoveryRetryHandler::Import) {
            return RecoveryFailureType::Import;
        }

        if ($handler === RecoveryRetryHandler::QueueJob) {
            return RecoveryFailureType::QueueJob;
        }

        $haystack = Str::lower($module . ' ' . $operation);

        return match (true) {
            Str::contains($haystack, ['import']) => RecoveryFailureType::Import,
            Str::contains($haystack, ['export', 'report']) => RecoveryFailureType::Export,
            Str::contains($haystack, ['queue', 'job']) => RecoveryFailureType::QueueJob,
            Str::contains($haystack, ['database', 'transaction', 'deadlock']) => RecoveryFailureType::Database,
            Str::contains($haystack, ['file', 'upload', 'document', 'storage']) => RecoveryFailureType::FileProcessing,
            Str::contains($haystack, ['integration', 'api', 'telemetry', 'sync', 'iot']) => RecoveryFailureType::Integration,
            default => RecoveryFailureType::Application,
        };
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
     * @param array<int, string> $identifierKeys keys treated as operational handles
     * @return array<string, mixed>
     */
    public function sanitize(array $data, array $identifierKeys = []): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($key)
                && preg_match(self::SENSITIVE_KEY_PATTERN, $key)
                && ! in_array($key, $identifierKeys, true)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value, $identifierKeys);
            } elseif (is_string($value)) {
                $sanitized[$key] = Str::limit($this->redact($value), 500, '... [TRUNCATED]');
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Strip credential-shaped text from a free-text value. Applied to exception
     * messages and context strings, which may echo a DSN or a token verbatim.
     */
    public function redact(string $text): string
    {
        foreach (self::MESSAGE_REDACTIONS as $pattern => $replacement) {
            $text = (string) preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }

    private function sanitizePath(string $path): string
    {
        $base = base_path();
        if (str_starts_with($path, $base)) {
            return ltrim(substr($path, strlen($base)), '/\\');
        }

        return basename($path);
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
