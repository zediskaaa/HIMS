<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\Permission;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiInventoryAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.gemini.key', 'test-api-key');
        config()->set('services.gemini.model', 'gemini-test-flash');
        config()->set('services.gemini.base_url', 'https://generativelanguage.googleapis.com');
    }

    public function test_guest_cannot_chat_with_ai_assistant(): void
    {
        $this->postJson(route('dashboard.ai-assistant'), [
            'message' => 'Which items are low in stock?',
        ])->assertUnauthorized();
    }

    public function test_user_without_inventory_or_reports_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        Gate::define(Permission::ViewInventory->value, fn () => false);
        Gate::define(Permission::ViewReports->value, fn () => false);

        $this->actingAs($user)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Which items are low in stock?',
            ])
            ->assertForbidden();
    }

    public function test_authorized_user_with_empty_api_key_receives_grounded_low_stock_fallback(): void
    {
        config()->set('services.gemini.key', '');

        $manager = User::factory()->inventoryManager()->create();
        $item = InventoryItem::create([
            'name' => 'Latex Examination Gloves',
            'sku' => 'GLOV-LTX-001',
            'unit' => 'box',
            'quantity_on_hand' => 4,
            'reorder_level' => 25,
            'safety_stock' => 10,
            'lead_time_days' => 5,
            'status' => 'active',
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Which items are currently low in stock?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('source', 'grounded_fallback');

        $reply = $response->json('reply');
        $this->assertStringContainsString('Latex Examination Gloves', $reply);
        $this->assertStringContainsString('GLOV-LTX-001', $reply);
        $this->assertStringContainsString('4 box', $reply);
        $this->assertStringContainsString('Reorder Level: 25', $reply);
    }

    public function test_item_specific_inquiry_returns_exact_item_stock_and_reorder_level(): void
    {
        config()->set('services.gemini.key', '');

        $manager = User::factory()->inventoryManager()->create();
        $item = InventoryItem::create([
            'name' => 'Surgical Scalpels Size 10',
            'sku' => 'SCALP-10',
            'unit' => 'pcs',
            'quantity_on_hand' => 15,
            'reorder_level' => 30,
            'safety_stock' => 10,
            'lead_time_days' => 7,
            'status' => 'active',
        ]);

        StockMovement::create([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockOut,
            'quantity' => 12,
            'moved_at' => now()->subDays(3),
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Why is Surgical Scalpels considered low stock?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringContainsString('Surgical Scalpels Size 10', $reply);
        $this->assertStringContainsString('SCALP-10', $reply);
        $this->assertStringContainsString('15 pcs', $reply);
        $this->assertStringContainsString('Reorder Level', $reply);
        $this->assertStringContainsString('30 pcs', $reply);
    }

    public function test_expiring_batch_inquiry_returns_batch_information(): void
    {
        config()->set('services.gemini.key', '');

        $manager = User::factory()->inventoryManager()->create();
        $item = InventoryItem::create([
            'name' => 'Amoxicillin 500mg Capsules',
            'sku' => 'AMOX-500',
            'unit' => 'bottle',
            'quantity_on_hand' => 50,
            'reorder_level' => 20,
            'status' => 'active',
        ]);

        ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'BATCH-EXP-2026',
            'expiry_date' => now()->addDays(25)->toDateString(),
            'initial_quantity' => 50,
            'unit_cost' => 150.00,
            'status' => 'active',
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'What inventory items are nearing expiry?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringContainsString('Amoxicillin 500mg Capsules', $reply);
        $this->assertStringContainsString('BATCH-EXP-2026', $reply);
        $this->assertStringContainsString('FEFO', $reply);
    }

    public function test_reorder_recommendations_inquiry_returns_reorder_items(): void
    {
        config()->set('services.gemini.key', '');

        $manager = User::factory()->inventoryManager()->create();
        $item = InventoryItem::create([
            'name' => 'Sterile Saline IV 500ml',
            'sku' => 'IV-SAL-500',
            'unit' => 'bag',
            'quantity_on_hand' => 2,
            'reorder_level' => 40,
            'safety_stock' => 15,
            'status' => 'active',
        ]);

        StockMovement::create([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockOut,
            'quantity' => 20,
            'moved_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'What items should we reorder?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringContainsString('Sterile Saline IV 500ml', $reply);
    }

    public function test_successful_gemini_api_call_returns_ai_generated_answer(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        InventoryItem::create([
            'name' => 'Paracetamol 500mg',
            'sku' => 'PARA-500',
            'unit' => 'box',
            'quantity_on_hand' => 10,
            'reorder_level' => 50,
            'status' => 'active',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => 'Based on current records, Paracetamol 500mg (SKU: PARA-500) has 10 boxes remaining, which is below the reorder level of 50 boxes.',
                        ]],
                    ],
                ]],
            ]),
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Which items are low in stock?',
                'history' => [
                    ['role' => 'user', 'content' => 'Hello'],
                    ['role' => 'assistant', 'content' => 'Hi, how can I assist you with HIMS inventory?'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('source', 'ai');

        $this->assertStringContainsString('Paracetamol 500mg', $response->json('reply'));
        $this->assertStringContainsString('PARA-500', $response->json('reply'));
    }

    public function test_gemini_api_failure_gracefully_falls_back_to_grounded_data(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        InventoryItem::create([
            'name' => 'Iodine Antiseptic Solution',
            'sku' => 'IOD-SOL-100',
            'unit' => 'bottle',
            'quantity_on_hand' => 3,
            'reorder_level' => 15,
            'status' => 'active',
        ]);

        // Mock 503 Service Unavailable from Gemini
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['code' => 503, 'message' => 'Service Unavailable'],
            ], 503),
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Which items are low in stock?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('source', 'grounded_fallback');

        $reply = $response->json('reply');
        $this->assertStringContainsString('Iodine Antiseptic Solution', $reply);
    }

    public function test_request_validation_enforces_required_message_and_length_limits(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        // Empty message
        $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);

        // Message exceeding 1000 characters
        $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => str_repeat('A', 1001),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_dashboard_renders_floating_assistant_for_authorized_user(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('HIMS AI Assistant')
            ->assertSee('Inventory Intelligence Assistant')
            ->assertSee('Ask about inventory, demand, and stock levels.')
            ->assertSee('Which items are low in stock?')
            ->assertSee('What should we reorder?')
            ->assertSee('Explain the demand forecast')
            ->assertSee('Summarize inventory status')
            ->assertSee('himsAiAssistant({', false)
            ->assertSee('loadingStatus', false);
    }

    public function test_context_aware_intent_status_detection_matches_user_inquiry_categories(): void
    {
        $service = app(\App\Services\AiInventoryAssistantService::class);

        // Exact examples from requirement
        $this->assertSame('Checking low-stock items...', $service->determineIntentStatus('Which items are low in stock?'));
        $this->assertSame('Reviewing reorder needs...', $service->determineIntentStatus('What should we reorder?'));
        $this->assertSame('Reviewing Surgical Gloves...', $service->determineIntentStatus('Why is Surgical Gloves high risk?'));
        $this->assertSame('Checking demand forecast...', $service->determineIntentStatus('What is the predicted demand for this item?'));
        $this->assertSame('Reviewing recent stock movements...', $service->determineIntentStatus('Show me the stock movement this month.'));
        $this->assertSame('Checking expiring inventory...', $service->determineIntentStatus('Which items are nearing expiry?'));
        $this->assertSame('Preparing your inventory summary...', $service->determineIntentStatus('Summarize our inventory.'));
        $this->assertSame('Reviewing the demand forecast...', $service->determineIntentStatus('Explain the demand forecast.'));
        $this->assertSame("Reviewing this week's inventory activity...", $service->determineIntentStatus('What happened to our inventory this week?'));
        $this->assertSame('Reviewing IV Solution...', $service->determineIntentStatus('Why is IV Solution at high risk?'));

        // Additional category tests
        $this->assertSame('Checking unavailable items...', $service->determineIntentStatus('What items are out of stock?'));
        $this->assertSame('Checking inventory risk...', $service->determineIntentStatus('Is there any high risk item right now?'));
        $this->assertSame('Reviewing inventory data...', $service->determineIntentStatus('How many items are in storage?'));
        $this->assertSame('Looking into that...', $service->determineIntentStatus('Hello there!'));
    }

    public function test_api_response_includes_context_aware_status_hint(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Which items are low in stock?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('status_hint', 'Checking low-stock items...');

        $this->assertNotEmpty($response->json('reply'));
    }

    public function test_fallback_and_system_prompt_exclude_emojis_and_badge_backticks(): void
    {
        $service = app(\App\Services\AiInventoryAssistantService::class);
        $context = [
            'mentioned_items' => [[
                'name' => 'Surgical Gloves',
                'sku' => 'GLV-SURG-75',
                'current_stock' => 10,
                'reorder_level' => 30,
                'safety_stock' => 15,
                'unit' => 'pairs',
                'lead_time_days' => 7,
                'recent_movements' => [],
            ]],
        ];

        $reply = $service->generateGroundedFallback('Why is Surgical Gloves low stock?', $context);

        // No emojis
        $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', $reply);
        // No backtick badges around SKU
        $this->assertStringNotContainsString('`GLV-SURG-75`', $reply);
        $this->assertStringContainsString('GLV-SURG-75', $reply);
    }

    public function test_sanitize_assistant_text_strips_emojis_and_id_backticks(): void
    {
        $service = app(\App\Services\AiInventoryAssistantService::class);
        $rawText = "Ang item na `AMOX-500` 📦 ay may 5 units lamang ⚠️! Batch: `BATCH-2024-X` ✅.";

        $sanitized = $service->sanitizeAssistantText($rawText);

        $this->assertStringNotContainsString('📦', $sanitized);
        $this->assertStringNotContainsString('⚠️', $sanitized);
        $this->assertStringNotContainsString('✅', $sanitized);
        $this->assertStringNotContainsString('`AMOX-500`', $sanitized);
        $this->assertStringNotContainsString('`BATCH-2024-X`', $sanitized);
        $this->assertStringContainsString('AMOX-500', $sanitized);
        $this->assertStringContainsString('BATCH-2024-X', $sanitized);
    }
}
