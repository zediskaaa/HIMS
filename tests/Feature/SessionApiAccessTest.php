<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The inventory Blade screens fetch /api/v1/* from the browser using the
 * session cookie, not a bearer token. actingAs() sets the guard directly and
 * therefore passes even when session middleware is missing from the api group,
 * so these tests log in through the real login route instead — that is the only
 * way to catch a 401 that a user would actually hit.
 */
class SessionApiAccessTest extends TestCase
{
    use RefreshDatabase;

    private function loginThroughForm(bool $supplierManager = false): User
    {
        $factory = User::factory();
        $user = ($supplierManager ? $factory->inventoryManager() : $factory)->create(['password' => bcrypt('password')]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);

        // The auth manager caches the user it resolved during /login, so without
        // this the next request would report an authenticated user even when the
        // api group cannot read the session at all. Forget the guards to force a
        // genuine re-resolution from the session cookie, as a browser does.
        $this->app['auth']->forgetGuards();

        return $user;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function endpointProvider(): array
    {
        return [
            'demand plans' => ['/api/v1/demand-plans'],
            'procurement requests' => ['/api/v1/procurement-requests'],
            'supplier quotes' => ['/api/v1/supplier-quotes'],
            'purchase orders' => ['/api/v1/purchase-orders'],
            'inventory items' => ['/api/v1/inventory-items'],
            'stock movements' => ['/api/v1/stock-movements'],
            'suppliers' => ['/api/v1/suppliers'],
            'storage locations' => ['/api/v1/storage-locations'],
            'dashboard summary' => ['/api/v1/dashboard-summary'],
        ];
    }

    #[DataProvider('endpointProvider')]
    public function test_session_authenticated_browser_can_read_endpoint(string $endpoint): void
    {
        $this->loginThroughForm(in_array($endpoint, [
            '/api/v1/suppliers',
            '/api/v1/procurement-requests',
            '/api/v1/supplier-quotes',
            '/api/v1/purchase-orders',
        ], true));

        $response = $this->getJson($endpoint);

        $response->assertStatus(200);
    }

    public function test_unauthenticated_request_is_still_rejected(): void
    {
        $this->getJson('/api/v1/demand-plans')->assertStatus(401);
    }

    public function test_session_authenticated_browser_can_write_through_the_api(): void
    {
        $this->loginThroughForm(supplierManager: true);

        // Mirrors the inline create forms: a POST carrying the session cookie.
        $response = $this->postJson('/api/v1/suppliers', [
            'name' => 'Session Write Co',
            'contact_person' => 'Rosa Lim',
            'email' => 'rosa@sessionwrite.test',
            'phone' => '09170000001',
            'address' => 'Cebu City',
            'status' => 'active',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('suppliers', ['name' => 'Session Write Co']);
    }
}
