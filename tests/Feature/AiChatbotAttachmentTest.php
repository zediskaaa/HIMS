<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class AiChatbotAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.gemini.key', 'test-gemini-key');
        config()->set('services.gemini.model', 'gemini-test-model');
        config()->set('services.gemini.base_url', 'https://generativelanguage.googleapis.com');
    }

    public function test_guest_cannot_upload_attachment_to_chat(): void
    {
        $file = UploadedFile::fake()->createWithContent('inventory.csv', "Item,Stock\nGloves,5\n");

        $this->post(route('dashboard.ai-assistant'), [
            'message' => 'Analyze this file',
            'attachment' => $file,
        ], ['Accept' => 'application/json'])->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_upload_attachment(): void
    {
        $user = User::factory()->create();
        Gate::define(Permission::ViewInventory->value, fn () => false);
        Gate::define(Permission::ViewReports->value, fn () => false);

        $file = UploadedFile::fake()->createWithContent('inventory.csv', "Item,Stock\nGloves,5\n");

        $this->actingAs($user)
            ->post(route('dashboard.ai-assistant'), [
                'message' => 'Analyze this file',
                'attachment' => $file,
            ], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_user_can_attach_csv_file_and_get_analysis(): void
    {
        config()->set('services.gemini.key', ''); // Test grounded fallback
        $manager = User::factory()->inventoryManager()->create();

        InventoryItem::create([
            'name' => 'Paracetamol 500mg',
            'sku' => 'MED-PARA-500',
            'unit' => 'tablet',
            'quantity_on_hand' => 12,
            'reorder_level' => 50,
            'safety_stock' => 20,
            'status' => 'active',
        ]);

        $csvContent = "Item Name,Current Stock,Reorder Level\nParacetamol 500mg,12,50\nAmoxicillin 500mg,100,30\n";
        $file = UploadedFile::fake()->createWithContent('stock_report.csv', $csvContent);

        $response = $this->actingAs($manager)
            ->post(route('dashboard.ai-assistant'), [
                'message' => 'Compare these items against inventory and suggest what to reorder',
                'attachment' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('attachment.name', 'stock_report.csv')
            ->assertJsonPath('attachment.type', 'spreadsheet')
            ->assertJsonPath('attachment.extension', 'csv');

        $reply = $response->json('reply');
        $this->assertStringContainsString('stock_report.csv', $reply);
        $this->assertStringContainsString('Paracetamol 500mg', $reply);
    }

    public function test_user_can_attach_xlsx_file_and_get_analysis(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $xlsxPath = tempnam(sys_get_temp_dir(), 'test_xlsx') . '.xlsx';
        $zip = new ZipArchive();
        $zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Item Name</t></si><si><t>Quantity</t></si><si><t>Latex Gloves</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row><row r="2"><c r="A2" t="s"><v>2</v></c><c r="B2"><v>15</v></c></row></sheetData></worksheet>');
        $zip->close();

        $file = new UploadedFile($xlsxPath, 'monthly_inventory.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($manager)
            ->post(route('dashboard.ai-assistant'), [
                'message' => 'Review stock levels in this Excel file',
                'attachment' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('attachment.name', 'monthly_inventory.xlsx')
            ->assertJsonPath('attachment.type', 'spreadsheet')
            ->assertJsonPath('attachment.extension', 'xlsx');

        $this->assertStringContainsString('monthly_inventory.xlsx', $response->json('reply'));
        @unlink($xlsxPath);
    }

    public function test_user_can_attach_docx_file_and_get_analysis(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $docxPath = tempnam(sys_get_temp_dir(), 'test_docx') . '.docx';
        $zip = new ZipArchive();
        $zip->open($docxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Quarterly Hospital Supply Utilization Summary.</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        $file = new UploadedFile($docxPath, 'procurement_report.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

        $response = $this->actingAs($manager)
            ->post(route('dashboard.ai-assistant'), [
                'message' => 'Please summarize this procurement document',
                'attachment' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('attachment.name', 'procurement_report.docx')
            ->assertJsonPath('attachment.type', 'document')
            ->assertJsonPath('attachment.extension', 'docx');

        $this->assertStringContainsString('procurement_report.docx', $response->json('reply'));
        @unlink($docxPath);
    }

    public function test_user_can_attach_txt_file_and_get_analysis(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $file = UploadedFile::fake()->createWithContent('daily_logs.txt', "Ward 3 requested 50 vials of Insulin R.\nEmergency Department depleted disposable syringes.");

        $response = $this->actingAs($manager)
            ->post(route('dashboard.ai-assistant'), [
                'message' => 'What did the wards report in this text log?',
                'attachment' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('attachment.type', 'text')
            ->assertJsonPath('attachment.name', 'daily_logs.txt');

        $this->assertStringContainsString('daily_logs.txt', $response->json('reply'));
    }

    public function test_user_can_attach_image_and_gemini_receives_multimodal_inline_data(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'The image shows surgical supplies stored in the main supply rack.'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $file = UploadedFile::fake()->createWithContent('stock_shelf.png', $pngBytes);

        $response = $this->actingAs($manager)
            ->post(route('dashboard.ai-assistant'), [
                'message' => 'What items can you see in this photo?',
                'attachment' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('source', 'ai')
            ->assertJsonPath('attachment.type', 'image')
            ->assertJsonPath('attachment.name', 'stock_shelf.png');

        $this->assertStringContainsString('The image shows surgical supplies', $response->json('reply'));

        Http::assertSent(function ($request) {
            $contents = $request['contents'] ?? [];
            $lastTurn = end($contents);
            $parts = $lastTurn['parts'] ?? [];

            $hasInlineData = false;
            foreach ($parts as $part) {
                if (isset($part['inlineData']['data']) && ($part['inlineData']['mimeType'] ?? '') === 'image/png') {
                    $hasInlineData = true;
                    break;
                }
            }

            return $hasInlineData;
        });
    }

    public function test_empty_message_with_attachment_succeeds_with_default_prompt(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $file = UploadedFile::fake()->createWithContent('items.csv', "Item,Quantity\nBandages,200\n");

        $response = $this->actingAs($manager)
            ->post(route('dashboard.ai-assistant'), [
                'message' => '',
                'attachment' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('attachment.name', 'items.csv');

        $this->assertNotEmpty($response->json('reply'));
    }

    public function test_empty_message_without_attachment_fails_validation(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        $this->actingAs($manager)
            ->post(route('dashboard.ai-assistant'), [
                'message' => '',
                'attachment' => null,
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_unsupported_file_extension_fails_validation(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        $file = UploadedFile::fake()->create('malicious.exe', 50, 'application/x-msdownload');

        $this->actingAs($manager)
            ->post(route('dashboard.ai-assistant'), [
                'message' => 'Check this file',
                'attachment' => $file,
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['attachment']);
    }

    public function test_oversized_attachment_fails_validation(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        // 36MB file (limit is 35MB / 35840 KB)
        $file = UploadedFile::fake()->create('oversized_huge.csv', 36864, 'text/csv');

        $this->actingAs($manager)
            ->post(route('dashboard.ai-assistant'), [
                'message' => 'Analyze this file',
                'attachment' => $file,
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['attachment']);
    }

    public function test_user_can_attach_large_dataset_and_get_analytics_summary(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        // Generate a 450-row CSV with stock and reorder columns
        $lines = ["Item Name,SKU,Current Stock,Reorder Level"];
        for ($i = 1; $i <= 450; $i++) {
            $stock = ($i % 10 === 0) ? 0 : ($i % 5 === 0 ? 5 : 100);
            $reorder = 20;
            $lines[] = "Medical Supply Item {$i},SKU-{$i},{$stock},{$reorder}";
        }
        $largeCsvContent = implode("\n", $lines);
        $file = UploadedFile::fake()->createWithContent('large_inventory_dataset.csv', $largeCsvContent);

        $response = $this->actingAs($manager)
            ->post(route('dashboard.ai-assistant'), [
                'message' => 'Analyze this large dataset and tell me what is out of stock',
                'attachment' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('attachment.name', 'large_inventory_dataset.csv')
            ->assertJsonPath('attachment.row_count', 450)
            ->assertJsonPath('attachment.is_truncated', true);

        $reply = $response->json('reply');
        $this->assertStringContainsString('large_inventory_dataset.csv', $reply);
    }

    public function test_large_text_file_exceeding_previous_limits_is_processed(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        // 70,000 characters text file (previous limit was 50,000, new limit is 150,000)
        $repeatedText = str_repeat("Hospital inventory consumption log entry for ward supplies.\n", 1200);
        $file = UploadedFile::fake()->createWithContent('large_audit_log.txt', $repeatedText);

        $response = $this->actingAs($manager)
            ->post(route('dashboard.ai-assistant'), [
                'message' => 'Summarize this long report',
                'attachment' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('attachment.is_truncated', false);

        $this->assertNotEmpty($response->json('reply'));
    }

    public function test_empty_zero_byte_file_is_rejected(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        $file = UploadedFile::fake()->createWithContent('empty.csv', '');

        $this->actingAs($manager)
            ->post(route('dashboard.ai-assistant'), [
                'message' => 'Analyze empty file',
                'attachment' => $file,
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['attachment']);
    }

    public function test_analyzed_attachment_records_audit_log(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $file = UploadedFile::fake()->createWithContent('clinic_supplies.csv', "Item,Count\nAlcohol 70%,45\n");

        $this->actingAs($manager)
            ->post(route('dashboard.ai-assistant'), [
                'message' => 'Review supplies',
                'attachment' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => AuditAction::AnalyzedAiChatAttachment->value,
        ]);

        $log = AuditLog::where('action', AuditAction::AnalyzedAiChatAttachment->value)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals('clinic_supplies.csv', $log->new_values['filename']);
        $this->assertEquals('spreadsheet', $log->new_values['filetype']);
        $this->assertEquals('csv', $log->new_values['extension']);
    }

    public function test_attaching_file_never_mutates_database_records(): void
    {
        config()->set('services.gemini.key', '');
        $manager = User::factory()->inventoryManager()->create();

        $itemCountBefore = InventoryItem::count();
        $movementCountBefore = StockMovement::count();

        $csvContent = "Item Name,Current Stock\nNonExistentItemXYZ,999\n";
        $file = UploadedFile::fake()->createWithContent('unauthorized_import.csv', $csvContent);

        $this->actingAs($manager)
            ->post(route('dashboard.ai-assistant'), [
                'message' => 'Please import and save these items to HIMS database',
                'attachment' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk();

        // Database records MUST remain completely unchanged
        $this->assertEquals($itemCountBefore, InventoryItem::count());
        $this->assertEquals($movementCountBefore, StockMovement::count());
        $this->assertDatabaseMissing('inventory_items', [
            'name' => 'NonExistentItemXYZ',
        ]);
    }
}
