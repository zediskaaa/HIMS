<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\SupplierAccreditationStatus;
use App\Enums\SupplierCompanyProfileStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Supplier;
use App\Models\SupplierInvitation;
use App\Models\User;
use App\Notifications\SupplierInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class SupplierInvitationOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_staff_creates_a_draft_supplier_and_sends_one_vendor_administrator_invitation(): void
    {
        Notification::fake();
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('inventory.suppliers'))
            ->assertOk()
            ->assertSee('Add &amp; Invite Supplier', false)
            ->assertSee('id="create-and-invite-supplier-form"', false)
            ->assertSee('This does not approve the supplier for procurement.');

        $this->post(route('inventory.suppliers.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Draft supplier created and the Vendor Administrator invitation was sent.');

        $supplier = Supplier::query()->where('name', 'Northstar Medical Supply')->firstOrFail();
        $user = User::query()->where('email', 'liaison@northstar.test')->firstOrFail();
        $invitation = SupplierInvitation::query()->whereBelongsTo($supplier)->firstOrFail();

        $this->assertSame(SupplierAccreditationStatus::Draft, $supplier->accreditation_status);
        $this->assertSame(SupplierCompanyProfileStatus::Draft, $supplier->company_profile_status);
        $this->assertFalse($supplier->isProcurementEligible());
        $this->assertSame($supplier->id, $user->supplier_id);
        $this->assertSame(UserRole::VendorAdministrator, $user->role);
        $this->assertSame(UserStatus::PendingActivation, $user->status);
        $this->assertSame(SupplierInvitation::DELIVERY_SENT, $invitation->delivery_status);
        $this->assertNotNull($invitation->sent_at);
        $this->assertTrue($invitation->expires_at->isFuture());
        Notification::assertSentTo($user, SupplierInvitationNotification::class);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::SentSupplierInvitation->value,
            'target_id' => (string) $invitation->id,
        ]);
    }

    public function test_invitation_link_is_expiring_recipient_bound_and_cannot_be_replayed_after_activation(): void
    {
        Notification::fake();
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin, 'admin')->post(route('inventory.suppliers.store'), $this->payload());
        auth('admin')->logout();

        $user = User::query()->where('email', 'liaison@northstar.test')->firstOrFail();
        $token = $this->invitationToken($user);

        $this->get(route('supplier-invitations.accept', $token))
            ->assertRedirect(route('activation.password'))
            ->assertSessionHas('status', 'Supplier invitation accepted. Create your password to continue onboarding.');

        $this->post(route('activation.password.store'), [
            'password' => 'Supplier2!Secure',
            'password_confirmation' => 'Supplier2!Secure',
        ])->assertRedirect(route('supplier.login'));

        $user->refresh();
        $invitation = $user->supplierInvitation()->firstOrFail();
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertTrue(Hash::check('Supplier2!Secure', $user->password));
        $this->assertSame(SupplierInvitation::STATUS_ACCEPTED, $invitation->status);
        $this->assertNotNull($invitation->accepted_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::AcceptedSupplierInvitation->value,
            'target_id' => (string) $invitation->id,
        ]);

        $this->get(route('supplier-invitations.accept', $token))
            ->assertRedirect(route('activation.start'))
            ->assertSessionHasErrors('email');
    }

    public function test_expired_revoked_and_superseded_invitation_links_are_rejected(): void
    {
        Notification::fake();
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin, 'admin')->post(route('inventory.suppliers.store'), $this->payload());
        $user = User::query()->where('email', 'liaison@northstar.test')->firstOrFail();
        $invitation = $user->supplierInvitation()->firstOrFail();
        $firstToken = $this->invitationToken($user);

        $invitation->update(['expires_at' => now()->subSecond()]);
        auth('admin')->logout();
        $this->get(route('supplier-invitations.accept', $firstToken))->assertSessionHasErrors('email');

        $this->actingAs($admin, 'admin')->post(route('inventory.suppliers.invitations.resend', [$invitation->supplier, $invitation]))
            ->assertSessionHas('success');
        $secondToken = $this->invitationToken($user);
        $this->assertNotSame($firstToken, $secondToken);
        auth('admin')->logout();
        $this->get(route('supplier-invitations.accept', $firstToken))->assertSessionHasErrors('email');

        $this->actingAs($admin, 'admin')->post(route('inventory.suppliers.invitations.revoke', [$invitation->supplier, $invitation]))
            ->assertSessionHas('success');
        auth('admin')->logout();
        $this->get(route('supplier-invitations.accept', $secondToken))->assertSessionHasErrors('email');
        $this->assertSame(SupplierInvitation::STATUS_REVOKED, $invitation->fresh()->status);
        $this->assertSame(UserStatus::Cancelled, $user->fresh()->status);

        $this->actingAs($admin, 'admin')->post(route('inventory.suppliers.invitations.resend', [$invitation->supplier, $invitation]))
            ->assertSessionHas('success');
        $thirdToken = $this->invitationToken($user);
        $this->assertNotSame($secondToken, $thirdToken);
        $this->assertSame(SupplierInvitation::STATUS_PENDING, $invitation->fresh()->status);
        $this->assertSame(UserStatus::PendingActivation, $user->fresh()->status);
        auth('admin')->logout();
        $this->get(route('supplier-invitations.accept', $secondToken))->assertSessionHasErrors('email');
        $this->get(route('supplier-invitations.accept', $thirdToken))->assertRedirect(route('activation.password'));
    }

    public function test_delivery_failure_preserves_a_retryable_supplier_and_pending_account(): void
    {
        $admin = User::factory()->administrator()->create();
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Simulated mail transport failure.'));

        $this->actingAs($admin, 'admin')->post(route('inventory.suppliers.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning');

        $supplier = Supplier::query()->where('name', 'Northstar Medical Supply')->firstOrFail();
        $user = User::query()->where('email', 'liaison@northstar.test')->firstOrFail();
        $invitation = $user->supplierInvitation()->firstOrFail();
        $this->assertSame($supplier->id, $user->supplier_id);
        $this->assertSame(UserStatus::PendingActivation, $user->status);
        $this->assertSame(SupplierInvitation::DELIVERY_FAILED, $invitation->delivery_status);
        $this->assertNotNull($invitation->delivery_failed_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::FailedSupplierInvitation->value,
            'target_id' => (string) $invitation->id,
            'outcome' => 'failure',
        ]);
    }

    public function test_duplicate_and_unauthorized_combined_invites_do_not_create_extra_suppliers(): void
    {
        Notification::fake();
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin, 'admin')->post(route('inventory.suppliers.store'), $this->payload());
        $this->post(route('inventory.suppliers.store'), [
            ...$this->payload(),
            'name' => 'Different Supplier Name',
        ])->assertSessionHasErrors('invitation_email');
        $this->assertDatabaseCount('suppliers', 1);
        $this->assertDatabaseCount('supplier_invitations', 1);

        auth('admin')->logout();
        $manager = User::factory()->inventoryManager()->create();
        $this->actingAs($manager)->post(route('inventory.suppliers.store'), [
            ...$this->payload(),
            'name' => 'Unauthorized Supplier',
            'invitation_email' => 'other@supplier.test',
        ])->assertForbidden();
        $this->assertDatabaseMissing('suppliers', ['name' => 'Unauthorized Supplier']);
    }

    public function test_duplicate_invitation_phone_returns_validation_error_without_creating_supplier(): void
    {
        Notification::fake();
        $admin = User::factory()->administrator()->create();
        User::factory()->create(['phone' => '09175550123']);

        $this->actingAs($admin, 'admin')->post(route('inventory.suppliers.store'), [
            ...$this->payload(),
            'invitation_phone' => '09175550123',
        ])->assertSessionHasErrors([
            'invitation_phone' => 'This mobile phone number is already assigned to another account.',
        ]);

        $this->assertDatabaseMissing('suppliers', ['name' => 'Northstar Medical Supply']);
        $this->assertDatabaseMissing('users', ['email' => 'liaison@northstar.test']);
    }

    /** @return array<string, string> */
    private function payload(): array
    {
        return [
            '_supplier_form' => 'create',
            'send_invitation' => '1',
            'name' => 'Northstar Medical Supply',
            'trade_name' => 'Northstar Medical',
            'invitation_first_name' => 'Aira',
            'invitation_surname' => 'Santos',
            'invitation_email' => 'liaison@northstar.test',
        ];
    }

    private function invitationToken(User $user): string
    {
        $token = '';
        Notification::assertSentTo($user, SupplierInvitationNotification::class, function (SupplierInvitationNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        return $token;
    }
}
