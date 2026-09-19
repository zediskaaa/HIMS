<?php

namespace Tests\Feature\Privacy;

use App\Models\User;
use App\Services\AiInventoryAssistantService;
use App\Services\Privacy\AiDataSanitizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiDataSanitizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanitizer_redacts_philhealth_pin(): void
    {
        $sanitizer = app(AiDataSanitizerService::class);
        $input = 'Patient member with PhilHealth PIN 12-345678901-2 requires antibiotics.';

        $result = $sanitizer->sanitize($input);

        $this->assertTrue($result['redacted']);
        $this->assertStringNotContainsString('12-345678901-2', $result['sanitized_text']);
        $this->assertStringContainsString('[REDACTED PHILHEALTH PIN]', $result['sanitized_text']);
    }

    public function test_sanitizer_redacts_tax_identification_number(): void
    {
        $sanitizer = app(AiDataSanitizerService::class);
        $input = 'Supplier billing details under TIN 123-456-789-000 must be verified.';

        $result = $sanitizer->sanitize($input);

        $this->assertTrue($result['redacted']);
        $this->assertStringNotContainsString('123-456-789-000', $result['sanitized_text']);
        $this->assertStringContainsString('[REDACTED TIN]', $result['sanitized_text']);
    }

    public function test_sanitizer_redacts_philippine_mobile_numbers(): void
    {
        $sanitizer = app(AiDataSanitizerService::class);
        $input = 'Call nurse supervisor on 09171234567 or +639189876543 immediately.';

        $result = $sanitizer->sanitize($input);

        $this->assertTrue($result['redacted']);
        $this->assertStringNotContainsString('09171234567', $result['sanitized_text']);
        $this->assertStringNotContainsString('+639189876543', $result['sanitized_text']);
        $this->assertStringContainsString('[REDACTED PH PHONE]', $result['sanitized_text']);
    }

    public function test_sanitizer_redacts_payment_cards_and_passwords(): void
    {
        $sanitizer = app(AiDataSanitizerService::class);
        $input = 'Payment card 4532-1234-5678-9012 with password: SecretPass123! entered.';

        $result = $sanitizer->sanitize($input);

        $this->assertTrue($result['redacted']);
        $this->assertStringNotContainsString('4532-1234-5678-9012', $result['sanitized_text']);
        $this->assertStringNotContainsString('SecretPass123!', $result['sanitized_text']);
    }

    public function test_ai_inventory_assistant_redacts_spi_before_processing(): void
    {
        config()->set('services.gemini.key', ''); // grounded fallback mode

        $user = User::factory()->inventoryManager()->create();
        $assistant = app(AiInventoryAssistantService::class);

        $response = $assistant->respond(
            actor: $user,
            userMessage: 'What is the stock for Paracetamol? Contact nurse at 09171234567 with PIN 12-345678901-2.'
        );

        $this->assertIsArray($response);
        $this->assertStringNotContainsString('09171234567', $response['reply']);
        $this->assertStringNotContainsString('12-345678901-2', $response['reply']);
    }
}
