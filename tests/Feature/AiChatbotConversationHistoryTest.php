<?php

namespace Tests\Feature;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\AiChatTitleGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiChatbotConversationHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.gemini.key', ''); // Test with grounded fallback
        Storage::fake('local');
    }

    public function test_chat_creates_new_conversation_and_persists_messages(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        InventoryItem::create([
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
                'message' => 'Which items are low in stock?',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('conversation_title', 'Low Stock Items');

        $conversationId = $response->json('conversation_id');
        $this->assertNotNull($conversationId);

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversationId,
            'user_id' => $manager->id,
            'title' => 'Low Stock Items',
        ]);

        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversationId,
            'role' => 'user',
            'content' => 'Which items are low in stock?',
        ]);

        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversationId,
            'role' => 'assistant',
        ]);
    }

    public function test_user_cannot_access_another_users_conversation(): void
    {
        $userA = User::factory()->inventoryManager()->create();
        $userB = User::factory()->inventoryManager()->create();

        $conversationA = AiChatConversation::create([
            'user_id' => $userA->id,
            'title' => 'Secret User A Chat',
        ]);

        AiChatMessage::create([
            'conversation_id' => $conversationA->id,
            'role' => 'user',
            'content' => 'Classified information for User A',
        ]);

        // User B attempts to view User A's conversation
        $this->actingAs($userB)
            ->getJson(route('dashboard.ai-assistant.conversation', ['id' => $conversationA->id]))
            ->assertNotFound();

        // User B attempts to append to User A's conversation
        $this->actingAs($userB)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Sneaky message into User A chat',
                'conversation_id' => $conversationA->id,
            ])
            ->assertNotFound();
    }

    public function test_conversation_list_returns_only_authenticated_user_conversations(): void
    {
        $userA = User::factory()->inventoryManager()->create();
        $userB = User::factory()->inventoryManager()->create();

        $convA = AiChatConversation::create([
            'user_id' => $userA->id,
            'title' => 'User A Inventory Analysis',
        ]);

        $convB = AiChatConversation::create([
            'user_id' => $userB->id,
            'title' => 'User B Forecast Review',
        ]);

        $responseA = $this->actingAs($userA)
            ->getJson(route('dashboard.ai-assistant.conversations'))
            ->assertOk();

        $conversationsA = $responseA->json('conversations');
        $this->assertCount(1, $conversationsA);
        $this->assertEquals($convA->id, $conversationsA[0]['id']);
        $this->assertEquals('User A Inventory Analysis', $conversationsA[0]['title']);

        $responseB = $this->actingAs($userB)
            ->getJson(route('dashboard.ai-assistant.conversations'))
            ->assertOk();

        $conversationsB = $responseB->json('conversations');
        $this->assertCount(1, $conversationsB);
        $this->assertEquals($convB->id, $conversationsB[0]['id']);
        $this->assertEquals('User B Forecast Review', $conversationsB[0]['title']);
    }

    public function test_active_conversation_returns_most_recent_conversation(): void
    {
        $user = User::factory()->inventoryManager()->create();

        // When user has no conversations yet
        $this->actingAs($user)
            ->getJson(route('dashboard.ai-assistant.active'))
            ->assertOk()
            ->assertJsonPath('conversation', null);

        // Create an older and a newer conversation
        $oldConv = AiChatConversation::create([
            'user_id' => $user->id,
            'title' => 'Older Conversation',
            'updated_at' => now()->subHours(2),
        ]);

        $newConv = AiChatConversation::create([
            'user_id' => $user->id,
            'title' => 'Newer Conversation',
            'updated_at' => now(),
        ]);

        AiChatMessage::create([
            'conversation_id' => $newConv->id,
            'role' => 'user',
            'content' => 'Latest question',
        ]);

        $this->actingAs($user)
            ->getJson(route('dashboard.ai-assistant.active'))
            ->assertOk()
            ->assertJsonPath('conversation.id', $newConv->id)
            ->assertJsonPath('conversation.title', 'Newer Conversation')
            ->assertJsonCount(1, 'conversation.messages');
    }

    public function test_title_is_generated_locally_from_first_message(): void
    {
        $this->assertEquals('Low Stock Items', AiChatTitleGenerator::generate('Which items are low in stock?'));
        $this->assertEquals('Demand Forecast', AiChatTitleGenerator::generate('Explain the demand forecast.'));
        $this->assertEquals('Inventory File Analysis', AiChatTitleGenerator::generate('Check this file', 'inventory.xlsx'));
        $this->assertEquals('Expiring Inventory', AiChatTitleGenerator::generate('What items are expiring soon?'));
        $this->assertEquals('Reorder Recommendations', AiChatTitleGenerator::generate('What should we reorder?'));
        $this->assertEquals('New Chat', AiChatTitleGenerator::generate(''));
    }

    public function test_multi_turn_conversation_context_is_persisted_and_used(): void
    {
        $user = User::factory()->inventoryManager()->create();

        $item = InventoryItem::create([
            'name' => 'Surgical Scissors',
            'sku' => 'SCIS-001',
            'unit' => 'pcs',
            'quantity_on_hand' => 2,
            'reorder_level' => 10,
            'safety_stock' => 5,
            'status' => 'active',
        ]);

        // Turn 1
        $turn1 = $this->actingAs($user)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Which items are low in stock?',
            ])
            ->assertOk();

        $convId = $turn1->json('conversation_id');

        // Turn 2 within the same conversation
        $turn2 = $this->actingAs($user)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'What is the sku of that item?',
                'conversation_id' => $convId,
            ])
            ->assertOk()
            ->assertJsonPath('conversation_id', $convId);

        // Verify that the conversation now contains 4 messages (2 user, 2 assistant)
        $this->assertDatabaseCount('ai_chat_messages', 4);

        $messages = AiChatMessage::where('conversation_id', $convId)->orderBy('id')->get();
        $this->assertEquals('Which items are low in stock?', $messages[0]->content);
        $this->assertEquals('user', $messages[0]->role);
        $this->assertEquals('assistant', $messages[1]->role);
        $this->assertEquals('What is the sku of that item?', $messages[2]->content);
        $this->assertEquals('user', $messages[2]->role);
        $this->assertEquals('assistant', $messages[3]->role);
    }

    public function test_attachment_is_saved_in_private_storage_and_accessible_by_owner_only(): void
    {
        $userA = User::factory()->inventoryManager()->create();
        $userB = User::factory()->inventoryManager()->create();

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $file = UploadedFile::fake()->createWithContent('gloves.png', $pngBytes);

        $response = $this->actingAs($userA)
            ->post(route('dashboard.ai-assistant'), [
                'message' => 'Inspect this gloves image',
                'attachment' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $convId = $response->json('conversation_id');
        $this->assertNotNull($convId);

        $message = AiChatMessage::where('conversation_id', $convId)
            ->where('role', 'user')
            ->first();

        $this->assertNotNull($message);
        $this->assertEquals('gloves.png', $message->attachment_name);
        $this->assertEquals('image', $message->attachment_type);
        $this->assertNotNull($message->attachment_path);

        // Owner can access the attachment
        $this->actingAs($userA)
            ->get(route('dashboard.ai-assistant.attachment', ['message' => $message->id]))
            ->assertOk();

        // Non-owner cannot access the attachment (IDOR protection)
        $this->actingAs($userB)
            ->get(route('dashboard.ai-assistant.attachment', ['message' => $message->id]))
            ->assertForbidden();
    }

    public function test_user_can_switch_between_conversations_and_load_respective_messages(): void
    {
        $user = User::factory()->inventoryManager()->create();

        $conv1 = AiChatConversation::create([
            'user_id' => $user->id,
            'title' => 'First Topic',
        ]);
        AiChatMessage::create([
            'conversation_id' => $conv1->id,
            'role' => 'user',
            'content' => 'First topic question',
        ]);
        AiChatMessage::create([
            'conversation_id' => $conv1->id,
            'role' => 'assistant',
            'content' => 'First topic answer',
        ]);

        $conv2 = AiChatConversation::create([
            'user_id' => $user->id,
            'title' => 'Second Topic',
        ]);
        AiChatMessage::create([
            'conversation_id' => $conv2->id,
            'role' => 'user',
            'content' => 'Second topic question',
        ]);
        AiChatMessage::create([
            'conversation_id' => $conv2->id,
            'role' => 'assistant',
            'content' => 'Second topic answer',
        ]);

        // Load Conv 1
        $resp1 = $this->actingAs($user)
            ->getJson(route('dashboard.ai-assistant.conversation', ['id' => $conv1->id]))
            ->assertOk();

        $this->assertEquals('First Topic', $resp1->json('conversation.title'));
        $this->assertCount(2, $resp1->json('conversation.messages'));
        $this->assertEquals('First topic question', $resp1->json('conversation.messages.0.content'));

        // Load Conv 2
        $resp2 = $this->actingAs($user)
            ->getJson(route('dashboard.ai-assistant.conversation', ['id' => $conv2->id]))
            ->assertOk();

        $this->assertEquals('Second Topic', $resp2->json('conversation.title'));
        $this->assertCount(2, $resp2->json('conversation.messages'));
        $this->assertEquals('Second topic question', $resp2->json('conversation.messages.0.content'));
    }

    public function test_starting_new_chat_creates_separate_conversation_and_preserves_old_one(): void
    {
        $user = User::factory()->inventoryManager()->create();

        // Chat 1: Send message without conversation_id -> creates new conversation
        $resp1 = $this->actingAs($user)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Which items are low in stock?',
            ])
            ->assertOk();

        $conv1Id = $resp1->json('conversation_id');

        // Chat 2: Send message without conversation_id (as happens when New Chat is confirmed)
        $resp2 = $this->actingAs($user)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Explain the demand forecast.',
            ])
            ->assertOk();

        $conv2Id = $resp2->json('conversation_id');

        $this->assertNotEquals($conv1Id, $conv2Id);

        // Both conversations exist in database and are owned by user
        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conv1Id,
            'user_id' => $user->id,
            'title' => 'Low Stock Items',
        ]);

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conv2Id,
            'user_id' => $user->id,
            'title' => 'Demand Forecast',
        ]);

        // User's conversation list has both
        $historyResp = $this->actingAs($user)
            ->getJson(route('dashboard.ai-assistant.conversations'))
            ->assertOk();

        $this->assertCount(2, $historyResp->json('conversations'));
    }
}
