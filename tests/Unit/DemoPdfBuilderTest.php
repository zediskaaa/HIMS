<?php

namespace Tests\Unit;

use App\Support\DemoPdfBuilder;
use PHPUnit\Framework\TestCase;

class DemoPdfBuilderTest extends TestCase
{
    public function test_general_document_wraps_long_table_cells_with_configured_column_widths(): void
    {
        $longItemName = 'Verorab Inactivated Rabies Vaccine 0.5mL + Diluent [VAC-RAB-VER05]';

        $pdf = DemoPdfBuilder::create(
            title: 'DELIVERY RECEIPT',
            sections: [[
                'heading' => 'DELIVERED INVENTORY',
                'table' => [
                    'headers' => ['Item / Product Name & SKU', 'Batch / Lot No.', 'Expiry Date', 'Quantity', 'Unit Cost (PHP)'],
                    'widths' => [2.8, 1.25, 1.1, 0.9, 1.1],
                    'rows' => [[$longItemName, 'VER-2026-881', '2028-09-24', '500 vials', '1,450.00']],
                ],
            ]],
        );

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('(VER-2026-881) Tj', $pdf);
        $this->assertStringNotContainsString('('.$longItemName.') Tj', $pdf);
    }

    public function test_generated_pdf_contains_renderable_page_content(): void
    {
        $pdf = DemoPdfBuilder::create('Logistics record', [
            ['heading' => 'DETAILS', 'lines' => ['Reference: TEST-001']],
        ]);

        $this->assertMatchesRegularExpression('/\/Type\s*\/Page\b/', $pdf);
        $this->assertStringContainsString('/Contents', $pdf);
    }
}
