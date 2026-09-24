<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\Permission;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemStockLevel;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * The model picks a tool per turn, and two intents sharing the word
     * "expiry" is exactly where it can pick the wrong one: asked which items
     * have no expiry date, it may call the nearing-expiry tool. The answer must
     * still come from the dataset the question asked for.
     */
    public function test_model_calling_the_wrong_expiry_tool_is_answered_from_the_right_records(): void
    {
        config()->set('services.gemini.key', 'test-api-key');

        $manager = User::factory()->inventoryManager()->create();

        InventoryItem::create([
            'name' => 'Surgical Blades Sterile No 10',
            'sku' => 'BLADE-10-ST',
            'unit' => 'box',
            'quantity_on_hand' => 30,
            'reorder_level' => 10,
            'status' => 'active',
        ]);

        $nearing = InventoryItem::create([
            'name' => 'Amoxicillin 500mg Capsules',
            'sku' => 'AMOX-500',
            'unit' => 'bottle',
            'quantity_on_hand' => 40,
            'reorder_level' => 20,
            'status' => 'active',
        ]);

        ItemBatch::create([
            'item_id' => $nearing->id,
            'batch_number' => 'BATCH-EXP-2026',
            'expiry_date' => now()->addDays(20)->toDateString(),
            'initial_quantity' => 40,
            'status' => 'active',
        ]);

        // The model reaches for the near-expiry tool on an expiry-negation question.
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'functionCall' => [
                                'name' => 'get_expiring_batches',
                                'args' => ['days_ahead' => 90],
                            ],
                        ]],
                    ],
                ]],
            ]),
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'anong mga items ang walang expiry?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');

        $this->assertStringContainsString('Surgical Blades Sterile No 10', $reply);
        $this->assertStringContainsString('BLADE-10-ST', $reply);
        $this->assertStringNotContainsString('Amoxicillin 500mg Capsules', $reply);
        $this->assertStringNotContainsString('BATCH-EXP-2026', $reply);
    }

    /**
     * Every tool result has to come back as something a clinician can read.
     * A result shape with no formatter fell through to a raw JSON dump, which
     * is not an answer.
     */
    public function test_every_tool_result_is_rendered_as_a_readable_answer(): void
    {
        config()->set('services.gemini.key', 'test-api-key');
        $manager = User::factory()->inventoryManager()->create();

        $supplier = \App\Models\Supplier::create([
            'name' => 'Metro Pharma Logistics Corp',
            'contact_person' => 'Maria Santos',
            'phone' => '09171234567',
            'email' => 'contact@metropharma.ph',
            'standard_lead_time_days' => 5,
            'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'name' => 'Paracetamol 500mg Tablets',
            'sku' => 'PARA-500-TAB',
            'unit' => 'box',
            'quantity_on_hand' => 40,
            'reorder_level' => 50,
            'safety_stock' => 10,
            'lead_time_days' => 5,
            'unit_cost' => 12.50,
            'supplier_id' => $supplier->id,
            'status' => 'active',
        ]);

        ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'BATCH-EXP-2026',
            'expiry_date' => now()->addDays(20)->toDateString(),
            'initial_quantity' => 40,
            'status' => 'active',
        ]);

        StockMovement::create([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockOut,
            'quantity' => 5,
            'moved_at' => now()->subDays(2),
        ]);

        // An item with no expiry date recorded anywhere.
        InventoryItem::create([
            'name' => 'Surgical Blades Sterile No 10',
            'sku' => 'BLADE-10-ST',
            'unit' => 'box',
            'quantity_on_hand' => 30,
            'reorder_level' => 10,
            'status' => 'active',
        ]);

        $po = \App\Models\PurchaseOrder::create([
            'po_number' => 'PO-2026-TEST-001',
            'supplier_id' => $supplier->id,
            'status' => 'approved',
            'delivery_date' => now()->addDays(5)->toDateString(),
        ]);

        \App\Models\Shipment::create([
            'shipment_number' => 'SHIP-2026-TEST',
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'carrier_name' => 'Express Logistics',
            'tracking_number' => 'TRK-111222333',
            'dispatch_date' => now()->subDays(10)->toDateString(),
            'estimated_delivery_date' => now()->subDays(2)->toDateString(),
            'status' => 'in_transit',
        ]);

        \App\Models\MaterialRequisition::create([
            'requisition_number' => 'REQ-2026-TEST-001',
            'requesting_user_id' => $manager->id,
            'department' => 'Intensive Care Unit',
            'required_date' => now()->addDays(2)->toDateString(),
            'status' => 'pending_approval',
            'urgency' => 'high',
        ]);

        $cases = [
            'near expiry' => ['What is expiring soon?', 'get_expiring_batches', ['days_ahead' => 90], 'nearing expiration'],
            'supplier directory' => ['List our suppliers.', 'search_suppliers', [], 'Metro Pharma Logistics Corp'],
            'supplier items' => ['Which items does Metro Pharma Logistics Corp supply?', 'get_supplier_items', ['supplier_identifier' => 'Metro Pharma Logistics Corp'], 'Items Supplied by'],
            'shipments' => ['Show me the shipments.', 'get_shipments_and_deliveries', [], 'SHIP-2026-TEST'],
            'purchase orders' => ['Show me the purchase orders.', 'get_procurement_records', [], 'PO-2026-TEST-001'],
            'requisitions' => ['What department requisitions are pending?', 'get_department_requisitions', [], 'REQ-2026-TEST-001'],
            'valuation' => ['What is our total inventory valuation?', 'get_inventory_valuation', [], 'Monetary Valuation'],
            'daily summary' => ['Give me a summary of our inventory.', 'get_daily_summary', [], 'Daily Status'],
            'demand forecast' => ['What is the demand forecast for Paracetamol 500mg Tablets?', 'get_demand_forecast', ['item_identifier' => 'Paracetamol 500mg Tablets'], 'Demand Forecast'],
            'stock movements' => ['Show me the stock movements.', 'get_stock_movements', [], 'Stock Movements'],
            'items without expiry' => ['Which items have no expiry date?', 'get_items_without_expiry', [], 'no expiry date'],
            'replenishment' => ['What should we reorder?', 'get_replenishment_recommendations', [], 'Paracetamol 500mg Tablets'],
        ];

        foreach ($cases as $label => [$message, $fnName, $args, $expected]) {
            // One stubbed response per case, handed out in order. Re-registering
            // Http::fake() inside the loop would not work: stubs accumulate and
            // the first one registered keeps matching every request.
            $responses[] = [
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'functionCall' => ['name' => $fnName, 'args' => $args],
                        ]],
                    ],
                ]],
            ];
        }

        $call = 0;
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => function () use (&$call, $responses) {
                return Http::response($responses[$call++] ?? end($responses));
            },
        ]);

        foreach ($cases as $label => [$message, $fnName, $args, $expected]) {
            $reply = $this->actingAs($manager)
                ->postJson(route('dashboard.ai-assistant'), ['message' => $message])
                ->assertOk()
                ->json('reply');

            $this->assertStringContainsString($expected, $reply, "Unexpected answer for {$label}: {$reply}");

            $this->assertStringNotContainsString(
                'Here is the verified information from the HIMS database',
                $reply,
                "Tool result for {$label} was dumped as raw JSON instead of being formatted."
            );
        }
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
        $this->assertSame('Replying...', $service->determineIntentStatus('Hello there!'));
        $this->assertSame('Looking into that...', $service->determineIntentStatus('Random unstructured note'));
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

    public function test_multi_turn_conversation_tracks_ordinal_reference_and_pronoun(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $supplier = \App\Models\Supplier::create([
            'name' => 'Metro Pharma Logistics Corp',
            'contact_person' => 'Maria Santos',
            'phone' => '09171234567',
            'email' => 'contact@metropharma.ph',
            'status' => 'active',
        ]);

        $item1 = InventoryItem::create([
            'name' => 'Paracetamol 500mg Tablets',
            'sku' => 'PARA-500-TAB',
            'unit' => 'box',
            'quantity_on_hand' => 2,
            'reorder_level' => 50,
            'supplier_id' => $supplier->id,
            'status' => 'active',
        ]);

        $item2 = InventoryItem::create([
            'name' => 'Amoxicillin 250mg Suspension',
            'sku' => 'AMOX-250-SUSP',
            'unit' => 'bottle',
            'quantity_on_hand' => 10,
            'reorder_level' => 30,
            'status' => 'active',
        ]);

        // Turn 1: Low stock inquiry
        $res1 = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Which items are low in stock?',
            ])
            ->assertOk();

        $reply1 = $res1->json('reply');
        $this->assertStringContainsString('Paracetamol 500mg Tablets', $reply1);

        // Turn 2: Follow-up using ordinal reference "the first one"
        $historyTurn1 = [
            ['role' => 'user', 'content' => 'Which items are low in stock?'],
            ['role' => 'assistant', 'content' => $reply1],
        ];

        $res2 = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Who provides the first one?',
                'history' => $historyTurn1,
            ])
            ->assertOk();

        $reply2 = $res2->json('reply');
        $this->assertStringContainsString('Metro Pharma Logistics Corp', $reply2);
        $this->assertStringContainsString('Paracetamol 500mg Tablets', $reply2);

        // Turn 3: Follow-up using pronoun "it"
        $historyTurn2 = [
            ...$historyTurn1,
            ['role' => 'user', 'content' => 'Who provides the first one?'],
            ['role' => 'assistant', 'content' => $reply2],
        ];

        $res3 = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'What is its physical stock and status?',
                'history' => $historyTurn2,
            ])
            ->assertOk();

        $reply3 = $res3->json('reply');
        $this->assertStringContainsString('Paracetamol 500mg Tablets', $reply3);
        $this->assertStringContainsString('PARA-500-TAB', $reply3);
    }

    public function test_procurement_financial_data_restricted_from_users_without_permission(): void
    {
        config()->set('services.gemini.key', '');

        $item = InventoryItem::create([
            'name' => 'Cardiac Defibrillator Pads',
            'sku' => 'DEFIB-PAD-01',
            'unit' => 'pair',
            'quantity_on_hand' => 10,
            'reorder_level' => 15,
            'unit_cost' => 4500.00,
            'status' => 'active',
        ]);

        // User without ViewProcurementSensitiveData (Viewer role)
        $viewer = User::factory()->create(['role' => \App\Enums\UserRole::Viewer]);

        $resViewer = $this->actingAs($viewer)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Tell me about Cardiac Defibrillator Pads',
            ])
            ->assertOk();

        $replyViewer = $resViewer->json('reply');
        $this->assertStringContainsString('Cardiac Defibrillator Pads', $replyViewer);
        $this->assertStringNotContainsString('4,500.00', $replyViewer);
        $this->assertStringNotContainsString('Unit Cost', $replyViewer);

        // User with ViewProcurementSensitiveData (Inventory Manager role)
        $manager = User::factory()->inventoryManager()->create();

        $resManager = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Tell me about Cardiac Defibrillator Pads',
            ])
            ->assertOk();

        $replyManager = $resManager->json('reply');
        $this->assertStringContainsString('Cardiac Defibrillator Pads', $replyManager);
        $this->assertStringContainsString('4,500.00', $replyManager);
        $this->assertStringContainsString('Unit Cost', $replyManager);
    }

    public function test_non_existent_item_query_returns_clear_not_found_message(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $res = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Tell me about Vibranium Shield 500mg',
            ])
            ->assertOk();

        $reply = $res->json('reply');
        $this->assertStringContainsString("I couldn't find a matching record", $reply);
        $this->assertStringContainsString('Vibranium Shield 500mg', $reply);
    }

    public function test_out_of_stock_query_lists_depleted_items(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        InventoryItem::create([
            'name' => 'Epinephrine 1mg/ml Ampules',
            'sku' => 'EPI-1MG-AMP',
            'unit' => 'ampule',
            'quantity_on_hand' => 0,
            'reorder_level' => 100,
            'status' => 'active',
        ]);

        $res = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Which items are completely out of stock?',
            ])
            ->assertOk();

        $reply = $res->json('reply');
        $this->assertStringContainsString('Epinephrine 1mg/ml Ampules', $reply);
        $this->assertStringContainsString('EPI-1MG-AMP', $reply);
    }

    public function test_delayed_shipments_query_detects_overdue_deliveries(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $supplier = \App\Models\Supplier::create([
            'name' => 'FastCare Medical Logistics',
            'contact_person' => 'Juan Dela Cruz',
            'phone' => '09189876543',
            'email' => 'info@fastcare.ph',
            'status' => 'active',
        ]);

        $po = \App\Models\PurchaseOrder::create([
            'po_number' => 'PO-2026-DEL-001',
            'supplier_id' => $supplier->id,
            'status' => 'approved',
            'delivery_date' => now()->subDays(3)->toDateString(),
        ]);

        \App\Models\Shipment::create([
            'shipment_number' => 'SHIP-2026-DELAYED',
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'carrier_name' => 'Express Logistics',
            'tracking_number' => 'TRK-999888777',
            'dispatch_date' => now()->subDays(10)->toDateString(),
            'estimated_delivery_date' => now()->subDays(3)->toDateString(),
            'status' => 'in_transit',
        ]);

        $res = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Show me delayed shipments',
            ])
            ->assertOk();

        $reply = $res->json('reply');
        $this->assertStringContainsString('SHIP-2026-DELAYED', $reply);
        $this->assertStringContainsString('FastCare Medical Logistics', $reply);
        $this->assertStringContainsString('OVERDUE', $reply);
    }

    public function test_pending_department_requisitions_query_returns_requisitions(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        \App\Models\MaterialRequisition::create([
            'requisition_number' => 'REQ-2026-ICU-001',
            'requesting_user_id' => $manager->id,
            'department' => 'Intensive Care Unit',
            'required_date' => now()->addDays(2)->toDateString(),
            'status' => 'pending_approval',
            'urgency' => 'high',
        ]);

        $res = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'What department requisitions are pending?',
            ])
            ->assertOk();

        $reply = $res->json('reply');
        $this->assertStringContainsString('REQ-2026-ICU-001', $reply);
        $this->assertStringContainsString('Intensive Care Unit', $reply);
    }

    /**
     * The reported failure: "may item ba na need na talagang orderin?" was
     * answered with "HIMS Inventory Daily Status" because no keyword list
     * contained the word "orderin".
     */
    public function test_reported_taglish_replenishment_question_is_answered_with_reorder_data(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $supplier = \App\Models\Supplier::create([
            'name' => 'Metro Pharma Logistics Corp',
            'contact_person' => 'Maria Santos',
            'phone' => '09171234567',
            'email' => 'contact@metropharma.ph',
            'status' => 'active',
        ]);

        InventoryItem::create([
            'name' => 'Cetirizine 10mg Tablets',
            'sku' => 'CET-10-TAB',
            'unit' => 'box',
            'quantity_on_hand' => 6,
            'reorder_level' => 60,
            'safety_stock' => 10,
            'lead_time_days' => 5,
            'supplier_id' => $supplier->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'may item ba na need na talagang orderin?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringContainsString('Cetirizine 10mg Tablets', $reply);
        $this->assertStringContainsString('CET-10-TAB', $reply);
        $this->assertStringContainsString('Reorder Level: 60', $reply);
        $this->assertStringContainsString('Short of Reorder Level By: 54 box', $reply);
        $this->assertStringContainsString('Metro Pharma Logistics Corp', $reply);
        $this->assertStringNotContainsString('HIMS Inventory Daily Status', $reply);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function replenishmentPhrasings(): array
    {
        return [
            'taglish orderin' => ['may item ba na need na talagang orderin?'],
            'taglish restock' => ['May kailangan ba tayong i-restock?'],
            'taglish what to order' => ['Ano yung kailangan orderin?'],
            'taglish paubos' => ['May paubos na ba?'],
            'taglish critical' => ['Alin yung critical na yung stock?'],
            'english replenishment' => ['Which items need replenishment?'],
            'english anything to order' => ['Anything we need to order?'],
            'taglish buy' => ['May item bang kailangan bilhin?'],
            'english running low' => ["What's running low?"],
            'english should reorder' => ['Which items should we reorder?'],
            'english inventory issues' => ['Any inventory issues I should know about?'],
        ];
    }

    /**
     * Every phrasing of the same question lands on the same answer, phrased by
     * meaning rather than by keyword.
     */
    #[DataProvider('replenishmentPhrasings')]
    public function test_replenishment_phrasings_all_name_the_item_needing_stock(string $message): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        InventoryItem::create([
            'name' => 'Ranitidine 150mg Tablets',
            'sku' => 'RANI-150-TAB',
            'unit' => 'box',
            'quantity_on_hand' => 5,
            'reorder_level' => 80,
            'status' => 'active',
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), ['message' => $message])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringContainsString('Ranitidine 150mg Tablets', $reply, "Did not name the item for: {$message}");
        $this->assertStringNotContainsString('HIMS Inventory Daily Status', $reply, "Fell back to the daily summary for: {$message}");
    }

    /**
     * A follow-up keeps the item under discussion: "nun" refers back to the item
     * listed in the previous answer, so the user does not repeat its name.
     */
    public function test_taglish_follow_up_reports_incoming_stock_for_the_item_under_discussion(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        InventoryItem::create([
            'name' => 'Paracetamol 500mg Tablets',
            'sku' => 'PARA-500-TAB',
            'unit' => 'box',
            'quantity_on_hand' => 2,
            'reorder_level' => 50,
            'status' => 'active',
        ]);

        $first = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Which items are low in stock?',
            ])
            ->assertOk();

        $reply1 = $first->json('reply');
        $this->assertStringContainsString('Paracetamol 500mg Tablets', $reply1);

        $second = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'May pending order na ba nun?',
                'history' => [
                    ['role' => 'user', 'content' => 'Which items are low in stock?'],
                    ['role' => 'assistant', 'content' => $reply1],
                ],
            ])
            ->assertOk();

        $reply2 = $second->json('reply');
        $this->assertStringContainsString('Paracetamol 500mg Tablets', $reply2);
        $this->assertStringContainsString('PARA-500-TAB', $reply2);
        $this->assertStringContainsString('Already On Order', $reply2);
    }

    /**
     * A question outside hospital supply operations is not answered as if it
     * were an inventory question.
     */
    public function test_unrelated_question_is_declined_rather_than_answered_as_inventory(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => "What's the weather today?",
            ])
            ->assertOk();

        $reply = $response->json('reply');
        $this->assertStringContainsString('HIMS', $reply);
        $this->assertStringNotContainsString('HIMS Inventory Daily Status', $reply);
    }

    /**
     * A vague on-topic question asks one clarifying question instead of
     * guessing or reciting the daily summary.
     */
    public function test_vague_question_asks_for_clarification_instead_of_summarising(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'May problema ba?',
            ])
            ->assertOk();

        $reply = $response->json('reply');
        $this->assertStringContainsString('make sure I look at the right thing', $reply);
        $this->assertStringNotContainsString('HIMS Inventory Daily Status', $reply);
    }

    /**
     * The daily summary is still available, but only when it is what was asked
     * for.
     */
    public function test_daily_summary_is_returned_only_for_a_summary_request(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        InventoryItem::create([
            'name' => 'Paracetamol 500mg Tablets',
            'sku' => 'PARA-500-TAB',
            'unit' => 'box',
            'quantity_on_hand' => 2,
            'reorder_level' => 50,
            'status' => 'active',
        ]);

        $summary = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Summarize our inventory.',
            ])
            ->assertOk();

        $this->assertStringContainsString('HIMS Inventory Daily Status', $summary->json('reply'));

        $specific = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'How many Paracetamol 500mg Tablets are in storage?',
            ])
            ->assertOk();

        $this->assertStringContainsString('Paracetamol 500mg Tablets', $specific->json('reply'));
        $this->assertStringNotContainsString('HIMS Inventory Daily Status', $specific->json('reply'));
    }

    /**
     * The reported defect: "anong mga items ang walang expiry?" was answered with
     * batches nearing expiration. The question asks which items have no expiry
     * date recorded; that is a different set of records from the ones about to
     * expire, and it must be answered from that set.
     */
    public function test_items_without_expiry_question_returns_items_lacking_an_expiry_date(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        // No batches at all, so no expiry date is recorded anywhere for it.
        InventoryItem::create([
            'name' => 'Surgical Blades Sterile No 10',
            'sku' => 'BLADE-10-ST',
            'unit' => 'box',
            'quantity_on_hand' => 30,
            'reorder_level' => 10,
            'status' => 'active',
        ]);

        // Has an expiry date on its batch, so it is not part of the answer.
        $expiring = InventoryItem::create([
            'name' => 'Amoxicillin 500mg Capsules',
            'sku' => 'AMOX-500',
            'unit' => 'bottle',
            'quantity_on_hand' => 40,
            'reorder_level' => 20,
            'status' => 'active',
        ]);

        ItemBatch::create([
            'item_id' => $expiring->id,
            'batch_number' => 'BATCH-EXP-2026',
            'expiry_date' => now()->addDays(20)->toDateString(),
            'initial_quantity' => 40,
            'status' => 'active',
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'anong mga items ang walang expiry?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');

        $this->assertStringContainsString('Surgical Blades Sterile No 10', $reply);
        $this->assertStringContainsString('BLADE-10-ST', $reply);
        $this->assertStringContainsString('no expiry date', $reply);

        // The wrong dataset is the defect being fixed.
        $this->assertStringNotContainsString('Amoxicillin 500mg Capsules', $reply);
        $this->assertStringNotContainsString('BATCH-EXP-2026', $reply);
        $this->assertStringNotContainsString('nearing expiration', $reply);
        $this->assertStringNotContainsString('HIMS Inventory Daily Status', $reply);
    }

    /**
     * "walang expiry", "malapit nang mag-expire" and "expired na" all contain a
     * word about expiry and mean three different things. Each has to be answered
     * from its own records.
     */
    public function test_the_three_expiry_readings_are_answered_from_different_records(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        InventoryItem::create([
            'name' => 'Surgical Blades Sterile No 10',
            'sku' => 'BLADE-10-ST',
            'unit' => 'box',
            'quantity_on_hand' => 30,
            'reorder_level' => 10,
            'status' => 'active',
        ]);

        $nearing = InventoryItem::create([
            'name' => 'Amoxicillin 500mg Capsules',
            'sku' => 'AMOX-500',
            'unit' => 'bottle',
            'quantity_on_hand' => 40,
            'reorder_level' => 20,
            'status' => 'active',
        ]);

        ItemBatch::create([
            'item_id' => $nearing->id,
            'batch_number' => 'BATCH-EXP-2026',
            'expiry_date' => now()->addDays(20)->toDateString(),
            'initial_quantity' => 40,
            'status' => 'active',
        ]);

        $expired = InventoryItem::create([
            'name' => 'Insulin Glargine 100IU',
            'sku' => 'INS-GLAR-100',
            'unit' => 'vial',
            'quantity_on_hand' => 12,
            'reorder_level' => 15,
            'status' => 'active',
        ]);

        ItemBatch::create([
            'item_id' => $expired->id,
            'batch_number' => 'BATCH-EXPIRED-2025',
            'expiry_date' => now()->subDays(10)->toDateString(),
            'initial_quantity' => 12,
            'status' => 'active',
        ]);

        $noExpiry = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), ['message' => 'Anong mga items ang walang expiry?'])
            ->assertOk()->json('reply');
        $this->assertStringContainsString('Surgical Blades Sterile No 10', $noExpiry);
        $this->assertStringNotContainsString('Amoxicillin 500mg Capsules', $noExpiry);
        $this->assertStringNotContainsString('Insulin Glargine 100IU', $noExpiry);

        $expiringSoon = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), ['message' => 'Anong mga gamot ang malapit nang mag-expire?'])
            ->assertOk()->json('reply');
        $this->assertStringContainsString('Amoxicillin 500mg Capsules', $expiringSoon);
        $this->assertStringContainsString('BATCH-EXP-2026', $expiringSoon);
        $this->assertStringContainsString('FEFO', $expiringSoon);
        $this->assertStringNotContainsString('Surgical Blades Sterile No 10', $expiringSoon);

        $alreadyExpired = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), ['message' => 'May expired stock ba?'])
            ->assertOk()->json('reply');
        $this->assertStringContainsString('Insulin Glargine 100IU', $alreadyExpired);
        $this->assertStringContainsString('BATCH-EXPIRED-2025', $alreadyExpired);
        $this->assertStringNotContainsString('BATCH-EXP-2026', $alreadyExpired);
    }

    /**
     * The window the user states is the window they get: "expiring this month"
     * and "expiring within 90 days" are different questions.
     */
    public function test_expiry_question_honours_the_stated_window(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $imminent = InventoryItem::create([
            'name' => 'Heparin Sodium 5000IU',
            'sku' => 'HEP-5000-IU',
            'unit' => 'vial',
            'quantity_on_hand' => 25,
            'reorder_level' => 10,
            'status' => 'active',
        ]);

        ItemBatch::create([
            'item_id' => $imminent->id,
            'batch_number' => 'BATCH-SOON',
            // Day zero is already expired; tomorrow is the first active
            // expiring-soon boundary.
            'expiry_date' => now()->addDay()->toDateString(),
            'initial_quantity' => 25,
            'status' => 'active',
        ]);

        $later = InventoryItem::create([
            'name' => 'Dextrose 50% 500ml',
            'sku' => 'DEXT-50-500',
            'unit' => 'bottle',
            'quantity_on_hand' => 60,
            'reorder_level' => 20,
            'status' => 'active',
        ]);

        ItemBatch::create([
            'item_id' => $later->id,
            'batch_number' => 'BATCH-LATER',
            'expiry_date' => now()->addDays(100)->toDateString(),
            'initial_quantity' => 60,
            'status' => 'active',
        ]);

        $thisMonth = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), ['message' => 'Anong items ang mag-e-expire this month?'])
            ->assertOk()->json('reply');
        $this->assertStringContainsString('Heparin Sodium 5000IU', $thisMonth);
        $this->assertStringNotContainsString('Dextrose 50% 500ml', $thisMonth);

        // A 100-day batch is still outside a 90-day horizon.
        $ninetyDays = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), ['message' => 'Show me batches expiring within 90 days'])
            ->assertOk()->json('reply');
        $this->assertStringContainsString('Heparin Sodium 5000IU', $ninetyDays);
        $this->assertStringNotContainsString('Dextrose 50% 500ml', $ninetyDays);
    }

    /**
     * A bare follow-up carries the item under discussion: "Sino supplier?" asks
     * about the item just listed, not for the whole supplier directory.
     */
    public function test_bare_supplier_follow_up_refers_to_the_item_under_discussion(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $supplier = \App\Models\Supplier::create([
            'name' => 'Metro Pharma Logistics Corp',
            'contact_person' => 'Maria Santos',
            'phone' => '09171234567',
            'email' => 'contact@metropharma.ph',
            'status' => 'active',
        ]);

        InventoryItem::create([
            'name' => 'Paracetamol 500mg Tablets',
            'sku' => 'PARA-500-TAB',
            'unit' => 'box',
            'quantity_on_hand' => 2,
            'reorder_level' => 50,
            'supplier_id' => $supplier->id,
            'status' => 'active',
        ]);

        $first = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), ['message' => 'Which items are low in stock?'])
            ->assertOk();

        $reply1 = $first->json('reply');
        $this->assertStringContainsString('Paracetamol 500mg Tablets', $reply1);

        $second = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Sino supplier?',
                'history' => [
                    ['role' => 'user', 'content' => 'Which items are low in stock?'],
                    ['role' => 'assistant', 'content' => $reply1],
                ],
            ])
            ->assertOk();

        $reply2 = $second->json('reply');
        $this->assertStringContainsString('Metro Pharma Logistics Corp', $reply2);
        $this->assertStringContainsString('Paracetamol 500mg Tablets', $reply2);
    }

    /**
     * An unrelated question is declined, not answered with inventory data.
     */
    public function test_unrelated_general_question_is_declined_without_inventory_data(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        InventoryItem::create([
            'name' => 'Paracetamol 500mg Tablets',
            'sku' => 'PARA-500-TAB',
            'unit' => 'box',
            'quantity_on_hand' => 2,
            'reorder_level' => 50,
            'status' => 'active',
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), ['message' => 'What is the capital of France?'])
            ->assertOk();

        $reply = $response->json('reply');
        $this->assertStringContainsString('HIMS', $reply);
        $this->assertStringNotContainsString('Paris', $reply);
        $this->assertStringNotContainsString('Paracetamol 500mg Tablets', $reply);
        $this->assertStringNotContainsString('HIMS Inventory Daily Status', $reply);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function casualHimsPhrasings(): array
    {
        return [
            'paubos' => ['Ano yung paubos?'],
            'orderin' => ['May kailangan bang orderin?'],
            'pending delivery' => ['May pending delivery?'],
            'magkano inventory' => ['Magkano inventory natin?'],
            'nangyari sa stock' => ['Ano nangyari sa stock?'],
            'kulang' => ['May kulang ba?'],
            'mabilis maubos' => ['Alin yung mabilis maubos?'],
            'issue sa inventory' => ['May issue ba sa inventory?'],
            'dapat bantayan' => ['Ano yung dapat bantayan?'],
            'kamusta procurement' => ['Kamusta procurement?'],
            'nasaan delivery' => ['Nasaan yung delivery?'],
            'stock movements' => ['Show me the stock movements.'],
        ];
    }

    /**
     * Natural Filipino, English and Taglish questions about the system are HIMS
     * questions. None of them needs technical wording, and none is turned away.
     */
    #[DataProvider('casualHimsPhrasings')]
    public function test_casual_hims_question_is_answered_rather_than_declined(string $message): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        InventoryItem::create([
            'name' => 'Ranitidine 150mg Tablets',
            'sku' => 'RANI-150-TAB',
            'unit' => 'box',
            'quantity_on_hand' => 5,
            'reorder_level' => 80,
            'status' => 'active',
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), ['message' => $message])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');

        $this->assertStringNotContainsString('outside what I can help with', $reply, "Declined a valid HIMS question: {$message}");
        $this->assertNotSame('', trim($reply), "Empty answer for: {$message}");
    }

    /**
     * "Ano yung mga puwede kong itanong?" asks what the assistant covers. It
     * names no HIMS record, so every keyword branch missed it and it was
     * answered with the out-of-scope refusal — a user asking how to use the
     * assistant was told it could not help them.
     */
    public function test_capability_question_lists_what_the_assistant_can_answer(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), ['message' => 'Ano yung mga puwede kong itanong?'])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');

        $this->assertStringNotContainsString('outside what I can help with', $reply);
        $this->assertStringContainsString('What I can answer', $reply);
        $this->assertStringContainsString('low on stock', $reply);
        $this->assertStringContainsString('nearing expiry', $reply);
        $this->assertStringContainsString('Demand forecasts', $reply);
        $this->assertStringContainsString('Department requisitions', $reply);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function capabilityQuestionPhrasings(): array
    {
        return [
            'taglish itanong' => ['Ano yung mga puwede kong itanong?'],
            'taglish sakop' => ['Anong sakop mo?'],
            'english answer' => ['What can you answer?'],
            'english help' => ['How can you help me?'],
        ];
    }

    #[DataProvider('capabilityQuestionPhrasings')]
    public function test_capability_question_is_recognised_however_it_is_phrased(string $message): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $reply = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), ['message' => $message])
            ->assertOk()
            ->json('reply');

        $this->assertStringContainsString('What I can answer', $reply, "Not answered as a capability question: {$message}");
    }

    /**
     * The list is what this role can actually be given, so it names the areas
     * the role cannot reach instead of offering them and failing later.
     */
    public function test_capability_list_is_scoped_to_the_actors_permissions(): void
    {
        config()->set('services.gemini.key', '');

        $viewer = User::factory()->viewer()->create();
        $viewerReply = $this->actingAs($viewer)
            ->postJson(route('dashboard.ai-assistant'), ['message' => 'What can you answer?'])
            ->assertOk()
            ->json('reply');

        $this->assertStringNotContainsString('Unit costs and the total value', $viewerReply);
        $this->assertStringContainsString('Not available to your role', $viewerReply);
        $this->assertStringContainsString('unit costs, supplier price quotes', $viewerReply);

        $manager = User::factory()->inventoryManager()->create();
        $managerReply = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), ['message' => 'What can you answer?'])
            ->assertOk()
            ->json('reply');

        $this->assertStringContainsString('Unit costs and the total value', $managerReply);
        $this->assertStringNotContainsString('unit costs, supplier price quotes', $managerReply);
    }

    // ---------------------------------------------------------------
    // Expiry sub-intent tests
    // ---------------------------------------------------------------

    /**
     * "Anong mga items ang walang expiry?" must return items with NULL
     * expiry_date — not items nearing expiration.
     */
    public function test_walang_expiry_returns_items_without_expiry_date(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        // Item with a batch that has NO expiry date
        $nonExpiring = InventoryItem::create([
            'name' => 'Wheelchair Standard',
            'sku' => 'EQ-WC-001',
            'unit' => 'unit',
            'quantity_on_hand' => 5,
            'reorder_level' => 2,
            'status' => 'active',
        ]);
        ItemBatch::create([
            'item_id' => $nonExpiring->id,
            'batch_number' => 'WC-2026-001',
            'expiry_date' => null,
            'initial_quantity' => 5,
            'status' => 'active',
        ]);

        // Item WITH an expiry date (should NOT appear in the response)
        $expiring = InventoryItem::create([
            'name' => 'N95 Respirator Mask',
            'sku' => 'PPE-N95-001',
            'unit' => 'box',
            'quantity_on_hand' => 35,
            'reorder_level' => 10,
            'status' => 'active',
        ]);
        ItemBatch::create([
            'item_id' => $expiring->id,
            'batch_number' => 'N95-2026-001',
            'expiry_date' => now()->addDays(85),
            'initial_quantity' => 35,
            'status' => 'active',
        ]);

        $reply = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'anong mga items ang walang expiry?',
            ])
            ->assertOk()
            ->assertJsonPath('source', 'grounded_fallback')
            ->json('reply');

        $this->assertStringContainsString('Wheelchair Standard', $reply);
        $this->assertStringContainsString('no expiry date', $reply);
        // Must NOT contain the nearing-expiry item or nearing-expiry phrasing
        $this->assertStringNotContainsString('nearing expiration', $reply);
        $this->assertStringNotContainsString('FEFO', $reply);
    }

    /**
     * "Which items have no expiry?" (English variant)
     */
    public function test_english_no_expiry_query_returns_items_without_expiry(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $item = InventoryItem::create([
            'name' => 'Surgical Scissors',
            'sku' => 'INSTR-SC-001',
            'unit' => 'piece',
            'quantity_on_hand' => 12,
            'reorder_level' => 3,
            'status' => 'active',
        ]);
        ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'SC-2026-001',
            'expiry_date' => null,
            'initial_quantity' => 12,
            'status' => 'active',
        ]);

        $reply = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'which items have no expiry?',
            ])
            ->assertOk()
            ->json('reply');

        $this->assertStringContainsString('Surgical Scissors', $reply);
        $this->assertStringContainsString('no expiry date', $reply);
    }

    /**
     * When no items without expiry exist, the response should say so.
     */
    public function test_no_expiry_query_handles_zero_results(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $reply = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'may items bang walang expiration date?',
            ])
            ->assertOk()
            ->json('reply');

        $this->assertStringContainsString("couldn't find any items", $reply);
    }

    /**
     * "Ano yung expired na?" must return already-expired batches.
     */
    public function test_expired_na_returns_already_expired_batches(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $item = InventoryItem::create([
            'name' => 'Expired Saline Solution',
            'sku' => 'IV-SAL-001',
            'unit' => 'bag',
            'quantity_on_hand' => 10,
            'reorder_level' => 5,
            'status' => 'active',
        ]);
        ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'SAL-2025-001',
            'expiry_date' => now()->subDays(30),
            'initial_quantity' => 10,
            'status' => 'active',
        ]);

        $reply = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'ano yung expired na?',
            ])
            ->assertOk()
            ->json('reply');

        $this->assertStringContainsString('Expired Saline Solution', $reply);
        $this->assertStringContainsString('expired', strtolower($reply));
        // Must NOT say "nearing expiration"
        $this->assertStringNotContainsString('nearing expiration', $reply);
    }

    /**
     * "Malapit nang mag-expire" must still call the nearing-expiry handler.
     */
    public function test_nearing_expiry_query_still_returns_expiring_batches(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $item = InventoryItem::create([
            'name' => 'Amoxicillin Capsule',
            'sku' => 'MED-AMX-001',
            'unit' => 'capsule',
            'quantity_on_hand' => 200,
            'reorder_level' => 50,
            'status' => 'active',
        ]);
        ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => 'AMX-2026-001',
            'expiry_date' => now()->addDays(45),
            'initial_quantity' => 200,
            'status' => 'active',
        ]);

        $reply = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'ano yung malapit nang mag-expire?',
            ])
            ->assertOk()
            ->json('reply');

        $this->assertStringContainsString('Amoxicillin Capsule', $reply);
        $this->assertStringContainsString('nearing expiration', $reply);
    }

    // ---------------------------------------------------------------
    // Intent resolution unit tests (data provider)
    // ---------------------------------------------------------------

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function intentResolutionCases(): array
    {
        return [
            // NO_EXPIRY
            'walang expiry tagalog'      => ['anong mga items ang walang expiry?', 'no_expiry'],
            'no expiry english'          => ['which items have no expiry?', 'no_expiry'],
            'walang expiration date'     => ['may items bang walang expiration date?', 'no_expiry'],
            'alin walang expiry'         => ['alin yung walang expiry?', 'no_expiry'],
            'hindi nag-e-expire'         => ['ano yung mga hindi nag-e-expire?', 'no_expiry'],
            'without expiry'             => ['items without expiry', 'no_expiry'],

            // EXPIRY (nearing)
            'malapit nang mag-expire'    => ['ano yung malapit nang mag-expire?', 'expiry'],
            'expire within 90 days'      => ['which items expire within 90 days?', 'expiry'],
            'expiring soon'              => ['expiring soon', 'expiry'],
            'mag-e-expire this month'    => ['anong items ang mag-e-expire this month?', 'expiry'],

            // EXPIRED
            'expired na'                 => ['ano yung expired na?', 'expired'],
            'may expired stock'          => ['may expired stock ba?', 'expired'],
            'already expired'            => ['already expired items', 'expired'],

            // REPLENISHMENT
            'need replenishment'         => ['which items need replenishment?', 'replenishment'],
            'kailangan i-restock'        => ['may kailangan bang i-restock?', 'replenishment'],
            'kailangan orderin'          => ['ano yung kailangan orderin?', 'replenishment'],

            // SUPPLIER
            'sino supplier nito'         => ['sino supplier nito?', 'supplier'],

            // OUT_OF_SCOPE
            'math question'              => ['1+1?', 'out_of_scope'],
            'capital of japan'           => ["What's the capital of Japan?", 'out_of_scope'],
            'tell me a joke'             => ['Tell me a joke.', 'out_of_scope'],
            'write a poem'               => ['Write a poem.', 'out_of_scope'],

            // META-SCOPE — no longer capabilities
            'bawal magtanong outside'    => ['bawal ako magtanong na outside sa system or diyan?', 'out_of_scope'],
            'bawal ba magtanong iba'     => ['bawal ba akong magtanong ng iba?', 'out_of_scope'],

            // CAPABILITIES — real capability questions still work
            'pwede kong itanong'         => ['ano ang pwede kong itanong?', 'capabilities'],
            'what can you do'            => ['what can you do?', 'capabilities'],
        ];
    }

    #[DataProvider('intentResolutionCases')]
    public function test_intent_resolver_classifies_correctly(string $query, string $expectedIntent): void
    {
        $resolver = new \App\Services\Ai\ConversationalIntentResolver();
        $result = $resolver->resolve($query);
        $this->assertSame(
            $expectedIntent,
            $result['intent'],
            "Query \"{$query}\" expected intent [{$expectedIntent}] but got [{$result['intent']}] with score {$result['score']}"
        );
    }

    // ---------------------------------------------------------------
    // Scope & out-of-scope tests
    // ---------------------------------------------------------------

    /**
     * Meta-scope questions ("bawal magtanong ng iba?") should get a short,
     * direct answer rather than the full capabilities list.
     */
    public function test_meta_scope_question_does_not_trigger_capabilities_list(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $reply = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'bawal ako magtanong na outside sa system or diyan?',
            ])
            ->assertOk()
            ->json('reply');

        // Should NOT contain the verbose capabilities heading
        $this->assertStringNotContainsString('What I can answer', $reply);
        // Should contain scope-related language
        $this->assertStringContainsString('HIMS', $reply);
    }

    /**
     * Out-of-scope questions produce a short refusal, not the full
     * capabilities list or the daily summary.
     */
    public function test_out_of_scope_gives_short_refusal(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $reply = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => '1+1=?',
            ])
            ->assertOk()
            ->json('reply');

        $this->assertStringContainsString('HIMS', $reply);
        // Must be short
        $this->assertLessThan(300, strlen($reply), 'Out-of-scope response should be short');
        // Must NOT contain daily summary headers
        $this->assertStringNotContainsString('Daily Status', $reply);
        $this->assertStringNotContainsString('What I can answer', $reply);
    }

    /**
     * Different out-of-scope questions get different wording (not the exact same string).
     */
    public function test_out_of_scope_responses_are_varied(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $reply1 = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), ['message' => '1+1?'])
            ->assertOk()
            ->json('reply');

        $reply2 = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), ['message' => 'tell me a joke'])
            ->assertOk()
            ->json('reply');

        // Both should be short HIMS refusals
        $this->assertStringContainsString('HIMS', $reply1);
        $this->assertStringContainsString('HIMS', $reply2);

        // They should differ (not the same canned response)
        $this->assertNotEquals($reply1, $reply2, 'Different out-of-scope questions should get different responses');
    }

    public function test_unregistered_item_inquiry_returns_not_found_message_not_out_of_scope(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'may zonrox ba tayo?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringContainsString("I couldn't find a matching record for \"zonrox\"", $reply);
        $this->assertStringNotContainsString('That falls outside what I cover', $reply);
        $this->assertStringNotContainsString('I focus on HIMS hospital inventory operations', $reply);
        $this->assertStringNotContainsString("That's outside my scope", $reply);
    }

    public function test_registered_item_inquiry_returns_exact_availability_and_stock_dossier(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $item = InventoryItem::create([
            'name' => 'Zonrox Bleach Disinfectant 1L',
            'sku' => 'ZONR-BLCH-1L',
            'unit' => 'bottle',
            'quantity_on_hand' => 20,
            'reserved_quantity' => 2,
            'reorder_level' => 10,
            'safety_stock' => 5,
            'status' => 'active',
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'may zonrox ba tayo?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringContainsString('Yes', $reply);
        $this->assertStringContainsString('Zonrox Bleach Disinfectant 1L', $reply);
        $this->assertStringContainsString('ZONR-BLCH-1L', $reply);
        $this->assertStringContainsString('18 bottle', $reply);
    }

    public function test_ambiguous_item_query_returns_numbered_disambiguation_list(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        InventoryItem::create([
            'name' => 'Zonrox Bleach Original 1L',
            'sku' => 'ZONR-ORIG-1L',
            'unit' => 'bottle',
            'quantity_on_hand' => 25,
            'reorder_level' => 10,
            'status' => 'active',
        ]);

        InventoryItem::create([
            'name' => 'Zonrox ColorSafe Bleach 900ml',
            'sku' => 'ZONR-CLR-900ML',
            'unit' => 'bottle',
            'quantity_on_hand' => 12,
            'reorder_level' => 5,
            'status' => 'active',
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'may zonrox ba tayo?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringContainsString('I found 2 items matching "zonrox" in HIMS inventory', $reply);
        $this->assertStringContainsString('1. **Zonrox Bleach Original 1L**', $reply);
        $this->assertStringContainsString('2. **Zonrox ColorSafe Bleach 900ml**', $reply);
        $this->assertStringContainsString('Please specify the item name, SKU, or number', $reply);
    }

    public function test_item_attribute_query_for_storage_location(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $location = StorageLocation::create([
            'name' => 'Pharmacy Disinfectant Room',
            'code' => 'DISINF-RM-01',
            'type' => 'room',
            'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'name' => 'Isopropyl Alcohol 70% 500ml',
            'sku' => 'ALC-70-500',
            'unit' => 'bottle',
            'quantity_on_hand' => 50,
            'reorder_level' => 15,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => 50,
            'reserved_quantity' => 0,
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'nasaan yung alcohol?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringContainsString('Storage Location for **Isopropyl Alcohol 70% 500ml**', $reply);
        $this->assertStringContainsString('Pharmacy Disinfectant Room', $reply);
        $this->assertStringContainsString('50 bottle', $reply);
    }

    public function test_item_attribute_query_for_supplier(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $supplier = Supplier::create([
            'name' => 'MediClean Chemical Solutions Inc',
            'code' => 'SUP-MCSI-01',
            'contact_person' => 'Juan Dela Cruz',
            'email' => 'juan@mediclean.example.com',
            'phone' => '+63 2 8123 4567',
            'standard_lead_time_days' => 4,
            'status' => 'active',
        ]);

        InventoryItem::create([
            'name' => 'Zonrox Hospital Grade Bleach',
            'sku' => 'ZONR-HOSP-1L',
            'unit' => 'bottle',
            'quantity_on_hand' => 30,
            'reorder_level' => 10,
            'supplier_id' => $supplier->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'sino supplier ng zonrox?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringContainsString('Zonrox Hospital Grade Bleach', $reply);
        $this->assertStringContainsString('is supplied by **MediClean Chemical Solutions Inc**', $reply);
        $this->assertStringContainsString('Juan Dela Cruz', $reply);
    }

    public function test_item_attribute_query_for_unfound_item_reports_item_not_found(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'sino supplier ng zonrox?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringContainsString("I couldn't find a matching record for \"zonrox\"", $reply);
        $this->assertStringNotContainsString('That falls outside what I cover', $reply);
    }

    public function test_general_storage_locations_inquiry_lists_active_locations(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        StorageLocation::create([
            'name' => 'Central Pharmacy Storeroom',
            'code' => 'CPH-STR-01',
            'type' => 'storeroom',
            'status' => 'active',
        ]);

        StorageLocation::create([
            'name' => 'Emergency Room Stockroom',
            'code' => 'ER-STR-01',
            'type' => 'storeroom',
            'status' => 'active',
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'saan ang mga storage location?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringContainsString('HIMS Storage Locations', $reply);
        $this->assertStringContainsString('Central Pharmacy Storeroom', $reply);
        $this->assertStringContainsString('Emergency Room Stockroom', $reply);
    }

    public function test_strictly_out_of_scope_inquiries_are_politely_declined(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $outOfScopeQueries = [
            'what is the weather today?',
            'calculate 25 * 4',
            'write a story about a dragon',
            'capital of France',
        ];

        foreach ($outOfScopeQueries as $query) {
            $response = $this->actingAs($manager)
                ->postJson(route('dashboard.ai-assistant'), ['message' => $query])
                ->assertOk()
                ->assertJsonPath('status', 'success');

            $reply = $response->json('reply');
            $this->assertMatchesRegularExpression('/\b(?:HIMS|inventory|supply|scope|cover)\b/i', $reply);
        }
    }

    public function test_greeting_inquiry_returns_conversational_reply_without_capability_dump(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), ['message' => 'hi'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('source', 'conversational')
            ->assertJsonPath('status_hint', 'Replying...');

        $reply = $response->json('reply');
        $this->assertMatchesRegularExpression('/\b(?:HIMS|assistant|help)\b/i', $reply);
        // Must NOT contain the old 4-line repeated capability dump
        $this->assertStringNotContainsString('I can tell you which items are low or out of stock, what needs reordering', $reply);
    }

    public function test_follow_up_greeting_in_ongoing_conversation_does_not_repeat_full_intro(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        // 1st turn: User says "hi"
        $response1 = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), ['message' => 'hi'])
            ->assertOk();

        $conversationId = $response1->json('conversation_id');

        // 2nd turn: User says "good evening"
        $response2 = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'good evening',
                'conversation_id' => $conversationId,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('source', 'conversational')
            ->assertJsonPath('status_hint', 'Replying...');

        $reply2 = $response2->json('reply');
        $this->assertStringContainsString('Good evening!', $reply2);
        // Must be a concise reply, NOT repeating the full opening greeting
        $this->assertStringNotContainsString("I'm your HIMS inventory assistant. How can I help you", $reply2);
        $this->assertStringNotContainsString('I can tell you which items are low or out of stock', $reply2);
    }

    public function test_acknowledgment_returns_polite_conversational_reply(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), ['message' => 'salamat'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('source', 'conversational')
            ->assertJsonPath('status_hint', 'Replying...');

        $reply = $response->json('reply');
        $this->assertStringContainsString("You're welcome", $reply);
    }

    public function test_farewell_returns_polite_closing_reply(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), ['message' => 'bye'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('source', 'conversational');

        $reply = $response->json('reply');
        $this->assertStringContainsString('Goodbye', $reply);
    }

    public function test_how_are_you_returns_readiness_reply(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), ['message' => 'kamusta ka?'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('source', 'conversational');

        $reply = $response->json('reply');
        $this->assertStringContainsString('ready to assist with HIMS inventory', $reply);
    }

    public function test_combined_greeting_and_query_prepends_greeting_salutation_to_inventory_answer(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $item = InventoryItem::create([
            'name' => 'Paracetamol 500mg',
            'sku' => 'MED-PARA-500',
            'unit' => 'box',
            'quantity_on_hand' => 5,
            'reorder_level' => 20,
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Good evening, may low stock ba tayo?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringStartsWith('Good evening!', $reply);
        $this->assertStringContainsString('Paracetamol 500mg', $reply);
    }

    public function test_entity_tracker_supports_subjectless_quantity_followup(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $item = InventoryItem::create([
            'name' => 'Zonrox Bleach',
            'sku' => 'SUP-ZON-001',
            'quantity_on_hand' => 18,
            'reserved_quantity' => 0,
            'unit' => 'bottles',
        ]);

        // Turn 1: Ask if Zonrox exists
        $response1 = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'may zonrox ba tayo?',
            ])
            ->assertOk();

        $convId = $response1->json('conversation_id');
        $reply1 = $response1->json('reply');
        $this->assertStringContainsString('Zonrox Bleach', $reply1);

        // Turn 2: Follow up with "ilan?" (quantity follow-up)
        $response2 = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'ilan?',
                'conversation_id' => $convId,
            ])
            ->assertOk();

        $reply2 = $response2->json('reply');
        $this->assertStringContainsString('Zonrox Bleach', $reply2);
        $this->assertStringContainsString('18', $reply2);
    }

    public function test_goodnight_farewell_is_acknowledged_politely(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Goodnight',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('source', 'conversational');

        $reply = $response->json('reply');
        $this->assertStringContainsString('Goodnight', $reply);
        $this->assertStringNotContainsString('outside my scope', $reply);
        $this->assertStringNotContainsString('falls outside', $reply);
    }

    public function test_conversational_clarification_followup_what_is_handled_helpfully(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        // Turn 1
        $res1 = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Hello',
            ])
            ->assertOk();

        $convId = $res1->json('conversation_id');

        // Turn 2: "what?"
        $res2 = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'what?',
                'conversation_id' => $convId,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('source', 'conversational');

        $reply = $res2->json('reply');
        $this->assertStringContainsString('clarify', strtolower($reply));
        $this->assertStringNotContainsString('outside my scope', $reply);
        $this->assertStringNotContainsString('falls outside', $reply);
    }

    public function test_unknown_item_returns_not_found_message_never_out_of_scope(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'May Zonrox ba tayo?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringContainsString("I couldn't find a matching record for \"Zonrox\"", $reply);
        $this->assertStringNotContainsString('outside my scope', $reply);
    }

    public function test_unauthorized_user_asking_for_audit_trail_receives_explicit_authorization_message(): void
    {
        config()->set('services.gemini.key', '');
        $staff = User::factory()->create();
        Gate::define(Permission::ViewInventory->value, fn () => true);
        Gate::define(Permission::ViewReports->value, fn () => false);

        $response = $this->actingAs($staff)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Show me the system audit logs and trail',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringContainsString('permission', strtolower($reply));
        $this->assertStringNotContainsString('outside my scope', $reply);
    }

    public function test_expanded_capabilities_storage_locations_inquiry(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        StorageLocation::create([
            'name' => 'Main Pharmacy Cold Vault',
            'code' => 'COLD-01',
            'type' => 'refrigerator',
            'zone' => 'Pharmacy',
            'status' => 'active',
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Nasaan ang storage locations natin?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringContainsString('Main Pharmacy Cold Vault', $reply);
        $this->assertStringNotContainsString('outside my scope', $reply);
    }

    public function test_ikaw_bahala_context_a_low_stock_continuation(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $supplier = Supplier::create([
            'name' => 'MediSupply Pharma',
            'contact_person' => 'Juan Dela Cruz',
            'status' => 'active',
            'standard_lead_time_days' => 7,
        ]);
        $item = InventoryItem::create([
            'name' => 'Paracetamol 500mg',
            'sku' => 'PARA-500',
            'supplier_id' => $supplier->id,
            'quantity_on_hand' => 0,
            'reorder_level' => 50,
            'safety_stock' => 20,
            'unit' => 'boxes',
            'status' => 'active',
        ]);

        // Turn 1: Low stock query
        $turn1Response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'May low-stock items ba?',
            ])
            ->assertOk();
        $reply1 = $turn1Response->json('reply');
        $this->assertStringContainsString('Paracetamol 500mg', $reply1);

        // Turn 2: User says "Ikaw bahala."
        $history = [
            ['role' => 'user', 'content' => 'May low-stock items ba?'],
            ['role' => 'assistant', 'content' => $reply1],
        ];

        $turn2Response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Ikaw bahala.',
                'history' => $history,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply2 = $turn2Response->json('reply');
        // AI must NOT reject as out of scope
        $this->assertStringNotContainsString('outside my scope', strtolower($reply2));
        $this->assertStringNotContainsString('outside what i cover', strtolower($reply2));
        // AI should proactively guide on the urgent item
        $this->assertStringContainsString('Paracetamol 500mg', $reply2);
        $this->assertStringContainsString('MediSupply Pharma', $reply2);
    }

    public function test_ikaw_bahala_context_b_priority_continuation(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $supplier = Supplier::create([
            'name' => 'HealthCorp Logistics',
            'contact_person' => 'Maria Santos',
            'status' => 'active',
            'standard_lead_time_days' => 5,
        ]);
        InventoryItem::create([
            'name' => 'Amoxicillin 500mg',
            'sku' => 'AMOX-500',
            'supplier_id' => $supplier->id,
            'quantity_on_hand' => 5,
            'reorder_level' => 100,
            'unit' => 'bottles',
            'status' => 'active',
        ]);

        $history = [
            ['role' => 'user', 'content' => 'Which item should I check first?'],
            ['role' => 'assistant', 'content' => 'Based on current stock data, checking **Amoxicillin 500mg** is the first priority.'],
        ];

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'ikaw bahala',
                'history' => $history,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringNotContainsString('outside my scope', strtolower($reply));
        $this->assertStringContainsString('Amoxicillin 500mg', $reply);
    }

    public function test_ikaw_bahala_context_c_greeting_continuation(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $history = [
            ['role' => 'user', 'content' => 'Good evening'],
            ['role' => 'assistant', 'content' => 'Good evening! How can I help you with HIMS today?'],
        ];

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Ikaw bahala.',
                'history' => $history,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringNotContainsString('outside my scope', strtolower($reply));
        $this->assertStringContainsString('HIMS', $reply);
        $this->assertStringContainsString('check', strtolower($reply));
    }

    public function test_ikaw_bahala_context_d_item_dossier_continuation(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $item = InventoryItem::create([
            'name' => 'Zonrox Bleach 500mL',
            'sku' => 'ZNX-BLC-500',
            'quantity_on_hand' => 18,
            'unit' => 'bottles',
            'status' => 'active',
        ]);

        $history = [
            ['role' => 'user', 'content' => 'May Zonrox ba tayo?'],
            ['role' => 'assistant', 'content' => "**Yes**, we have **Zonrox Bleach 500mL** (SKU: ZNX-BLC-500) in stock. Available for dispensing: **18 bottles**."],
        ];

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'ikaw bahala',
                'history' => $history,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringNotContainsString('outside my scope', strtolower($reply));
        $this->assertStringContainsString('Zonrox Bleach 500mL', $reply);
    }

    public function test_ikaw_bahala_standalone_without_history(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'ikaw bahala',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reply = $response->json('reply');
        $this->assertStringNotContainsString('outside my scope', strtolower($reply));
        $this->assertStringContainsString('HIMS', $reply);
        $this->assertStringContainsString('stock', strtolower($reply));
    }

    public function test_multi_turn_subjectless_followup_and_okay_context_preservation(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $supplier = Supplier::create([
            'name' => 'Zonrox Chemical Supply',
            'contact_person' => 'Pedro Cruz',
            'status' => 'active',
            'standard_lead_time_days' => 3,
        ]);
        $item = InventoryItem::create([
            'name' => 'Zonrox Bleach 500mL',
            'sku' => 'ZNX-BLC-500',
            'supplier_id' => $supplier->id,
            'quantity_on_hand' => 18,
            'reorder_level' => 10,
            'unit' => 'bottles',
            'status' => 'active',
        ]);

        // Turn 1: Item lookup
        $turn1Response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'May Zonrox ba tayo?',
            ])
            ->assertOk();
        $reply1 = $turn1Response->json('reply');

        // Turn 2: User says "Okay."
        $historyTurn2 = [
            ['role' => 'user', 'content' => 'May Zonrox ba tayo?'],
            ['role' => 'assistant', 'content' => $reply1],
        ];
        $turn2Response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Okay.',
                'history' => $historyTurn2,
            ])
            ->assertOk();
        $reply2 = $turn2Response->json('reply');

        // Turn 3: User asks "Ilan?" (Context must NOT be reset by "Okay")
        $historyTurn3 = [
            ['role' => 'user', 'content' => 'May Zonrox ba tayo?'],
            ['role' => 'assistant', 'content' => $reply1],
            ['role' => 'user', 'content' => 'Okay.'],
            ['role' => 'assistant', 'content' => $reply2],
        ];
        $turn3Response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Ilan?',
                'history' => $historyTurn3,
            ])
            ->assertOk();
        $reply3 = $turn3Response->json('reply');
        $this->assertStringNotContainsString('outside my scope', strtolower($reply3));
        $this->assertStringContainsString('Zonrox Bleach 500mL', $reply3);
        $this->assertStringContainsString('18 bottles', $reply3);

        // Turn 4: User asks "Sino supplier?"
        $historyTurn4 = array_merge($historyTurn3, [
            ['role' => 'user', 'content' => 'Ilan?'],
            ['role' => 'assistant', 'content' => $reply3],
        ]);
        $turn4Response = $this->actingAs($manager)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Sino supplier?',
                'history' => $historyTurn4,
            ])
            ->assertOk();
        $reply4 = $turn4Response->json('reply');
        $this->assertStringNotContainsString('outside my scope', strtolower($reply4));
        $this->assertStringContainsString('Zonrox Chemical Supply', $reply4);
    }
}
