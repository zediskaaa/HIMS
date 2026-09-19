<?php

namespace Tests\Feature\Privacy;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\SecurityIncident;
use App\Models\User;
use App\Services\Privacy\SecurityIncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityIncidentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthorized_user_cannot_record_security_incident(): void
    {
        $staff = User::factory()->pharmacyStaff()->create();

        $this->actingAs($staff)
            ->post(route('admin.privacy.incidents.store'), [
                'title' => 'Suspicious account behavior',
                'incident_type' => 'unauthorized_access_attempt',
                'severity' => 'high',
                'description' => 'Observed repeated failed logins.',
            ])
            ->assertForbidden();
    }

    public function test_super_admin_can_record_security_incident(): void
    {
        $admin = User::factory()->superAdministrator()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.privacy.incidents.store'), [
                'title' => 'Excessive API Rate Limit Exceeded on Warehouse Telemetry',
                'incident_type' => 'unauthorized_access_attempt',
                'severity' => 'medium',
                'description' => 'Multiple automated bursts observed from untrusted IP subnet attempting to scrape stock ledger.',
                'containment_actions' => 'Source IP blocked at gateway level.',
                'is_reportable_breach' => 0,
            ]);

        $response->assertSessionHas('status');

        $this->assertDatabaseHas('security_incidents', [
            'title' => 'Excessive API Rate Limit Exceeded on Warehouse Telemetry',
            'severity' => 'medium',
            'category' => 'unauthorized_access_attempt',
            'status' => 'detected',
            'is_suspected_breach' => 0,
        ]);

        $incident = SecurityIncident::where('title', 'Excessive API Rate Limit Exceeded on Warehouse Telemetry')->first();
        $this->assertNotNull($incident);
        $this->assertStringStartsWith('INC-', $incident->incident_number);

        // Verify audit log record
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::RecordedSecurityIncident->value,
            'user_id' => $admin->id,
        ]);
    }

    public function test_super_admin_can_update_incident_containment_and_npc_breach_status(): void
    {
        $admin = User::factory()->superAdministrator()->create();

        $incident = SecurityIncident::create([
            'incident_number' => 'INC-2026-TEST',
            'title' => 'Staff terminal left unlocked in public ward',
            'incident_type' => 'policy_violation',
            'severity' => 'low',
            'status' => 'reported',
            'description' => 'Staff terminal observed unattended with active session.',
            'reported_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.privacy.incidents.update', $incident), [
                'status' => 'contained',
                'severity' => 'medium',
                'containment_actions' => 'Session remotely revoked; terminal locked.',
                'remediation_notes' => 'Staff member counseled on screen locking procedure.',
                'is_reportable_breach' => 1,
                'affected_subjects_count' => 15,
                'npc_notified_at' => now()->toDateString(),
            ]);

        $response->assertSessionHas('status');

        $incident->refresh();
        $this->assertSame('contained', $incident->status);
        $this->assertSame('medium', $incident->severity);
        $this->assertTrue((bool) $incident->is_reportable_breach);
        $this->assertSame(15, $incident->affected_subjects_count);
        $this->assertNotNull($incident->npc_notified_at);
        $this->assertSame('Session remotely revoked; terminal locked.', $incident->containment_actions);
    }

    public function test_service_can_record_automated_suspicious_activity(): void
    {
        $service = app(SecurityIncidentService::class);
        $user = User::factory()->create();

        $incident = $service->recordSuspiciousActivity(
            type: 'credential_stuffing_attempt',
            description: '10 consecutive failed logins across multiple accounts from IP 192.168.1.100.',
            severity: 'high',
            actor: $user,
            metadata: ['client_ip' => '192.168.1.100']
        );

        $this->assertNotNull($incident);
        $this->assertSame('high', $incident->severity);
        $this->assertSame('credential_stuffing_attempt', $incident->incident_type);
        $this->assertDatabaseHas('security_incidents', [
            'id' => $incident->id,
            'category' => 'credential_stuffing_attempt',
        ]);
    }
}
