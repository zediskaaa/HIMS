<?php

namespace App\Services\Privacy;

use App\Enums\AuditAction;
use App\Models\LogisticsDocument;
use App\Models\SystemRecoveryRecord;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DataRetentionService
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Evaluate and optionally sweep expired ephemeral records according to policy.
     * Audit logs and inventory ledgers are NEVER purged.
     *
     * @return array{
     *     dry_run: bool,
     *     notifications_purged: int,
     *     resolved_recovery_records_purged: int,
     *     expired_documents_flagged: int,
     *     temporary_files_cleared: int
     * }
     */
    public function sweepEphemeralData(bool $dryRun = false, ?User $actor = null): array
    {
        $notificationCutoffDays = max(30, (int) config('privacy.retention.expired_notifications_days', 90));
        $recoveryCutoffDays = max(90, (int) config('privacy.retention.resolved_recovery_records_days', 180));
        $chatTempCutoffDays = max(7, (int) config('privacy.retention.temporary_chat_attachments_days', 30));

        $notificationCutoff = now()->subDays($notificationCutoffDays);
        $recoveryCutoff = now()->subDays($recoveryCutoffDays);
        $tempCutoff = now()->subDays($chatTempCutoffDays);

        // 1. Expired notifications query
        $notificationsQuery = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', $notificationCutoff);
        $notificationsCount = $notificationsQuery->count();

        // 2. Old resolved recovery records
        $recoveryQuery = SystemRecoveryRecord::query()
            ->where('status', 'resolved')
            ->where('resolved_at', '<', $recoveryCutoff);
        $recoveryCount = $recoveryQuery->count();

        // 3. Logistics documents past NAP retention date (flagged, not deleted)
        $expiredDocsQuery = LogisticsDocument::query()
            ->whereNotNull('retention_until')
            ->where('retention_until', '<=', now()->toDateString())
            ->where('status', '!=', 'archived');
        $expiredDocsCount = $expiredDocsQuery->count();

        // 4. Temporary chat files/scratch in storage
        $tempFilesCleared = 0;
        $tempDirs = [
            storage_path('app/temp'),
            storage_path('app/public/temp'),
        ];

        foreach ($tempDirs as $dir) {
            if (File::isDirectory($dir)) {
                $files = File::files($dir);
                foreach ($files as $file) {
                    if (File::lastModified($file->getPathname()) < $tempCutoff->timestamp) {
                        $tempFilesCleared++;
                        if (! $dryRun) {
                            File::delete($file->getPathname());
                        }
                    }
                }
            }
        }

        if (! $dryRun) {
            if ($notificationsCount > 0) {
                $notificationsQuery->delete();
            }

            if ($recoveryCount > 0) {
                $recoveryQuery->delete();
            }

            $this->auditLogger->record(
                action: AuditAction::ExecutedDataRetention,
                actor: $actor,
                description: "Executed data retention sweep: {$notificationsCount} notifications, {$recoveryCount} resolved recovery records purged; {$expiredDocsCount} NAP documents flagged for disposal review.",
                newValues: [
                    'notifications_purged' => $notificationsCount,
                    'recovery_purged' => $recoveryCount,
                    'expired_documents_flagged' => $expiredDocsCount,
                    'temp_files_cleared' => $tempFilesCleared,
                ]
            );
        }

        return [
            'dry_run' => $dryRun,
            'notifications_purged' => $notificationsCount,
            'resolved_recovery_records_purged' => $recoveryCount,
            'expired_documents_flagged' => $expiredDocsCount,
            'temporary_files_cleared' => $tempFilesCleared,
        ];
    }
}
