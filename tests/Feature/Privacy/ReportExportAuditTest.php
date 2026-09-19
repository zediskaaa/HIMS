<?php

namespace Tests\Feature\Privacy;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_exporting_report_as_json_logs_audit_trail_event(): void
    {
        $user = User::factory()->inventoryManager()->create();

        $response = $this->actingAs($user)->get(route('inventory.reports.generate', [
            'report_type' => 'stock_status',
            'format' => 'json',
            'period' => '30',
        ]));

        $response->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ExportedSystemReport->value,
            'user_id' => $user->id,
            'module' => 'Reports & Analytics',
        ]);

        $log = AuditLog::where('action', AuditAction::ExportedSystemReport->value)
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('json', $log->new_values['format'] ?? null);
        $this->assertSame('stock_status', $log->new_values['report_type'] ?? null);
    }

    public function test_exporting_report_as_csv_logs_audit_trail_event(): void
    {
        $user = User::factory()->inventoryManager()->create();

        $response = $this->actingAs($user)->get(route('inventory.reports.generate', [
            'report_type' => 'stock_status',
            'format' => 'csv',
            'period' => '7',
        ]));

        $response->assertOk();

        $log = AuditLog::where('action', AuditAction::ExportedSystemReport->value)
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('csv', $log->new_values['format'] ?? null);
        $this->assertStringContainsString('Exported report', $log->description);
    }

    public function test_unauthenticated_guest_cannot_export_reports(): void
    {
        $response = $this->get(route('inventory.reports.generate', [
            'report_type' => 'stock_status',
            'format' => 'json',
        ]));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::ExportedSystemReport->value,
        ]);
    }
}
