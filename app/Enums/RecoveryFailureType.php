<?php

namespace App\Enums;

/**
 * The kind of system operation that failed.
 *
 * Stored on the recovery record so incidents can be triaged and filtered by
 * the subsystem boundary that produced them.
 */
enum RecoveryFailureType: string
{
    case Import = 'import';
    case Export = 'export';
    case QueueJob = 'queue_job';
    case Database = 'database';
    case FileProcessing = 'file_processing';
    case Integration = 'integration';
    case Application = 'application';

    public function label(): string
    {
        return match ($this) {
            self::Import => 'Data import',
            self::Export => 'Report export',
            self::QueueJob => 'Queued job',
            self::Database => 'Database transaction',
            self::FileProcessing => 'File processing',
            self::Integration => 'External integration',
            self::Application => 'Application error',
        };
    }

    /**
     * The retry handler able to reproduce this operation, if one exists.
     */
    public function defaultRetryHandler(): ?RecoveryRetryHandler
    {
        return match ($this) {
            self::Import => RecoveryRetryHandler::Import,
            self::QueueJob => RecoveryRetryHandler::QueueJob,
            default => null,
        };
    }
}
