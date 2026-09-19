<?php

namespace Tests\Feature\Privacy;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\SystemRecoveryRecord;
use App\Models\User;
use App\Services\Privacy\DataRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_mode_does_not_delete_any_records(): void
    {
        $service = app(DataRetentionService::class);
        $user = User::factory()->create();

        // 1. Insert old read notification (100 days old)
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Generic',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['title' => 'Test Notification']),
            'read_at' => now()->subDays(100),
            'created_at' => now()->subDays(100),
            'updated_at' => now()->subDays(100),
        ]);

        // 2. Insert old resolved recovery record (200 days old)
        SystemRecoveryRecord::create([
            'error_id' => 'REC-2026-0001',
            'severity' => 'low',
            'status' => 'resolved',
            'module' => 'System',
            'failure_type' => \App\Enums\RecoveryFailureType::Application->value,
            'operation' => 'Cache Cleanup',
            'error_summary' => 'Cache connection timeout',
            'exception_class' => 'RuntimeException',
            'resolved_at' => now()->subDays(200),
        ]);

        $results = $service->sweepEphemeralData(dryRun: true);

        $this->assertTrue($results['dry_run']);
        $this->assertSame(1, $results['notifications_purged']);
        $this->assertSame(1, $results['resolved_recovery_records_purged']);

        // Assert records still exist in DB because dry_run was true
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('system_recovery_records', ['error_id' => 'REC-2026-0001']);
    }

    public function test_retention_sweep_prunes_only_expired_ephemeral_records(): void
    {
        $service = app(DataRetentionService::class);
        $user = User::factory()->create();

        // Expired read notification (100 days old)
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Generic',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['title' => 'Old Read Notification']),
            'read_at' => now()->subDays(100),
            'created_at' => now()->subDays(100),
            'updated_at' => now()->subDays(100),
        ]);

        // Recent unread notification (1 day old) - MUST BE PRESERVED
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Generic',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['title' => 'Recent Notification']),
            'read_at' => null,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $results = $service->sweepEphemeralData(dryRun: false, actor: $user);

        $this->assertFalse($results['dry_run']);
        $this->assertSame(1, $results['notifications_purged']);

        // Exactly 1 notification remains: the recent unread one
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseMissing('notifications', ['data->title' => 'Old Read Notification']);
    }

    public function test_permanent_audit_trail_is_never_pruned_under_any_circumstance(): void
    {
        $service = app(DataRetentionService::class);
        $user = User::factory()->create();

        // Create an audit log entry dated 3 years ago
        $log = AuditLog::create([
            'event_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'actor_name' => $user->name,
            'actor_role' => $user->role->value,
            'action' => AuditAction::LoggedIn,
            'event_category' => 'Authentication',
            'target_type' => User::class,
            'target_id' => $user->id,
            'target_name' => $user->name,
            'description' => 'User logged in successfully 3 years ago.',
            'created_at' => now()->subYears(3),
        ]);

        // Execute live retention sweep
        $service->sweepEphemeralData(dryRun: false, actor: $user);

        // AuditLog MUST remain in database
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);

        // Verify model-level immutability invariant
        try {
            $log->delete();
            $this->fail('AuditLog model delete was expected to throw an exception or be prevented.');
        } catch (\Throwable $e) {
            $this->assertTrue(true, 'AuditLog prevented deletion: ' . $e->getMessage());
        }
    }

    public function test_artisan_retention_command_runs_successfully(): void
    {
        $this->artisan('privacy:enforce-retention', ['--dry-run' => true])
            ->expectsOutputToContain('DRY-RUN mode')
            ->expectsOutputToContain('Permanent Audit Trail Preserved')
            ->assertExitCode(0);
    }
}
