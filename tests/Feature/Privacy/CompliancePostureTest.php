<?php

namespace Tests\Feature\Privacy;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Privacy\CompliancePostureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompliancePostureTest extends TestCase
{
    use RefreshDatabase;

    public function test_posture_service_evaluates_all_expected_controls_without_error(): void
    {
        $service = app(CompliancePostureService::class);
        $result = $service->evaluate();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('controls', $result);
        $this->assertArrayHasKey('disclaimer', $result);

        $this->assertCount(14, $result['controls']);
        $this->assertSame(14, $result['summary']['total']);

        $sum = $result['summary']['implemented']
            + $result['summary']['partially_implemented']
            + $result['summary']['needs_review']
            + $result['summary']['not_implemented'];

        $this->assertSame(14, $sum);
        $this->assertStringContainsString('does NOT constitute formal ISO certification', $result['disclaimer']);
    }

    public function test_posture_report_structure_and_status_mapping(): void
    {
        $service = app(CompliancePostureService::class);
        $report = $service->getPostureReport();

        $this->assertIsArray($report);
        $this->assertSame(14, $report['total_controls']);
        $this->assertGreaterThan(0, $report['passed_count']);
        $this->assertSame(14, $report['passed_count'] + $report['attention_count']);
        $this->assertCount(14, $report['checks']);

        foreach ($report['checks'] as $check) {
            $this->assertArrayHasKey('id', $check);
            $this->assertArrayHasKey('title', $check);
            $this->assertContains($check['status'], ['pass', 'attention']);
            $this->assertArrayHasKey('evidence', $check);
            $this->assertArrayHasKey('action_required', $check);
        }
    }

    public function test_authorized_super_admin_can_access_privacy_governance_posture(): void
    {
        $admin = User::factory()->superAdministrator()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.privacy.index'));

        $response->assertOk();
        $response->assertSee('Privacy & Security Governance');
        $response->assertSee('Posture');
        $response->assertSee('RA 10173');
        $response->assertSee('ISO/IEC 27001:2022');
    }

    public function test_unauthorized_user_cannot_access_privacy_governance(): void
    {
        $user = User::factory()->pharmacyStaff()->create();

        $response = $this->actingAs($user)
            ->get(route('admin.privacy.index'));

        $response->assertForbidden();
    }
}
