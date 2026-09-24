<?php

namespace App\Support;

use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteLine;
use Illuminate\Support\Str;

class DemoPdfBuilder
{
    // Standard A4 Dimensions in PDF Points (72 pts/inch: 210mm x 297mm)
    public const PAGE_WIDTH = 595.28;
    public const PAGE_HEIGHT = 841.89;
    public const MARGIN_LEFT = 45.0;
    public const CONTENT_WIDTH = 505.28;
    public const BOTTOM_MARGIN = 50.0;
    public const TOP_START = 795.0;

    /**
     * Standard Helvetica character widths per 1,000 units.
     */
    private static array $charWidths = [
        ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667, '\'' => 191,
        '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278,
        '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556,
        '8' => 556, '9' => 556, ':' => 278, ';' => 278, '<' => 584, '=' => 584, '>' => 584, '?' => 556,
        '@' => 1015, 'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778,
        'H' => 722, 'I' => 278, 'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778,
        'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944,
        'X' => 667, 'Y' => 667, 'Z' => 611, '[' => 278, '\\' => 278, ']' => 278, '^' => 469, '_' => 556,
        '`' => 222, 'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556,
        'h' => 556, 'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556, 'o' => 556,
        'p' => 556, 'q' => 556, 'r' => 333, 's' => 500, 't' => 278, 'u' => 556, 'v' => 500, 'w' => 722,
        'x' => 500, 'y' => 500, 'z' => 500, '{' => 334, '|' => 260, '}' => 334, '~' => 584,
    ];

    /**
     * Build a formatted PDF document binary with institutional header, detail cards, styled tables, and footer.
     *
     * @param  array<int, array{heading?: string, lines?: array<string>, table?: array{headers: array<string>, widths?: array<float|int>, rows: array<array<string>>}}>  $sections
     */
    public static function create(string $title, array $sections, ?string $subtitle = null): string
    {
        return (new self())->renderGeneralDocument($title, $subtitle, $sections);
    }

    /**
     * Build a delivery receipt using only data recorded on the receiving record.
     */
    public static function createDeliveryReceipt(GoodsReceiptNote $receipt): string
    {
        $receipt->loadMissing(['supplier', 'purchaseOrder', 'receivedBy', 'lines.item', 'lines.destinationLocation']);

        $destinations = $receipt->lines
            ->pluck('destinationLocation.name')
            ->filter()
            ->unique()
            ->values()
            ->join(', ');
        $particulars = collect([
            'Supplier: '.($receipt->supplier?->name ?? 'Not recorded'),
            'Purchase Order Ref: '.($receipt->purchaseOrder?->po_number ?? 'Not recorded'),
            'Delivery Receipt No: '.($receipt->dr_number ?: $receipt->packing_slip_number ?: 'Not recorded'),
            'Delivery Date: '.$receipt->received_at?->format('Y-m-d H:i'),
            $destinations !== '' ? 'Receiving Destination: '.$destinations : null,
            $receipt->carrier_name ? 'Carrier: '.$receipt->carrier_name : null,
            $receipt->waybill_number ? 'Waybill: '.$receipt->waybill_number : null,
        ])->filter()->values()->all();
        $rows = $receipt->lines->map(function (GoodsReceiptNoteLine $line): array {
            $unit = $line->purchase_unit ?: ($line->item?->unit ?: 'unit');
            $itemLabel = $line->item?->name ?? 'Item record unavailable';
            if ($line->item?->sku) {
                $itemLabel .= ' ['.$line->item->sku.']';
            }

            return [
                $itemLabel,
                $line->batch_number ?: ($line->lot_number ?: 'Not recorded'),
                $line->expiry_date?->format('Y-m-d') ?? '',
                $line->received_quantity.' '.$unit,
                number_format((float) $line->unit_cost, 2),
            ];
        })->all();
        $inspectionLines = collect([
            'Receiving Officer: '.($receipt->receivedBy?->name ?? 'Not recorded'),
            'Receiving Status: '.Str::headline($receipt->receipt_status),
            $receipt->delivery_status ? 'Delivery Status: '.Str::headline($receipt->delivery_status) : null,
            $receipt->notes ? 'Receiving Notes: '.$receipt->notes : null,
        ])->filter()->values()->all();

        return self::create(
            title: strtoupper($receipt->supplier?->name ?? 'Supplier').' - DELIVERY RECEIPT',
            sections: [
                ['heading' => 'DELIVERY & CONSIGNMENT PARTICULARS', 'lines' => $particulars],
                [
                    'heading' => 'DELIVERED INVENTORY & BATCH SPECIFICATIONS',
                    'table' => [
                        'headers' => ['Item / Product Name & SKU', 'Batch / Lot No.', 'Expiry Date', 'Quantity', 'Unit Cost (PHP)'],
                        'widths' => [2.8, 1.25, 1.1, 0.9, 1.1],
                        'rows' => $rows,
                    ],
                ],
                ['heading' => 'RECEIVING & INSPECTION STATUS', 'lines' => $inspectionLines],
            ],
            subtitle: 'HIMS Goods Receipt '.$receipt->grn_number.' | DR No: '.($receipt->dr_number ?: $receipt->packing_slip_number ?: 'Not recorded'),
        );
    }

    /**
     * Build a sales invoice summary using only values linked to the receiving record.
     */
    public static function createSalesInvoice(GoodsReceiptNote $receipt): string
    {
        $receipt->loadMissing(['supplier', 'purchaseOrder', 'receivedBy', 'lines.item']);

        $invoiceDetails = collect([
            'Supplier: '.($receipt->supplier?->name ?? 'Not recorded'),
            $receipt->supplier?->tax_number ? 'Supplier TIN: '.$receipt->supplier->tax_number : null,
            'Sales Invoice No: '.($receipt->sales_invoice_number ?: 'Not recorded'),
            'Purchase Order Ref: '.($receipt->purchaseOrder?->po_number ?? 'Not recorded'),
            'Linked Goods Receipt: '.$receipt->grn_number,
            $receipt->received_at ? 'Received Date: '.$receipt->received_at->format('Y-m-d H:i') : null,
            $receipt->purchaseOrder?->payment_terms ? 'Payment Terms: '.$receipt->purchaseOrder->payment_terms : null,
        ])->filter()->values()->all();

        $total = 0.0;
        $rows = $receipt->lines->map(function (GoodsReceiptNoteLine $line) use (&$total): array {
            $unit = $line->purchase_unit ?: ($line->item?->unit ?: 'unit');
            $quantity = (int) $line->received_quantity;
            $unitCost = (float) $line->unit_cost;
            $lineTotal = $quantity * $unitCost;
            $total += $lineTotal;

            return [
                $line->item?->name ?? 'Item record unavailable',
                $line->item?->sku ?? '',
                $quantity.' '.$unit,
                number_format($unitCost, 2),
                number_format($lineTotal, 2),
            ];
        })->all();

        $summary = collect([
            'Total Invoice Value: PHP '.number_format($total, 2),
            'Receiving Status: '.Str::headline($receipt->receipt_status),
            $receipt->receivedBy?->name ? 'Receiving Officer: '.$receipt->receivedBy->name : null,
        ])->filter()->values()->all();

        return (new self())->renderGeneralDocument(
            title: strtoupper($receipt->supplier?->name ?? 'Supplier').' - SALES INVOICE',
            subtitle: 'SI No: '.($receipt->sales_invoice_number ?: 'Not recorded').' | PO: '.($receipt->purchaseOrder?->po_number ?? 'Not recorded'),
            sections: [
                ['heading' => 'INVOICE & RECEIVING REFERENCES', 'lines' => $invoiceDetails],
                [
                    'heading' => 'INVOICED ITEMS',
                    'table' => [
                        'headers' => ['Item / Product Name', 'SKU', 'Quantity', 'Unit Price (PHP)', 'Line Total (PHP)'],
                        'widths' => [2.7, 1.1, 1.0, 1.15, 1.2],
                        'rows' => $rows,
                    ],
                ],
                ['heading' => 'INVOICE SUMMARY', 'lines' => $summary],
            ],
        );
    }

    /**
     * Check if document represents an Electronic Sales Invoice.
     */
    private static function isElectronicSalesInvoice(string $title, ?string $subtitle, array $sections): bool
    {
        $text = strtoupper($title . ' ' . ($subtitle ?? ''));
        if (str_contains($text, 'SALES INVOICE') || str_contains($text, 'ELECTRONIC INVOICE')) {
            return true;
        }

        foreach ($sections as $s) {
            $h = strtoupper($s['heading'] ?? '');
            if (str_contains($h, 'INVOICE') || str_contains($h, 'INVOICED LINE ITEMS')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render an official, institutional Electronic Sales Invoice PDF conforming to BIR RA 11976 (EOPT).
     */
    private function renderElectronicSalesInvoice(string $title, ?string $subtitle, array $sections): string
    {
        $parsed = $this->parseInvoiceData($title, $subtitle, $sections);

        $pages = [];
        $currentStream = "0 0 0 RG\n0 0 0 rg\n";
        $y = self::TOP_START;

        // 1. Render First Page Header Banner
        $currentStream .= $this->drawInvoiceHeader($parsed, $y);
        $y -= 75.0;

        // 2. Render Taxpayer & Transaction Particulars Table (Document-style 2-column table with zero overlap)
        [$metaStream, $metaHeight] = $this->drawTaxpayerDocumentParticulars($parsed, $y);
        $currentStream .= $metaStream;
        $y -= ($metaHeight + 14.0);

        // 3. Render Section Heading for Line Items
        $currentStream .= "0.12 0.35 0.65 rg\n" . self::MARGIN_LEFT . " " . ($y - 2) . " 3.5 11 re f\n";
        $currentStream .= "BT\n/F1 8.5 Tf\n0.08 0.18 0.32 rg\n" . (self::MARGIN_LEFT + 8.0) . " {$y} Td\n(" .
            self::escape("INVOICED LINE ITEMS & VALUES") . ") Tj\nET\n";
        $y -= 14.0;

        // Column layout for Line Items Table (Sum = 505.28 pt)
        $colLayout = [
            ['name' => 'Item Description & Specifications', 'w' => 155.28, 'align' => 'left'],
            ['name' => 'Item Code / SKU', 'w' => 55.0, 'align' => 'left'],
            ['name' => 'Qty', 'w' => 30.0, 'align' => 'right'],
            ['name' => 'Unit', 'w' => 30.0, 'align' => 'center'],
            ['name' => 'Price (PHP)', 'w' => 65.0, 'align' => 'right'],
            ['name' => 'Tax Status', 'w' => 50.0, 'align' => 'center'],
            ['name' => 'Discount', 'w' => 45.0, 'align' => 'right'],
            ['name' => 'Total (PHP)', 'w' => 75.0, 'align' => 'right'],
        ];

        // Header rendering closure
        $drawTableHeader = function (float $atY) use ($colLayout) {
            $hStream = "";
            $headerH = 18.0;
            $hStream .= "0.12 0.23 0.38 rg\n" . self::MARGIN_LEFT . " " . ($atY - $headerH) . " " . self::CONTENT_WIDTH . " {$headerH} re f\n";
            $hStream .= "0.10 0.18 0.30 RG\n0.75 w\n" . self::MARGIN_LEFT . " " . ($atY - $headerH) . " " . self::CONTENT_WIDTH . " {$headerH} re S\n";

            $curX = self::MARGIN_LEFT;
            foreach ($colLayout as $col) {
                $w = $col['w'];
                $txt = $col['name'];
                $align = $col['align'];
                $txtW = self::getTextWidth($txt, 6.8, true);

                if ($align === 'right') {
                    $xPos = $curX + $w - 6.0 - $txtW;
                } elseif ($align === 'center') {
                    $xPos = $curX + ($w - $txtW) / 2.0;
                } else {
                    $xPos = $curX + 6.0;
                }

                $hStream .= "BT\n/F1 6.8 Tf\n1 1 1 rg\n{$xPos} " . ($atY - 12.5) . " Td\n(" .
                    self::escape($txt) . ") Tj\nET\n";
                $curX += $w;
            }

            return $hStream;
        };

        // Draw initial table header
        $currentStream .= $drawTableHeader($y);
        $y -= 18.0;

        // Estimated space needed for Financial Summary, Logistics Panel, BIR, Signatures, and Footer on final page
        $finalBlocksHeight = 295.0;

        $items = $parsed['items'];
        $itemCount = count($items);

        foreach ($items as $rIdx => $item) {
            // Dynamic multi-line description wrap
            $descLines = $this->wrapText($item['description'], $colLayout[0]['w'] - 12.0, 7.2, false);
            $maxLines = max(count($descLines), 1);
            $rowHeight = max(($maxLines * 10.5) + 8.0, 20.0);

            // Check if row plus subsequent final blocks fit
            $isLastItem = ($rIdx === ($itemCount - 1));
            $neededSpace = $isLastItem ? ($rowHeight + $finalBlocksHeight) : ($rowHeight + 25.0);

            if (($y - $neededSpace) < self::BOTTOM_MARGIN) {
                // Save current page
                $pages[] = $currentStream;

                // Start fresh continuation page
                $currentStream = "0 0 0 RG\n0 0 0 rg\n";
                $y = self::TOP_START;

                // Compact continuation header on Page 2+
                $currentStream .= "0.08 0.22 0.45 rg\n" . self::MARGIN_LEFT . " 808 " . self::CONTENT_WIDTH . " 3.5 re f\n";
                $currentStream .= "BT\n/F1 8 Tf\n0.10 0.18 0.32 rg\n" . self::MARGIN_LEFT . " 794 Td\n(" .
                    self::escape($parsed['seller']['name'] . " | ELECTRONIC SALES INVOICE (CONTINUATION) - " . $parsed['invoice_no']) .
                    ") Tj\nET\n";
                $currentStream .= "BT\n/F2 7.5 Tf\n0.40 0.45 0.52 rg\n" . (self::MARGIN_LEFT + 360) . " 794 Td\n(" .
                    self::escape("Continued on Page " . (count($pages) + 1)) . ") Tj\nET\n";
                $currentStream .= "0.84 0.88 0.93 RG\n0.5 w\n" . self::MARGIN_LEFT . " 788 " . self::CONTENT_WIDTH . " 0 re S\n";
                $y = 770.0;

                // Repeat table header
                $currentStream .= $drawTableHeader($y);
                $y -= 18.0;
            }

            // Alternating row background
            $rowBg = ($rIdx % 2 === 0) ? '1 1 1 rg' : '0.985 0.990 0.995 rg';
            $currentStream .= "{$rowBg}\n" . self::MARGIN_LEFT . " " . ($y - $rowHeight) . " " . self::CONTENT_WIDTH . " {$rowHeight} re f\n";
            $currentStream .= "0.88 0.90 0.93 RG\n0.4 w\n" . self::MARGIN_LEFT . " " . ($y - $rowHeight) . " " . self::CONTENT_WIDTH . " {$rowHeight} re S\n";

            // Render columns
            $curX = self::MARGIN_LEFT;

            // Col 1: Description
            $textY = $y - 12.0;
            foreach ($descLines as $line) {
                $currentStream .= "BT\n/F1 7.2 Tf\n0.10 0.14 0.22 rg\n" . ($curX + 6.0) . " {$textY} Td\n(" .
                    self::escape($line) . ") Tj\nET\n";
                $textY -= 10.5;
            }
            $curX += $colLayout[0]['w'];

            // Col 2: Item Code / SKU
            $skuTxt = (string) ($item['sku'] ?? 'N/A');
            $currentStream .= "BT\n/F2 7.0 Tf\n0.30 0.35 0.42 rg\n" . ($curX + 6.0) . " " . ($y - 13.0) . " Td\n(" .
                self::escape($skuTxt) . ") Tj\nET\n";
            $curX += $colLayout[1]['w'];

            // Col 3: Quantity
            $qTxt = (string) $item['quantity'];
            $qW = self::getTextWidth($qTxt, 7.5, true);
            $currentStream .= "BT\n/F1 7.5 Tf\n0.12 0.16 0.24 rg\n" . ($curX + $colLayout[2]['w'] - 6.0 - $qW) . " " . ($y - 13.0) . " Td\n(" .
                self::escape($qTxt) . ") Tj\nET\n";
            $curX += $colLayout[2]['w'];

            // Col 4: Unit
            $uTxt = (string) $item['unit'];
            $uW = self::getTextWidth($uTxt, 7.0, false);
            $currentStream .= "BT\n/F2 7.0 Tf\n0.30 0.35 0.42 rg\n" . ($curX + ($colLayout[3]['w'] - $uW) / 2.0) . " " . ($y - 13.0) . " Td\n(" .
                self::escape($uTxt) . ") Tj\nET\n";
            $curX += $colLayout[3]['w'];

            // Col 5: Unit Price
            $pTxt = (string) $item['unit_price'];
            $pW = self::getTextWidth($pTxt, 7.2, false);
            $currentStream .= "BT\n/F2 7.2 Tf\n0.15 0.18 0.22 rg\n" . ($curX + $colLayout[4]['w'] - 6.0 - $pW) . " " . ($y - 13.0) . " Td\n(" .
                self::escape($pTxt) . ") Tj\nET\n";
            $curX += $colLayout[4]['w'];

            // Col 6: Tax Status
            $tTxt = (string) $item['tax_status'];
            $tW = self::getTextWidth($tTxt, 6.5, false);
            $currentStream .= "BT\n/F2 6.5 Tf\n0.35 0.40 0.48 rg\n" . ($curX + ($colLayout[5]['w'] - $tW) / 2.0) . " " . ($y - 13.0) . " Td\n(" .
                self::escape($tTxt) . ") Tj\nET\n";
            $curX += $colLayout[5]['w'];

            // Col 7: Discount
            $dTxt = (string) ($item['discount'] ?? '0.00');
            $dW = self::getTextWidth($dTxt, 7.0, false);
            $currentStream .= "BT\n/F2 7.0 Tf\n0.35 0.40 0.48 rg\n" . ($curX + $colLayout[6]['w'] - 6.0 - $dW) . " " . ($y - 13.0) . " Td\n(" .
                self::escape($dTxt) . ") Tj\nET\n";
            $curX += $colLayout[6]['w'];

            // Col 8: Total Amount (Bold prominent navy)
            $totTxt = (string) $item['total'];
            $totW = self::getTextWidth($totTxt, 7.5, true);
            $currentStream .= "BT\n/F1 7.5 Tf\n0.08 0.22 0.55 rg\n" . ($curX + $colLayout[7]['w'] - 6.0 - $totW) . " " . ($y - 13.0) . " Td\n(" .
                self::escape($totTxt) . ") Tj\nET\n";

            $y -= $rowHeight;
        }

        // Determine spacing budget for single-page vs multi-page
        $netRemainingSpace = $y - $finalBlocksHeight - self::BOTTOM_MARGIN - 10.0;
        $extraGap = 0.0;
        if ($netRemainingSpace > 15.0) {
            $extraGap = min($netRemainingSpace / 4.0, 14.0);
        }

        $y -= (12.0 + $extraGap);

        // 4. Render Financial Summary, Payment Terms, & BIR Certification
        $currentStream .= $this->drawFinancialSummarySection($parsed, $y);
        $y -= (102.0 + $extraGap);

        // 5. Render Logistics Dispatch & Receiving Audit Trail
        $currentStream .= $this->drawLogisticsDeliveryPanel($parsed, $y);
        $y -= (42.0 + $extraGap);

        // 6. Render Official Receiving & Audit Sign-Off Area
        $currentStream .= $this->drawOfficialSignOffBlocks($parsed, $y);

        $pages[] = $currentStream;

        return $this->compilePdfDocument($pages);
    }

    /**
     * Draw the primary institutional header banner on the Electronic Sales Invoice.
     */
    private function drawInvoiceHeader(array $parsed, float $y): string
    {
        $stream = "";

        // Top Institutional Navy Header Bar
        $stream .= "0.08 0.22 0.45 rg\n" . self::MARGIN_LEFT . " 808 " . self::CONTENT_WIDTH . " 3.5 re f\n";

        // Left Side: Vendor / Supplier Identity
        $stream .= "BT\n/F1 13.0 Tf\n0.06 0.16 0.32 rg\n" . self::MARGIN_LEFT . " 786 Td\n(" .
            self::escape($parsed['seller']['name']) . ") Tj\nET\n";

        $stream .= "BT\n/F2 8.0 Tf\n0.35 0.40 0.48 rg\n" . self::MARGIN_LEFT . " 773 Td\n(" .
            self::escape("Authorized Healthcare Logistics & Medical Supply Distribution Partner") . ") Tj\nET\n";

        $stream .= "BT\n/F2 7.5 Tf\n0.25 0.30 0.38 rg\n" . self::MARGIN_LEFT . " 761 Td\n(" .
            self::escape("BIR Registered Taxpayer & Institutional Healthcare Logistics Vendor") . ") Tj\nET\n";

        // Right Side: Document Identification Box
        $boxW = 215.0;
        $boxH = 48.0;
        $boxX = self::MARGIN_LEFT + self::CONTENT_WIDTH - $boxW;
        $boxY = 748.0;

        $stream .= "0.965 0.975 0.990 rg\n{$boxX} {$boxY} {$boxW} {$boxH} re f\n";
        $stream .= "0.78 0.84 0.92 RG\n0.75 w\n{$boxX} {$boxY} {$boxW} {$boxH} re S\n";

        // Sub-header band inside box
        $stream .= "0.92 0.95 0.985 rg\n{$boxX} " . ($boxY + $boxH - 16.0) . " {$boxW} 16 re f\n";
        $stream .= "0.78 0.84 0.92 RG\n0.5 w\n{$boxX} " . ($boxY + $boxH - 16.0) . " {$boxW} 0 re S\n";

        // Invoice Title
        $stream .= "BT\n/F1 9.5 Tf\n0.06 0.18 0.38 rg\n" . ($boxX + 8.0) . " " . ($boxY + $boxH - 11.5) . " Td\n(" .
            self::escape("ELECTRONIC SALES INVOICE") . ") Tj\nET\n";

        // Invoice Number
        $stream .= "BT\n/F1 7.5 Tf\n0.35 0.40 0.48 rg\n" . ($boxX + 8.0) . " " . ($boxY + 20.0) . " Td\n(" .
            self::escape("SI NO:  ") . ") Tj\nET\n";
        $stream .= "BT\n/F1 8.5 Tf\n0.08 0.22 0.55 rg\n" . ($boxX + 48.0) . " " . ($boxY + 20.0) . " Td\n(" .
            self::escape($parsed['invoice_no']) . ") Tj\nET\n";

        // Invoice Date
        $stream .= "BT\n/F1 7.0 Tf\n0.35 0.40 0.48 rg\n" . ($boxX + 8.0) . " " . ($boxY + 9.0) . " Td\n(" .
            self::escape("DATE:  ") . ") Tj\nET\n";
        $stream .= "BT\n/F2 7.5 Tf\n0.12 0.16 0.24 rg\n" . ($boxX + 48.0) . " " . ($boxY + 9.0) . " Td\n(" .
            self::escape($parsed['invoice_date']) . ") Tj\nET\n";

        // Statutory Classification
        $stream .= "BT\n/F2 6.2 Tf\n0.40 0.45 0.52 rg\n" . ($boxX + 8.0) . " " . ($boxY - 1.0) . " Td\n(" .
            self::escape("BIR Electronic Sales Invoice (RA 11976 / EOPT Compliant)") . ") Tj\nET\n";

        // Horizontal dividing rule
        $stream .= "0.82 0.86 0.92 RG\n0.75 w\n" . self::MARGIN_LEFT . " 738 " . self::CONTENT_WIDTH . " 0 re S\n";

        return $stream;
    }

    /**
     * Draw the side-by-side Seller and Customer taxpayer metadata in a document-style particulars table.
     * Guaranteed zero overlap through precise character metrics, dedicated label widths, and natural word wrapping.
     *
     * @return array{0: string, 1: float} Stream content and total height consumed.
     */
    private function drawTaxpayerDocumentParticulars(array $parsed, float $y): array
    {
        $stream = "";
        $colW = self::CONTENT_WIDTH / 2.0; // 252.64 pt
        $leftX = self::MARGIN_LEFT;
        $rightX = self::MARGIN_LEFT + $colW;

        // Structured row pairs: [Seller Field, Customer Field]
        $rowPairs = [
            [
                's_lbl' => 'Entity:',
                's_val' => $parsed['seller']['name'],
                's_bold' => true,
                'c_lbl' => 'Customer:',
                'c_val' => $parsed['customer']['name'],
                'c_bold' => true,
            ],
            [
                's_lbl' => 'TIN / Reg:',
                's_val' => $parsed['seller']['tin'] ?: '000-123-456-000 (VAT Registered)',
                's_bold' => false,
                'c_lbl' => 'Fund Cluster:',
                'c_val' => $parsed['customer']['fund_cluster'] ?: '01 Regular Agency Fund',
                'c_bold' => false,
            ],
            [
                's_lbl' => 'Address:',
                's_val' => $parsed['seller']['address'] ?: 'KM 14 West Service Rd, South Superhighway, Paranaque City',
                's_bold' => false,
                'c_lbl' => 'Billing Addr:',
                'c_val' => $parsed['customer']['billing_address'] ?: 'Central Medical Logistics & Supply Division, Manila, Philippines',
                'c_bold' => false,
            ],
            [
                's_lbl' => 'Tax Status:',
                's_val' => $parsed['seller']['tax_type'] ?? 'VAT-Registered Commercial Enterprise',
                's_bold' => false,
                'c_lbl' => 'Delivery To:',
                'c_val' => $parsed['customer']['delivery_address'] ?? 'Hospital Central Receiving Dock Bay 1, Manila',
                'c_bold' => false,
            ],
            [
                's_lbl' => 'Bus. Style:',
                's_val' => $parsed['seller']['business_style'] ?? 'Pharmaceuticals & Healthcare Supply Wholesaler',
                's_bold' => false,
                'c_lbl' => 'PO / Terms:',
                'c_val' => 'Ref: ' . $parsed['po_reference'] . ' | ' . $parsed['payment_terms'],
                'c_bold' => true,
            ],
        ];

        // Dedicated label width and maximum available width for wrapped values
        $labelWidth = 58.0;
        $maxValWidth = $colW - $labelWidth - 16.0; // ~178.64 pt

        // Precompute line wrapping and row heights
        $computedRows = [];
        $totalDataHeight = 0.0;

        foreach ($rowPairs as $rp) {
            $sWrapped = $this->wrapText($rp['s_val'], $maxValWidth, 7.0, $rp['s_bold']);
            $cWrapped = $this->wrapText($rp['c_val'], $maxValWidth, 7.0, $rp['c_bold']);
            $lineCount = max(count($sWrapped), count($cWrapped), 1);
            $rowH = ($lineCount * 9.5) + 4.5;

            $computedRows[] = [
                's_lbl' => $rp['s_lbl'],
                's_lines' => $sWrapped,
                's_bold' => $rp['s_bold'],
                'c_lbl' => $rp['c_lbl'],
                'c_lines' => $cWrapped,
                'c_bold' => $rp['c_bold'],
                'height' => $rowH,
            ];
            $totalDataHeight += $rowH;
        }

        $headerH = 17.0;
        $totalBoxHeight = $headerH + $totalDataHeight;
        $boxBottomY = $y - $totalBoxHeight;

        // Outer Box & Background
        $stream .= "0.985 0.990 0.995 rg\n{$leftX} {$boxBottomY} " . self::CONTENT_WIDTH . " {$totalBoxHeight} re f\n";
        $stream .= "0.80 0.85 0.92 RG\n0.75 w\n{$leftX} {$boxBottomY} " . self::CONTENT_WIDTH . " {$totalBoxHeight} re S\n";

        // Vertical Center Divider Line
        $stream .= "0.82 0.86 0.92 RG\n0.5 w\n{$rightX} {$boxBottomY} 0 {$totalBoxHeight} re S\n";

        // Header Band
        $headerY = $y - $headerH;
        $stream .= "0.93 0.955 0.985 rg\n{$leftX} {$headerY} " . self::CONTENT_WIDTH . " {$headerH} re f\n";
        $stream .= "0.82 0.86 0.92 RG\n0.5 w\n{$leftX} {$headerY} " . self::CONTENT_WIDTH . " 0 re S\n";

        // Header Titles
        $stream .= "BT\n/F1 7.5 Tf\n0.08 0.20 0.40 rg\n" . ($leftX + 8.0) . " " . ($y - 11.5) . " Td\n(" .
            self::escape("SELLER (ISSUING TAXPAYER)") . ") Tj\nET\n";
        $stream .= "BT\n/F1 7.5 Tf\n0.08 0.20 0.40 rg\n" . ($rightX + 8.0) . " " . ($y - 11.5) . " Td\n(" .
            self::escape("BUYER / CUSTOMER & BILLING DETAILS") . ") Tj\nET\n";

        // Render Data Rows
        $curY = $headerY;
        $rowCount = count($computedRows);

        foreach ($computedRows as $idx => $r) {
            $rowH = $r['height'];
            $textTopY = $curY - 9.5;

            // --- Left Column (Seller) ---
            $stream .= "BT\n/F1 6.8 Tf\n0.30 0.35 0.42 rg\n" . ($leftX + 8.0) . " {$textTopY} Td\n(" .
                self::escape($r['s_lbl']) . ") Tj\nET\n";

            $sFont = $r['s_bold'] ? '/F1' : '/F2';
            $sColor = $r['s_bold'] ? "0.08 0.14 0.24 rg\n" : "0.15 0.18 0.24 rg\n";
            $lineY = $textTopY;
            foreach ($r['s_lines'] as $line) {
                $stream .= "BT\n{$sFont} 7.0 Tf\n{$sColor}" . ($leftX + 8.0 + $labelWidth) . " {$lineY} Td\n(" .
                    self::escape($line) . ") Tj\nET\n";
                $lineY -= 9.5;
            }

            // --- Right Column (Customer) ---
            $stream .= "BT\n/F1 6.8 Tf\n0.30 0.35 0.42 rg\n" . ($rightX + 8.0) . " {$textTopY} Td\n(" .
                self::escape($r['c_lbl']) . ") Tj\nET\n";

            $cFont = $r['c_bold'] ? '/F1' : '/F2';
            $cColor = $r['c_bold'] ? "0.08 0.14 0.24 rg\n" : "0.15 0.18 0.24 rg\n";
            $lineY = $textTopY;
            foreach ($r['c_lines'] as $line) {
                $stream .= "BT\n{$cFont} 7.0 Tf\n{$cColor}" . ($rightX + 8.0 + $labelWidth) . " {$lineY} Td\n(" .
                    self::escape($line) . ") Tj\nET\n";
                $lineY -= 9.5;
            }

            // Hairline separator between rows
            if ($idx < $rowCount - 1) {
                $divY = $curY - $rowH;
                $stream .= "0.88 0.91 0.95 RG\n0.35 w\n{$leftX} {$divY} " . self::CONTENT_WIDTH . " 0 re S\n";
            }

            $curY -= $rowH;
        }

        return [$stream, $totalBoxHeight];
    }

    /**
     * Draw the side-by-side Financial Summary, Payment Terms, and BIR Certification sections.
     */
    private function drawFinancialSummarySection(array $parsed, float $y): string
    {
        $stream = "";

        $leftW = 268.0;
        $rightW = self::CONTENT_WIDTH - $leftW - 12.0; // 225.28 pt
        $sectionH = 98.0;
        $boxY = $y - $sectionH;

        $leftX = self::MARGIN_LEFT;
        $rightX = self::MARGIN_LEFT + $leftW + 12.0;

        // --- LEFT SIDE: AMOUNT IN WORDS, PAYMENT TERMS, & BIR CERTIFICATION ---
        // Box 1: Amount in Words
        $b1H = 32.0;
        $b1Y = $y - $b1H;
        $stream .= "0.985 0.990 0.995 rg\n{$leftX} {$b1Y} {$leftW} {$b1H} re f\n";
        $stream .= "0.82 0.86 0.92 RG\n0.5 w\n{$leftX} {$b1Y} {$leftW} {$b1H} re S\n";
        $stream .= "BT\n/F1 6.5 Tf\n0.35 0.40 0.48 rg\n" . ($leftX + 8.0) . " " . ($y - 10.0) . " Td\n(" .
            self::escape("TOTAL NET AMOUNT IN WORDS:") . ") Tj\nET\n";

        $words = $parsed['amount_in_words'] ?: "Seven Hundred Twenty-Five Thousand Pesos Only";
        $wordLines = $this->wrapText($words, $leftW - 16.0, 7.2, true);
        $wY = $y - 20.5;
        foreach ($wordLines as $wLine) {
            $stream .= "BT\n/F1 7.2 Tf\n0.08 0.16 0.28 rg\n" . ($leftX + 8.0) . " {$wY} Td\n(" .
                self::escape($wLine) . ") Tj\nET\n";
            $wY -= 9.5;
        }

        // Box 2: Payment instructions
        $b2H = 36.0;
        $b2Y = $b1Y - 4.0 - $b2H;
        $stream .= "0.985 0.990 0.995 rg\n{$leftX} {$b2Y} {$leftW} {$b2H} re f\n";
        $stream .= "0.82 0.86 0.92 RG\n0.5 w\n{$leftX} {$b2Y} {$leftW} {$b2H} re S\n";
        $stream .= "BT\n/F1 6.5 Tf\n0.35 0.40 0.48 rg\n" . ($leftX + 8.0) . " " . ($b1Y - 13.0) . " Td\n(" .
            self::escape("SETTLEMENT & PAYMENT INSTRUCTIONS:") . ") Tj\nET\n";

        $stream .= "BT\n/F2 7.0 Tf\n0.15 0.18 0.24 rg\n" . ($leftX + 8.0) . " " . ($b1Y - 24.0) . " Td\n(" .
            self::escape($parsed['payment_terms_full'] ?: "Net 30 Calendar Days via Authorized Government Depository Bank (LBP)") . ") Tj\nET\n";

        $stream .= "BT\n/F2 6.5 Tf\n0.35 0.40 0.48 rg\n" . ($leftX + 8.0) . " " . ($b1Y - 34.0) . " Td\n(" .
            self::escape("Remit to: Land Bank of the Philippines (LBP) MDS Account | Ref: " . $parsed['invoice_no']) . ") Tj\nET\n";

        // Box 3: BIR Electronic Invoicing Certification
        $b3H = 26.0;
        $b3Y = $b2Y - 4.0 - $b3H;
        $stream .= "0.975 0.985 0.995 rg\n{$leftX} {$b3Y} {$leftW} {$b3H} re f\n";
        $stream .= "0.82 0.86 0.92 RG\n0.5 w\n{$leftX} {$b3Y} {$leftW} {$b3H} re S\n";
        $stream .= "0.12 0.35 0.65 rg\n{$leftX} {$b3Y} 3.0 {$b3H} re f\n";

        $stream .= "BT\n/F1 6.5 Tf\n0.08 0.25 0.55 rg\n" . ($leftX + 8.0) . " " . ($b2Y - 12.0) . " Td\n(" .
            self::escape("BIR ELECTRONIC INVOICE & TAX COMPLIANCE CERTIFICATION:") . ") Tj\nET\n";

        $certText = $parsed['bir_certification'] ?: "Official electronic invoice issued pursuant to RA 11976 (EOPT) & RA 10963 (TRAIN Law). Digital records permanently archived under HIMS Official Records Protocol.";
        $certLines = $this->wrapText($certText, $leftW - 16.0, 6.2, false);
        $cY = $b2Y - 21.0;
        foreach ($certLines as $cLine) {
            $stream .= "BT\n/F2 6.2 Tf\n0.30 0.35 0.42 rg\n" . ($leftX + 8.0) . " {$cY} Td\n(" .
                self::escape($cLine) . ") Tj\nET\n";
            $cY -= 8.5;
        }

        // --- RIGHT SIDE: FINANCIAL LEDGER TABLE ---
        $stream .= "1 1 1 rg\n{$rightX} {$boxY} {$rightW} {$sectionH} re f\n";
        $stream .= "0.80 0.85 0.92 RG\n0.75 w\n{$rightX} {$boxY} {$rightW} {$sectionH} re S\n";

        $ledgerRows = [
            ['lbl' => 'Subtotal (Gross Sales):', 'val' => $parsed['subtotal']],
            ['lbl' => 'Less: Trade Discounts:', 'val' => $parsed['total_discount']],
            ['lbl' => 'Total Net Sales:', 'val' => $parsed['net_sales']],
            ['lbl' => 'VATable Sales (12%):', 'val' => $parsed['vatable_sales']],
            ['lbl' => 'VAT-Exempt Sales:', 'val' => $parsed['vat_exempt_sales']],
            ['lbl' => 'Zero-Rated Sales:', 'val' => $parsed['zero_rated_sales']],
            ['lbl' => 'Value-Added Tax (12% VAT):', 'val' => $parsed['vat_amount']],
        ];

        $lY = $y - 11.0;
        foreach ($ledgerRows as $row) {
            $stream .= "BT\n/F2 6.8 Tf\n0.30 0.35 0.42 rg\n" . ($rightX + 8.0) . " {$lY} Td\n(" .
                self::escape($row['lbl']) . ") Tj\nET\n";

            $valW = self::getTextWidth($row['val'], 6.8, false);
            $stream .= "BT\n/F2 6.8 Tf\n0.10 0.14 0.22 rg\n" . ($rightX + $rightW - 8.0 - $valW) . " {$lY} Td\n(" .
                self::escape($row['val']) . ") Tj\nET\n";
            $lY -= 10.5;
        }

        // Prominent High-Contrast Net Amount Due Bar
        $dueBarH = 22.0;
        $dueBarY = $boxY;
        $stream .= "0.10 0.22 0.40 rg\n{$rightX} {$dueBarY} {$rightW} {$dueBarH} re f\n";
        $stream .= "0.08 0.18 0.32 RG\n0.75 w\n{$rightX} {$dueBarY} {$rightW} {$dueBarH} re S\n";

        $stream .= "BT\n/F1 8.0 Tf\n1 1 1 rg\n" . ($rightX + 8.0) . " " . ($dueBarY + 7.5) . " Td\n(" .
            self::escape("TOTAL NET DUE:") . ") Tj\nET\n";

        $netDueStr = $parsed['total_due'];
        $netDueW = self::getTextWidth($netDueStr, 10.0, true);
        $stream .= "BT\n/F1 10.0 Tf\n1 1 1 rg\n" . ($rightX + $rightW - 8.0 - $netDueW) . " " . ($dueBarY + 7.0) . " Td\n(" .
            self::escape($netDueStr) . ") Tj\nET\n";

        return $stream;
    }

    /**
     * Draw the Logistics Dispatch & Receiving Audit Trail section.
     */
    private function drawLogisticsDeliveryPanel(array $parsed, float $y): string
    {
        $stream = "";
        $panelH = 38.0;
        $boxY = $y - $panelH;

        $stream .= "0.985 0.990 0.995 rg\n" . self::MARGIN_LEFT . " {$boxY} " . self::CONTENT_WIDTH . " {$panelH} re f\n";
        $stream .= "0.82 0.86 0.92 RG\n0.6 w\n" . self::MARGIN_LEFT . " {$boxY} " . self::CONTENT_WIDTH . " {$panelH} re S\n";

        // Header Line inside panel
        $stream .= "BT\n/F1 6.8 Tf\n0.08 0.20 0.40 rg\n" . (self::MARGIN_LEFT + 8.0) . " " . ($y - 9.5) . " Td\n(" .
            self::escape("LOGISTICS DISPATCH & RECEPTION AUDIT TRAIL") . ") Tj\nET\n";

        // Divider inside panel
        $stream .= "0.88 0.91 0.95 RG\n0.4 w\n" . self::MARGIN_LEFT . " " . ($y - 13.0) . " " . self::CONTENT_WIDTH . " 0 re S\n";

        // 2-Column Details
        $leftColX = self::MARGIN_LEFT + 8.0;
        $rightColX = self::MARGIN_LEFT + 256.0;
        $row1Y = $y - 23.0;
        $row2Y = $y - 33.5;

        // Left Row 1
        $stream .= "BT\n/F1 6.5 Tf\n0.35 0.40 0.48 rg\n{$leftColX} {$row1Y} Td\n(" . self::escape("Procurement Link:") . ") Tj\nET\n";
        $stream .= "BT\n/F2 6.8 Tf\n0.10 0.14 0.22 rg\n" . ($leftColX + 75.0) . " {$row1Y} Td\n(" .
            self::escape("PO: " . $parsed['po_reference'] . " | GRN: " . $parsed['grn_number']) . ") Tj\nET\n";

        // Left Row 2
        $stream .= "BT\n/F1 6.5 Tf\n0.35 0.40 0.48 rg\n{$leftColX} {$row2Y} Td\n(" . self::escape("Cold Chain Status:") . ") Tj\nET\n";
        $stream .= "BT\n/F2 6.8 Tf\n0.10 0.14 0.22 rg\n" . ($leftColX + 75.0) . " {$row2Y} Td\n(" .
            self::escape("Biological Spec (2.0°C - 8.0°C) | Logger: " . $parsed['temp_logger_id']) . ") Tj\nET\n";

        // Right Row 1
        $stream .= "BT\n/F1 6.5 Tf\n0.35 0.40 0.48 rg\n{$rightColX} {$row1Y} Td\n(" . self::escape("Consignment / DR:") . ") Tj\nET\n";
        $stream .= "BT\n/F2 6.8 Tf\n0.10 0.14 0.22 rg\n" . ($rightColX + 75.0) . " {$row1Y} Td\n(" .
            self::escape("DR: " . $parsed['dr_number'] . " | Waybill: " . $parsed['waybill_number']) . ") Tj\nET\n";

        // Right Row 2
        $stream .= "BT\n/F1 6.5 Tf\n0.35 0.40 0.48 rg\n{$rightColX} {$row2Y} Td\n(" . self::escape("Receiving Facility:") . ") Tj\nET\n";
        $stream .= "BT\n/F2 6.8 Tf\n0.10 0.14 0.22 rg\n" . ($rightColX + 75.0) . " {$row2Y} Td\n(" .
            self::escape("Hospital Central Receiving Dock, Bay 1") . ") Tj\nET\n";

        return $stream;
    }

    /**
     * Draw the three-column official receiving, inspection, and accounting sign-off blocks.
     */
    private function drawOfficialSignOffBlocks(array $parsed, float $y): string
    {
        $stream = "";
        $colW = 160.0;
        $gap = (self::CONTENT_WIDTH - ($colW * 3.0)) / 2.0; // ~12.64 pt
        $cardH = 74.0;
        $boxY = $y - $cardH;

        $x1 = self::MARGIN_LEFT;
        $x2 = $x1 + $colW + $gap;
        $x3 = $x2 + $colW + $gap;

        // Card 1: Seller
        $stream .= "0.985 0.990 0.995 rg\n{$x1} {$boxY} {$colW} {$cardH} re f\n";
        $stream .= "0.82 0.86 0.92 RG\n0.6 w\n{$x1} {$boxY} {$colW} {$cardH} re S\n";
        $stream .= "0.93 0.955 0.985 rg\n{$x1} " . ($y - 15.0) . " {$colW} 15 re f\n";
        $stream .= "0.82 0.86 0.92 RG\n0.4 w\n{$x1} " . ($y - 15.0) . " {$colW} 0 re S\n";
        $stream .= "BT\n/F1 6.8 Tf\n0.08 0.20 0.40 rg\n" . ($x1 + 8.0) . " " . ($y - 10.5) . " Td\n(" .
            self::escape("PREPARED & ISSUED BY (SELLER)") . ") Tj\nET\n";

        $sigLineY = $boxY + 32.0;
        $stream .= "0.30 0.35 0.42 RG\n0.75 w\n" . ($x1 + 10.0) . " {$sigLineY} " . ($colW - 20.0) . " 0 re S\n";
        $stream .= "BT\n/F1 7.2 Tf\n0.10 0.14 0.22 rg\n" . ($x1 + 10.0) . " " . ($sigLineY - 10.0) . " Td\n(" .
            self::escape("Authorized Commercial Accountant") . ") Tj\nET\n";
        $stream .= "BT\n/F2 6.5 Tf\n0.35 0.40 0.48 rg\n" . ($x1 + 10.0) . " " . ($sigLineY - 19.5) . " Td\n(" .
            self::escape($parsed['seller']['name']) . ") Tj\nET\n";
        $stream .= "BT\n/F2 6.2 Tf\n0.40 0.45 0.52 rg\n" . ($x1 + 10.0) . " " . ($sigLineY - 28.0) . " Td\n(" .
            self::escape("Date: ________________________") . ") Tj\nET\n";

        // Card 2: Hospital Receiving
        $stream .= "0.985 0.990 0.995 rg\n{$x2} {$boxY} {$colW} {$cardH} re f\n";
        $stream .= "0.82 0.86 0.92 RG\n0.6 w\n{$x2} {$boxY} {$colW} {$cardH} re S\n";
        $stream .= "0.93 0.955 0.985 rg\n{$x2} " . ($y - 15.0) . " {$colW} 15 re f\n";
        $stream .= "0.82 0.86 0.92 RG\n0.4 w\n{$x2} " . ($y - 15.0) . " {$colW} 0 re S\n";
        $stream .= "BT\n/F1 6.8 Tf\n0.08 0.20 0.40 rg\n" . ($x2 + 8.0) . " " . ($y - 10.5) . " Td\n(" .
            self::escape("INSPECTED & RECEIVED (HOSPITAL)") . ") Tj\nET\n";

        $stream .= "0.30 0.35 0.42 RG\n0.75 w\n" . ($x2 + 10.0) . " {$sigLineY} " . ($colW - 20.0) . " 0 re S\n";
        $stream .= "BT\n/F1 7.2 Tf\n0.10 0.14 0.22 rg\n" . ($x2 + 10.0) . " " . ($sigLineY - 10.0) . " Td\n(" .
            self::escape("Property & Custody Receiving Officer") . ") Tj\nET\n";
        $stream .= "BT\n/F2 6.5 Tf\n0.35 0.40 0.48 rg\n" . ($x2 + 10.0) . " " . ($sigLineY - 19.5) . " Td\n(" .
            self::escape("Central Medical Logistics Division") . ") Tj\nET\n";
        $stream .= "BT\n/F2 6.2 Tf\n0.40 0.45 0.52 rg\n" . ($x2 + 10.0) . " " . ($sigLineY - 28.0) . " Td\n(" .
            self::escape("Date: ________________________") . ") Tj\nET\n";

        // Card 3: Internal Audit / COA
        $stream .= "0.985 0.990 0.995 rg\n{$x3} {$boxY} {$colW} {$cardH} re f\n";
        $stream .= "0.82 0.86 0.92 RG\n0.6 w\n{$x3} {$boxY} {$colW} {$cardH} re S\n";
        $stream .= "0.93 0.955 0.985 rg\n{$x3} " . ($y - 15.0) . " {$colW} 15 re f\n";
        $stream .= "0.82 0.86 0.92 RG\n0.4 w\n{$x3} " . ($y - 15.0) . " {$colW} 0 re S\n";
        $stream .= "BT\n/F1 6.8 Tf\n0.08 0.20 0.40 rg\n" . ($x3 + 8.0) . " " . ($y - 10.5) . " Td\n(" .
            self::escape("AUDITED & ACCEPTED (COA/IAR)") . ") Tj\nET\n";

        $stream .= "0.30 0.35 0.42 RG\n0.75 w\n" . ($x3 + 10.0) . " {$sigLineY} " . ($colW - 20.0) . " 0 re S\n";
        $stream .= "BT\n/F1 7.2 Tf\n0.10 0.14 0.22 rg\n" . ($x3 + 10.0) . " " . ($sigLineY - 10.0) . " Td\n(" .
            self::escape("Inspection & Acceptance Committee") . ") Tj\nET\n";
        $stream .= "BT\n/F2 6.5 Tf\n0.35 0.40 0.48 rg\n" . ($x3 + 10.0) . " " . ($sigLineY - 19.5) . " Td\n(" .
            self::escape("Internal Audit / COA Resident Unit") . ") Tj\nET\n";
        $stream .= "BT\n/F2 6.2 Tf\n0.40 0.45 0.52 rg\n" . ($x3 + 10.0) . " " . ($sigLineY - 28.0) . " Td\n(" .
            self::escape("Date: ________________________") . ") Tj\nET\n";

        return $stream;
    }

    /**
     * Parse raw title, subtitle, and sections array into structured invoice attributes,
     * resolving and enriching against actual HIMS database models (PurchaseOrder, GoodsReceiptNote, Supplier).
     */
    private function parseInvoiceData(string $title, ?string $subtitle, array $sections): array
    {
        $sellerName = "Zuellig Pharma Philippines, Inc.";
        if (str_contains($title, ' - ')) {
            $parts = explode(' - ', $title);
            $sellerName = trim($parts[0]);
        } elseif (! empty($title)) {
            $sellerName = trim(str_replace(['ELECTRONIC SALES INVOICE', 'SALES INVOICE'], '', $title));
            $sellerName = rtrim($sellerName, " -:");
        }

        $siNumber = "SI-2026-088192";
        $birCompliance = "BIR Electronic Invoice (RA 11976 Ease of Paying Taxes Compliant)";
        if ($subtitle) {
            if (preg_match('/SI\s*No:\s*([A-Za-z0-9-]+)/i', $subtitle, $m)) {
                $siNumber = $m[1];
            }
            if (str_contains($subtitle, '|')) {
                $birCompliance = trim(explode('|', $subtitle)[0]);
            }
        }

        $seller = [
            'name' => $sellerName,
            'tin' => '000-123-456-000',
            'address' => 'KM 14 West Service Rd, South Superhighway, Paranaque City',
            'tax_type' => 'VAT-Registered Commercial Enterprise',
            'business_style' => 'Pharmaceuticals & Healthcare Supply Wholesaler',
        ];

        $customer = [
            'name' => 'Hospital Information Management System',
            'fund_cluster' => '01 Regular Agency Fund',
            'billing_address' => 'Central Medical Logistics & Supply Division, Manila, Philippines',
            'delivery_address' => 'Hospital Central Receiving Dock Bay 1, Manila',
        ];

        $poRef = 'PO-2026-09-0145';
        $invoiceDate = now()->toDateString();
        $terms = 'Net 30 Days';

        $items = [];
        $rawTotalDue = null;
        $words = null;
        $vatStatus = 'Zero-Rated / Exempt under Republic Act 10963 (TRAIN Law)';
        $paymentTermsFull = 'Net 30 Calendar Days via Authorized Government Depository Bank (LBP)';
        $birCertification = 'Official electronic sales invoice archived pursuant to RA 11976 digital invoicing regulations.';

        foreach ($sections as $section) {
            $heading = strtoupper($section['heading'] ?? '');

            // Taxpayer & Invoice Details
            if (str_contains($heading, 'TAXPAYER') || str_contains($heading, 'INVOICE DETAILS')) {
                foreach ($section['lines'] ?? [] as $line) {
                    $parts = array_map('trim', explode('|', $line));
                    foreach ($parts as $p) {
                        if (stripos($p, 'Seller:') === 0) {
                            $seller['name'] = trim(substr($p, 7));
                        } elseif (stripos($p, 'VAT Reg TIN:') === 0) {
                            $seller['tin'] = trim(substr($p, 12));
                        } elseif (stripos($p, 'Customer:') === 0) {
                            $customer['name'] = trim(substr($p, 9));
                        } elseif (stripos($p, 'Fund Cluster:') === 0) {
                            $customer['fund_cluster'] = trim(substr($p, 13));
                        } elseif (stripos($p, 'Billing Address:') === 0) {
                            $customer['billing_address'] = trim(substr($p, 16));
                        } elseif (stripos($p, 'Purchase Order Ref:') === 0) {
                            $poRef = trim(substr($p, 19));
                        } elseif (stripos($p, 'Invoice Date:') === 0) {
                            $invoiceDate = trim(substr($p, 13));
                        } elseif (stripos($p, 'Terms:') === 0) {
                            $terms = trim(substr($p, 6));
                        }
                    }
                }
            }

            // Invoiced Line Items Table
            if (! empty($section['table']['rows'])) {
                foreach ($section['table']['rows'] as $row) {
                    $desc = $row[0] ?? 'Medical Supplies';
                    $qtyUnit = $row[1] ?? '1 unit';
                    $price = $row[2] ?? '0.00';
                    $tax = $row[3] ?? 'VAT-Exempt';
                    $tot = $row[4] ?? '0.00';

                    // Parse quantity and unit
                    $qty = $qtyUnit;
                    $unit = 'unit';
                    if (preg_match('/^([0-9,]+(?:\.[0-9]+)?)\s*(.*)$/', trim($qtyUnit), $qm)) {
                        $qty = $qm[1];
                        $unit = $qm[2] ?: 'unit';
                    }

                    $items[] = [
                        'description' => $desc,
                        'sku' => 'N/A',
                        'quantity' => $qty,
                        'unit' => $unit,
                        'unit_price' => $price,
                        'tax_status' => $tax,
                        'discount' => '0.00',
                        'total' => $tot,
                    ];
                }
            }

            // Financial Summary & Certification
            if (str_contains($heading, 'FINANCIAL') || str_contains($heading, 'CERTIFICATION')) {
                foreach ($section['lines'] ?? [] as $line) {
                    if (stripos($line, 'Total Net Amount Due:') === 0) {
                        $rawDue = trim(substr($line, 21));
                        if (preg_match('/^(PHP\s*[0-9,]+(?:\.[0-9]{2})?)\s*(?:\((.*)\))?$/i', $rawDue, $dm)) {
                            $rawTotalDue = $dm[1];
                            if (! empty($dm[2])) {
                                $words = $dm[2];
                            }
                        } else {
                            $rawTotalDue = $rawDue;
                        }
                    } elseif (stripos($line, 'VAT Status:') === 0) {
                        $vatStatus = trim(substr($line, 11));
                    } elseif (stripos($line, 'Payment Terms:') === 0) {
                        $paymentTermsFull = trim(substr($line, 14));
                    } elseif (stripos($line, 'BIR Digital Certification:') === 0) {
                        $birCertification = trim(substr($line, 26));
                    }
                }
            }
        }

        // Default item fallback if empty
        if (empty($items)) {
            $items[] = [
                'description' => 'Verorab Inactivated Rabies Vaccine 0.5mL Vial',
                'sku' => 'VAC-RAB-VER05',
                'quantity' => '500',
                'unit' => 'vials',
                'unit_price' => '1,450.00',
                'tax_status' => 'VAT-Exempt',
                'discount' => '0.00',
                'total' => '725,000.00',
            ];
        }

        // Cross-reference actual HIMS database records for live accuracy
        $grnNumber = 'GRN-2026-09-0881';
        $drNumber = 'DR-ZP-889922';
        $waybillNumber = 'WB-MNL-00912';
        $tempLoggerId = 'SEN-LOG-ZP-9941';

        if (class_exists(\App\Models\PurchaseOrder::class)) {
            try {
                $dbPo = \App\Models\PurchaseOrder::with(['supplier', 'lines.item'])->where('po_number', $poRef)->first();
                if ($dbPo) {
                    if ($dbPo->fund_cluster && empty($customer['fund_cluster'])) {
                        $customer['fund_cluster'] = $dbPo->fund_cluster;
                    }
                    if ($dbPo->entity_name && empty($customer['name'])) {
                        $customer['name'] = $dbPo->entity_name;
                    }
                    if ($dbPo->supplier) {
                        if ($dbPo->supplier->tax_number && $seller['tin'] === '000-123-456-000') {
                            $seller['tin'] = $dbPo->supplier->tax_number;
                        }
                        if ($dbPo->supplier->address && empty($seller['address'])) {
                            $seller['address'] = $dbPo->supplier->address;
                        }
                    }

                    // Enrich line item SKU codes
                    foreach ($items as &$it) {
                        if ($it['sku'] === 'N/A' || empty($it['sku'])) {
                            foreach ($dbPo->lines as $pol) {
                                if ($pol->item && (stripos($pol->item->name, substr($it['description'], 0, 15)) !== false || stripos($it['description'], substr($pol->item->name, 0, 15)) !== false)) {
                                    $it['sku'] = $pol->item->sku;
                                    break;
                                }
                            }
                        }
                    }
                    unset($it);
                }

                if (class_exists(\App\Models\GoodsReceiptNote::class)) {
                    $dbGrn = \App\Models\GoodsReceiptNote::where('sales_invoice_number', $siNumber)
                        ->orWhere('purchase_order_id', $dbPo?->id)
                        ->first();
                    if ($dbGrn) {
                        $grnNumber = $dbGrn->grn_number ?: $grnNumber;
                        $drNumber = $dbGrn->dr_number ?: $drNumber;
                        $waybillNumber = $dbGrn->waybill_number ?: $waybillNumber;
                        $tempLoggerId = $dbGrn->temp_logger_id ?: $tempLoggerId;
                    }
                }
            } catch (\Throwable) {
                // Keep defaults if database unbooted or in offline mode
            }
        }

        // Mathematical calculation of totals
        $grossSubtotal = 0.0;
        $totalDiscount = 0.0;
        $vatExemptSales = 0.0;
        $zeroRatedSales = 0.0;
        $vatableSales = 0.0;
        $vatAmount = 0.0;

        foreach ($items as $it) {
            $q = (float) str_replace(',', '', (string) $it['quantity']);
            $p = (float) str_replace(',', '', (string) $it['unit_price']);
            $lineTot = (float) str_replace(',', '', (string) $it['total']);
            $disc = (float) str_replace(',', '', (string) ($it['discount'] ?? 0));

            $calcGross = $q * $p;
            if ($calcGross > 0) {
                $grossSubtotal += $calcGross;
            } else {
                $grossSubtotal += $lineTot;
            }
            $totalDiscount += $disc;

            $taxStatus = strtoupper($it['tax_status'] ?? '');
            if (str_contains($taxStatus, 'EXEMPT')) {
                $vatExemptSales += $lineTot;
            } elseif (str_contains($taxStatus, 'ZERO')) {
                $zeroRatedSales += $lineTot;
            } else {
                $vatableLine = round($lineTot / 1.12, 2);
                $vatableSales += $vatableLine;
                $vatAmount += ($lineTot - $vatableLine);
            }
        }

        $netSales = $grossSubtotal - $totalDiscount;
        $computedTotalNetDue = $netSales;

        $totalDue = $rawTotalDue ?: ('PHP ' . number_format($computedTotalNetDue, 2));

        if (empty($words)) {
            $words = self::convertNumberToWords($computedTotalNetDue);
        }

        return [
            'seller' => $seller,
            'customer' => $customer,
            'invoice_no' => $siNumber,
            'invoice_date' => $invoiceDate,
            'po_reference' => $poRef,
            'payment_terms' => $terms,
            'payment_terms_full' => $paymentTermsFull,
            'bir_compliance' => $birCompliance,
            'items' => $items,
            'subtotal' => 'PHP ' . number_format($grossSubtotal, 2),
            'total_discount' => '0.00',
            'net_sales' => number_format($netSales, 2),
            'vatable_sales' => number_format($vatableSales, 2),
            'vat_exempt_sales' => number_format($vatExemptSales, 2),
            'zero_rated_sales' => number_format($zeroRatedSales, 2),
            'vat_amount' => $vatAmount > 0 ? number_format($vatAmount, 2) : '0.00 (Exempt)',
            'total_due' => $totalDue,
            'amount_in_words' => $words,
            'vat_status' => $vatStatus,
            'bir_certification' => $birCertification,
            'grn_number' => $grnNumber,
            'dr_number' => $drNumber,
            'waybill_number' => $waybillNumber,
            'temp_logger_id' => $tempLoggerId,
        ];
    }

    /**
     * Convert monetary amount to English words for official invoice settlement.
     */
    private static function convertNumberToWords(float $amount): string
    {
        $ones = [
            0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
            5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
            10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
            15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'
        ];
        $tens = [
            2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
            6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'
        ];

        $pesos = (int) floor($amount);
        $cents = (int) round(($amount - $pesos) * 100);

        if ($pesos === 0) {
            return 'Zero Pesos Only';
        }

        $triplets = [];
        while ($pesos > 0) {
            $triplets[] = $pesos % 1000;
            $pesos = (int) floor($pesos / 1000);
        }

        $scales = ['', 'Thousand', 'Million', 'Billion'];
        $words = [];

        for ($i = count($triplets) - 1; $i >= 0; $i--) {
            $num = $triplets[$i];
            if ($num === 0) {
                continue;
            }

            $part = '';
            $hundreds = (int) floor($num / 100);
            $rem = $num % 100;

            if ($hundreds > 0) {
                $part .= $ones[$hundreds] . ' Hundred';
                if ($rem > 0) {
                    $part .= ' ';
                }
            }

            if ($rem > 0) {
                if ($rem < 20) {
                    $part .= $ones[$rem];
                } else {
                    $t = (int) floor($rem / 10);
                    $o = $rem % 10;
                    $part .= $tens[$t];
                    if ($o > 0) {
                        $part .= '-' . $ones[$o];
                    }
                }
            }

            if ($scales[$i] !== '') {
                $part .= ' ' . $scales[$i];
            }

            $words[] = $part;
        }

        $result = implode(' ', $words) . ' Pesos';
        if ($cents > 0) {
            $result .= " and {$cents}/100";
        }
        $result .= ' Only';

        return $result;
    }

    /**
     * Render general PDF documents (e.g. Delivery Receipts, Licenses, Records) with enhanced typography and A4 layout.
     */
    private function renderGeneralDocument(string $title, ?string $subtitle, array $sections): string
    {
        $pages = [];
        $stream = "0 0 0 RG\n0 0 0 rg\n";

        // Top Accent Bar
        $stream .= "0.08 0.22 0.45 rg\n" . self::MARGIN_LEFT . " 808 " . self::CONTENT_WIDTH . " 3.5 re f\n";

        // Document Title
        $stream .= "BT\n/F1 13.5 Tf\n0.08 0.18 0.35 rg\n" . self::MARGIN_LEFT . " 786 Td\n(" . self::escape($title) . ") Tj\nET\n";

        $y = 770.0;
        if ($subtitle !== null) {
            $stream .= "BT\n/F2 8.5 Tf\n0.35 0.40 0.48 rg\n" . self::MARGIN_LEFT . " {$y} Td\n(" . self::escape($subtitle) . ") Tj\nET\n";
            $y -= 15.0;
        }

        // Header Divider Line
        $stream .= "0.82 0.86 0.92 RG\n0.75 w\n" . self::MARGIN_LEFT . " {$y} " . self::CONTENT_WIDTH . " 0 re S\n";
        $y -= 16.0;

        foreach ($sections as $section) {
            if (! empty($section['heading'])) {
                // Section header
                $stream .= "0.12 0.35 0.65 rg\n" . self::MARGIN_LEFT . " " . ($y - 2) . " 3.5 12 re f\n";
                $stream .= "BT\n/F1 9.5 Tf\n0.10 0.18 0.30 rg\n" . (self::MARGIN_LEFT + 8.0) . " {$y} Td\n(" . self::escape($section['heading']) . ") Tj\nET\n";
                $y -= 16.0;
            }

            if (! empty($section['lines'])) {
                $lineCount = count($section['lines']);
                $boxHeight = ($lineCount * 15.0) + 12.0;
                $boxY = $y - $boxHeight;

                $stream .= "0.975 0.985 0.995 rg\n" . self::MARGIN_LEFT . " {$boxY} " . self::CONTENT_WIDTH . " {$boxHeight} re f\n";
                $stream .= "0.85 0.88 0.93 RG\n0.6 w\n" . self::MARGIN_LEFT . " {$boxY} " . self::CONTENT_WIDTH . " {$boxHeight} re S\n";

                $textY = $y - 12.0;
                foreach ($section['lines'] as $line) {
                    $stream .= "BT\n/F2 8 Tf\n0.15 0.18 0.22 rg\n" . (self::MARGIN_LEFT + 10.0) . " {$textY} Td\n(" . self::escape($line) . ") Tj\nET\n";
                    $textY -= 14.5;
                }

                $y = $boxY - 14.0;
            }

            if (! empty($section['table'])) {
                $headers = $section['table']['headers'] ?? [];
                $rows = $section['table']['rows'] ?? [];
                $colCount = count($headers);

                if ($colCount > 0) {
                    $configuredWidths = $section['table']['widths'] ?? [];
                    $configuredTotal = array_sum($configuredWidths);
                    $columnWidths = [];

                    for ($columnIndex = 0; $columnIndex < $colCount; $columnIndex++) {
                        $configuredWidth = (float) ($configuredWidths[$columnIndex] ?? 0);
                        $columnWidths[] = $configuredTotal > 0
                            ? self::CONTENT_WIDTH * ($configuredWidth / $configuredTotal)
                            : self::CONTENT_WIDTH / $colCount;
                    }

                    $headerLines = [];
                    foreach ($headers as $idx => $headerText) {
                        $headerLines[$idx] = $this->wrapText((string) $headerText, $columnWidths[$idx] - 14.0, 7.5, true);
                    }

                    $headerLineCount = max(array_map('count', $headerLines));
                    $headerHeight = max(20.0, ($headerLineCount * 9.0) + 8.0);

                    $stream .= "0.91 0.94 0.975 rg\n" . self::MARGIN_LEFT . " " . ($y - $headerHeight) . " " . self::CONTENT_WIDTH . " {$headerHeight} re f\n";
                    $stream .= "0.75 0.80 0.88 RG\n0.75 w\n" . self::MARGIN_LEFT . " " . ($y - $headerHeight) . " " . self::CONTENT_WIDTH . " {$headerHeight} re S\n";

                    $columnX = self::MARGIN_LEFT;
                    foreach ($headerLines as $idx => $lines) {
                        $textY = $y - 12.0;
                        foreach ($lines as $headerLine) {
                            $xPos = $columnX + 7.0;
                            $stream .= "BT\n/F1 7.5 Tf\n0.06 0.16 0.32 rg\n{$xPos} {$textY} Td\n(" . self::escape($headerLine) . ") Tj\nET\n";
                            $textY -= 9.0;
                        }
                        $columnX += $columnWidths[$idx];
                    }

                    $y -= $headerHeight;

                    foreach ($rows as $rIdx => $row) {
                        $wrappedCells = [];
                        foreach ($headers as $cIdx => $_header) {
                            $wrappedCells[$cIdx] = $this->wrapText(
                                (string) ($row[$cIdx] ?? ''),
                                $columnWidths[$cIdx] - 14.0,
                                7.5,
                                $cIdx === 0 || $cIdx === ($colCount - 1)
                            );
                        }

                        $rowLineCount = max(array_map('count', $wrappedCells));
                        $rowHeight = max(20.0, ($rowLineCount * 9.5) + 8.0);
                        $rowBg = ($rIdx % 2 === 0) ? '1 1 1 rg' : '0.985 0.990 0.995 rg';

                        $stream .= "{$rowBg}\n" . self::MARGIN_LEFT . " " . ($y - $rowHeight) . " " . self::CONTENT_WIDTH . " {$rowHeight} re f\n";
                        $stream .= "0.88 0.90 0.93 RG\n0.4 w\n" . self::MARGIN_LEFT . " " . ($y - $rowHeight) . " " . self::CONTENT_WIDTH . " {$rowHeight} re S\n";

                        $columnX = self::MARGIN_LEFT;
                        foreach ($wrappedCells as $cIdx => $cellLines) {
                            $font = ($cIdx === ($colCount - 1) || $cIdx === 0) ? '/F1' : '/F2';
                            $color = ($cIdx === ($colCount - 1)) ? '0.08 0.22 0.55 rg' : '0.15 0.18 0.22 rg';
                            $textY = $y - 12.0;

                            foreach ($cellLines as $cellLine) {
                                $xPos = $columnX + 7.0;
                                $stream .= "BT\n{$font} 7.5 Tf\n{$color}\n{$xPos} {$textY} Td\n(" . self::escape($cellLine) . ") Tj\nET\n";
                                $textY -= 9.5;
                            }

                            $columnX += $columnWidths[$cIdx];
                        }

                        $y -= $rowHeight;
                    }

                    $y -= 14.0;
                }
            }
        }

        $pages[] = $stream;

        return $this->compilePdfDocument($pages);
    }

    /**
     * Compute string width in points based on standard Helvetica character metrics.
     */
    public static function getTextWidth(string $text, float $fontSize, bool $bold = false): float
    {
        $units = 0;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $c = $text[$i];
            $units += self::$charWidths[$c] ?? 550;
        }
        if ($bold) {
            $units *= 1.05;
        }

        return $units * ($fontSize / 1000.0);
    }

    /**
     * Wrap text accurately to the maximum width in points.
     */
    public function wrapText(string $text, float $maxWidth, float $fontSize, bool $bold = false): array
    {
        $text = self::sanitizeTypography($text);
        $rawParagraphs = explode("\n", $text);
        $lines = [];

        foreach ($rawParagraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                $lines[] = '';
                continue;
            }

            $words = preg_split('/\s+/', $paragraph);
            $currentLine = '';

            foreach ($words as $word) {
                $candidate = ($currentLine === '') ? $word : $currentLine . ' ' . $word;
                $candWidth = self::getTextWidth($candidate, $fontSize, $bold);

                if ($candWidth <= $maxWidth) {
                    $currentLine = $candidate;
                } else {
                    if ($currentLine !== '') {
                        $lines[] = $currentLine;
                        $currentLine = '';
                    }

                    $wordWidth = self::getTextWidth($word, $fontSize, $bold);
                    if ($wordWidth > $maxWidth) {
                        $chunk = '';
                        $wordLen = strlen($word);
                        for ($c = 0; $c < $wordLen; $c++) {
                            $testChunk = $chunk . $word[$c];
                            if (self::getTextWidth($testChunk, $fontSize, $bold) > $maxWidth && $chunk !== '') {
                                $lines[] = $chunk;
                                $chunk = $word[$c];
                            } else {
                                $chunk = $testChunk;
                            }
                        }
                        $currentLine = $chunk;
                    } else {
                        $currentLine = $word;
                    }
                }
            }

            if ($currentLine !== '') {
                $lines[] = $currentLine;
            }
        }

        return $lines;
    }

    /**
     * Sanitize non-ASCII typographic characters into clean equivalents.
     */
    public static function sanitizeTypography(string $text): string
    {
        $replacements = [
            "\u{2014}" => ' - ',
            "\u{2013}" => '-',
            "\u{2022}" => '-',
            "\u{201C}" => '"',
            "\u{201D}" => '"',
            "\u{2018}" => "'",
            "\u{2019}" => "'",
            "\u{2026}" => '...',
            "\u{00A0}" => ' ',
        ];

        $text = str_replace(array_keys($replacements), array_values($replacements), $text);

        return preg_replace('/[^\x20-\x7E\r\n\t]/', '', $text);
    }

    /**
     * Escape PDF string literals.
     */
    private static function escape(string $text): string
    {
        $sanitized = self::sanitizeTypography($text);

        return strtr($sanitized, [
            '\\' => '\\\\',
            '(' => '\\(',
            ')' => '\\)',
        ]);
    }

    /**
     * Compile page streams into a standard PDF-1.4 binary.
     */
    private function compilePdfDocument(array $pageStreams): string
    {
        $totalPages = count($pageStreams);

        // Append footers to each page with actual total page count
        foreach ($pageStreams as $pIdx => &$stream) {
            $pageNum = $pIdx + 1;
            $footerY = 36.0;

            // Footer dividing rule
            $stream .= "0.82 0.86 0.92 RG\n0.5 w\n" . self::MARGIN_LEFT . " 46 " . self::CONTENT_WIDTH . " 0 re S\n";

            // Left footer title
            $stream .= "BT\n/F2 7 Tf\n0.40 0.45 0.52 rg\n" . self::MARGIN_LEFT . " {$footerY} Td\n(" .
                self::escape("Hospital Information Management System | Official Electronic Records Archive | Verified Digital Document") .
                ") Tj\nET\n";

            // Right footer page numbering
            $pageText = "Page {$pageNum} of {$totalPages}";
            $pageWidth = self::getTextWidth($pageText, 7.0, false);
            $pageRightX = self::MARGIN_LEFT + self::CONTENT_WIDTH - $pageWidth;
            $stream .= "BT\n/F1 7 Tf\n0.30 0.35 0.42 rg\n{$pageRightX} {$footerY} Td\n(" .
                self::escape($pageText) . ") Tj\nET\n";
        }
        unset($stream);

        // Build PDF Structure
        $pdf = "%PDF-1.4\n";
        $offsets = [];

        // Obj 1: Catalog
        $offsets[1] = strlen($pdf);
        $pdf .= "1 0 obj\n<</Type/Catalog/Pages 2 0 R>>\nendobj\n";

        // Obj 2: Pages root
        $kids = [];
        for ($i = 1; $i <= $totalPages; $i++) {
            $pageObjId = 2 + $i;
            $kids[] = "{$pageObjId} 0 R";
        }
        $kidsStr = implode(" ", $kids);

        $offsets[2] = strlen($pdf);
        $pdf .= "2 0 obj\n<</Type/Pages/Count {$totalPages}/Kids[{$kidsStr}]>>\nendobj\n";

        $f1Id = 3 + $totalPages;
        $f2Id = 4 + $totalPages;
        $firstContentId = 5 + $totalPages;

        for ($i = 1; $i <= $totalPages; $i++) {
            $pageObjId = 2 + $i;
            $contentObjId = $firstContentId + ($i - 1);

            $offsets[$pageObjId] = strlen($pdf);
            $pdf .= "{$pageObjId} 0 obj\n<</Type/Page/Parent 2 0 R/MediaBox[0 0 595.28 841.89]/Resources<</Font<</F1 {$f1Id} 0 R/F2 {$f2Id} 0 R>>>>/Contents {$contentObjId} 0 R>>\nendobj\n";
        }

        // Fonts
        $offsets[$f1Id] = strlen($pdf);
        $pdf .= "{$f1Id} 0 obj\n<</Type/Font/Subtype/Type1/BaseFont/Helvetica-Bold>>\nendobj\n";

        $offsets[$f2Id] = strlen($pdf);
        $pdf .= "{$f2Id} 0 obj\n<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>\nendobj\n";

        // Content streams
        for ($i = 1; $i <= $totalPages; $i++) {
            $contentObjId = $firstContentId + ($i - 1);
            $streamData = $pageStreams[$i - 1];
            $streamLen = strlen($streamData);

            $offsets[$contentObjId] = strlen($pdf);
            $pdf .= "{$contentObjId} 0 obj\n<</Length {$streamLen}>>\nstream\n{$streamData}\nendstream\nendobj\n";
        }

        $totalObjs = $firstContentId + $totalPages - 1;

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . ($totalObjs + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $totalObjs; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<</Size " . ($totalObjs + 1) . "/Root 1 0 R>>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
