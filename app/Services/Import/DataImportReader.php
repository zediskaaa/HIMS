<?php

namespace App\Services\Import;

use DOMDocument;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use SimpleXMLElement;
use ZipArchive;

class DataImportReader
{
    /**
     * Known field aliases for each target entity.
     * Maps canonical database field => array of acceptable column aliases.
     *
     * @var array<string, array<string, array<string>>>
     */
    public const TARGET_ALIASES = [
        'items' => [
            'sku' => [
                'sku', 'item_sku', 'item_code', 'product_code', 'item_number',
                'item_no', 'item_num', 'item_id', 'stock_code', 'code',
                'barcode_value', 'catalog_number', 'cat_no', 'part_number',
                'part_no', 'reference_number', 'ref_no', 'item', 'item_identifier',
                'code_sku', 'sku_code', 'product_id', 'material_number', 'mat_no',
            ],
            'name' => [
                'name', 'item_name', 'product_name', 'item_title', 'title',
                'item_description', 'item_desc', 'description_item', 'desc_item',
                'item_description_short', 'drug_name', 'medicine_name',
                'brand_name_generic', 'product_title', 'description_name',
                'product', 'item_description_name', 'product_description',
            ],
            'description' => [
                'description', 'desc', 'details',
                'specification', 'specifications', 'specs', 'notes', 'remarks',
                'item_notes', 'item_remarks', 'item_details', 'additional_description',
            ],
            'category' => [
                'category', 'category_name', 'item_category', 'category_code',
                'category_id', 'cat', 'classification', 'item_group', 'group',
                'category_group',
            ],
            'unit' => [
                'unit', 'uom', 'unit_of_measure', 'unit_of_measurement',
                'packaging', 'package_unit', 'measure_unit', 'unit_type', 'pack_size',
            ],
            'unit_cost' => [
                'unit_cost', 'cost', 'unit_price', 'price', 'cost_price',
                'purchase_price', 'cost_per_unit', 'rate', 'amount', 'standard_cost',
                'total_cost',
            ],
            'reorder_level' => [
                'reorder_level', 'reorder_point', 'min_level', 'minimum_level',
                'min_stock', 'minimum_stock', 'min_qty', 'reorder_qty',
            ],
            'safety_stock' => [
                'safety_stock', 'buffer_stock', 'safety_level', 'safety_stock_level',
                'buffer_level',
            ],
            'critical_level' => [
                'critical_level', 'critical_stock', 'critical_point', 'emergency_stock',
                'minimum_critical',
            ],
            'expiry_alert_days' => [
                'expiry_alert_days', 'alert_days', 'expiry_days', 'expiry_warning_days',
                'shelf_life_alert_days', 'expiration_alert_days',
            ],
            'default_location' => [
                'default_location', 'storage_location', 'location', 'location_code',
                'default_storage_location', 'default_location_code', 'warehouse_location',
                'bin_location', 'shelf_location', 'store_location',
            ],
            'requires_cold_chain' => [
                'requires_cold_chain', 'cold_chain', 'is_cold_chain', 'cold_storage',
                'temperature_sensitive', 'refrigerated', 'chilled',
            ],
            'is_dangerous_drug' => [
                'is_dangerous_drug', 'dangerous_drug', 'dangerous_drugs', 'narcotic',
                'is_narcotic', 'pdea_regulated', 'controlled_substance', 'is_controlled',
            ],
            'gtin' => [
                'gtin', 'barcode', 'upc', 'ean', 'global_trade_item_number',
            ],
        ],
        'locations' => [
            'code' => [
                'code', 'location_code', 'storage_code', 'loc_code', 'location_id',
                'room_code', 'area_code', 'bin_code', 'rack_code', 'storage_id',
            ],
            'name' => [
                'name', 'location_name', 'storage_name', 'location_title', 'title',
                'room_name', 'area_name', 'storage_location_name',
            ],
            'type' => [
                'type', 'location_type', 'storage_type', 'category_type',
            ],
            'zone' => [
                'zone', 'storage_zone', 'area', 'section', 'wing', 'floor', 'aisle',
            ],
            'capacity' => [
                'capacity', 'max_capacity', 'total_capacity', 'bin_capacity', 'volume_capacity',
            ],
            'storage_classification' => [
                'storage_classification', 'classification', 'storage_class', 'class',
            ],
            'temperature_classification' => [
                'temperature_classification', 'temperature', 'temp_class', 'temp_range', 'temperature_zone',
            ],
            'status' => [
                'status', 'state', 'active_status', 'is_active',
            ],
        ],
        'suppliers' => [
            'name' => [
                'name', 'supplier_name', 'vendor_name', 'company_name', 'vendor',
                'supplier', 'company', 'provider',
            ],
            'code' => [
                'code', 'supplier_code', 'vendor_code', 'provider_code', 'supplier_id', 'vendor_id',
            ],
            'contact_person' => [
                'contact_person', 'contact_name', 'contact', 'representative', 'sales_rep',
                'agent', 'person_in_charge',
            ],
            'email' => [
                'email', 'email_address', 'contact_email', 'supplier_email', 'vendor_email', 'mail',
            ],
            'phone' => [
                'phone', 'phone_number', 'telephone', 'contact_number', 'mobile', 'tel', 'cellphone', 'contact_no',
            ],
            'address' => [
                'address', 'business_address', 'company_address', 'location_address', 'office_address', 'street_address',
            ],
            'tax_id' => [
                'tax_id', 'tin', 'tax_identification_number', 'tax_number', 'vat_number', 'tax_registration_number', 'bir_tin',
            ],
            'status' => [
                'status', 'state', 'accreditation_status', 'active_status', 'is_active',
            ],
        ],
    ];

    /**
     * Parse an uploaded file or file path into an array of associative rows.
     *
     * @return array{
     *     format: string,
     *     headers: array<string>,
     *     original_headers: array<string>,
     *     header_map: array<string, string>,
     *     header_row_number: int,
     *     rows: array<int, array<string, mixed>>,
     *     total_rows: int,
     *     raw_data_count: int
     * }
     */
    public function read(UploadedFile|string $file, ?string $format = null, ?string $target = null): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $extension = strtolower($format ?? ($file instanceof UploadedFile ? $file->getClientOriginalExtension() : pathinfo($path, PATHINFO_EXTENSION)));

        if (! file_exists($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('Import file could not be read or does not exist.');
        }

        // Dedicated JSON parsing directly preserves object keys and does NOT treat values as table headers
        if ($extension === 'json') {
            return $this->readJsonDirect($path, $target);
        }

        $rawRows = match ($extension) {
            'csv', 'txt' => $this->readCsv($path),
            'xlsx' => $this->readXlsx($path),
            'xls' => $this->readXls($path),
            default => throw new InvalidArgumentException("Unsupported file format: [{$extension}]. Please upload a CSV, Excel (.xlsx/.xls), or JSON file."),
        };

        return $this->normalizeTable($rawRows, $target, $extension);
    }

    /**
     * Parse JSON files containing array of objects or enveloped objects.
     * Maps each JSON object's keys directly to canonical HIMS fields without
     * flattening them into a pseudo-spreadsheet or treating record values as headers.
     *
     * @return array{
     *     format: string,
     *     headers: array<string>,
     *     original_headers: array<string>,
     *     header_map: array<string, string>,
     *     header_row_number: int,
     *     rows: array<int, array<string, mixed>>,
     *     total_rows: int,
     *     raw_data_count: int
     * }
     */
    public function readJsonDirect(string $path, ?string $target = null): array
    {
        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return [
                'format' => 'json',
                'headers' => [],
                'original_headers' => [],
                'header_map' => [],
                'header_row_number' => 1,
                'rows' => [],
                'total_rows' => 0,
                'raw_data_count' => 0,
            ];
        }

        $decoded = json_decode($raw, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON syntax: ' . json_last_error_msg());
        }

        // Handle common envelopes like {"data": [...]}, {"items": [...]}, {"rows": [...]}, {"records": [...]}
        if (is_array($decoded)) {
            if (isset($decoded['data']) && is_array($decoded['data']) && ! empty($decoded['data'])) {
                $decoded = $decoded['data'];
            } elseif (isset($decoded['items']) && is_array($decoded['items']) && ! empty($decoded['items'])) {
                $decoded = $decoded['items'];
            } elseif (isset($decoded['records']) && is_array($decoded['records']) && ! empty($decoded['records'])) {
                $decoded = $decoded['records'];
            } elseif (isset($decoded['rows']) && is_array($decoded['rows']) && ! empty($decoded['rows'])) {
                $decoded = $decoded['rows'];
            } elseif (isset($decoded['sections']) && is_array($decoded['sections'])) {
                // Support HIMS report export dossiers where items are listed in sections[*]['rows']
                $sectionRows = [];
                foreach ($decoded['sections'] as $section) {
                    if (isset($section['rows']) && is_array($section['rows'])) {
                        foreach ($section['rows'] as $r) {
                            $sectionRows[] = $r;
                        }
                    }
                }
                if (! empty($sectionRows)) {
                    $decoded = $sectionRows;
                }
            }
        }

        // If a single JSON object was uploaded, wrap it into an array
        if (is_array($decoded) && ! isset($decoded[0]) && ! empty($decoded)) {
            $decoded = [$decoded];
        }

        if (! is_array($decoded) || empty($decoded)) {
            return [
                'format' => 'json',
                'headers' => [],
                'original_headers' => [],
                'header_map' => [],
                'header_row_number' => 1,
                'rows' => [],
                'total_rows' => 0,
                'raw_data_count' => 0,
            ];
        }

        $targetKey = $target && isset(self::TARGET_ALIASES[$target]) ? $target : 'items';
        $canonicalHeaders = [];
        $originalHeaders = [];
        $headerMap = [];
        $normalizedRows = [];

        foreach ($decoded as $idx => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException("Invalid JSON structure at item #".($idx + 1).". Expected a JSON object with key-value pairs.");
            }

            $rowAssoc = [];
            foreach ($item as $rawKey => $val) {
                $rawKeyStr = trim((string) $rawKey);
                if ($rawKeyStr === '') {
                    continue;
                }

                $normKey = $this->normalizeHeaderName($rawKeyStr);
                $canonicalField = $this->resolveCanonicalField($normKey, $targetKey);

                if (! in_array($rawKeyStr, $originalHeaders, true)) {
                    $originalHeaders[] = $rawKeyStr;
                }
                if (! in_array($canonicalField, $canonicalHeaders, true)) {
                    $canonicalHeaders[] = $canonicalField;
                }
                $headerMap[$rawKeyStr] = $canonicalField;

                $cleanVal = is_string($val) ? trim($val) : (is_scalar($val) ? $val : json_encode($val));
                $rowAssoc[$canonicalField] = $cleanVal;
            }

            // Assign 1-based record number
            $rowAssoc['_row_number'] = $idx + 1;
            $normalizedRows[] = $rowAssoc;
        }

        // If 'name' is missing, but 'description' is present, promote 'description' to 'name'
        if ($targetKey === 'items' && ! in_array('name', $canonicalHeaders, true)) {
            $descRawKey = array_search('description', $headerMap, true);
            if ($descRawKey !== false) {
                $headerMap[$descRawKey] = 'name';
                $canonicalHeaders = array_map(fn ($f) => $f === 'description' ? 'name' : $f, $canonicalHeaders);
                foreach ($normalizedRows as &$r) {
                    if (isset($r['description']) && ! isset($r['name'])) {
                        $r['name'] = $r['description'];
                    }
                }
                unset($r);
            }
        }

        return [
            'format' => 'json',
            'headers' => array_values($canonicalHeaders),
            'original_headers' => array_values($originalHeaders),
            'header_map' => $headerMap,
            'header_row_number' => 1,
            'rows' => $normalizedRows,
            'total_rows' => count($normalizedRows),
            'raw_data_count' => count($normalizedRows),
        ];
    }

    /**
     * Parse CSV files with multi-encoding support (UTF-8, UTF-16LE/BE),
     * BOM stripping, and delimiter auto-detection.
     *
     * @return array<int, array<int, mixed>>
     */
    public function readCsv(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false || trim($content) === '') {
            return [];
        }

        // Check for binary / corrupted file (null bytes, except in UTF-16)
        if (! str_starts_with($content, "\xFF\xFE") && ! str_starts_with($content, "\xFE\xFF")) {
            if (str_contains(substr($content, 0, 2048), "\0")) {
                throw new InvalidArgumentException('Uploaded file appears to be a binary file or malformed CSV.');
            }
        }

        // Convert UTF-16LE BOM (\xFF\xFE) or UTF-16BE BOM (\xFE\xFF)
        if (str_starts_with($content, "\xFF\xFE")) {
            $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($content, "\xFE\xFF")) {
            $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE');
        } elseif (str_starts_with($content, "\xEF\xBB\xBF")) {
            // Strip UTF-8 BOM
            $content = substr($content, 3);
        }

        // Standardize line endings to LF
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // Auto-detect delimiter from non-empty lines
        $delimiter = $this->detectCsvDelimiter($content);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $rows = [];
        $lineNum = 0;
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $lineNum++;
            $cleaned = array_map(function ($cell) {
                if (! is_string($cell)) {
                    return $cell;
                }
                $clean = str_replace(["\xEF\xBB\xBF", "\xC2\xA0", "\u{FEFF}", "\u{200B}", "\u{00A0}"], ' ', $cell);
                return trim($clean);
            }, $row);

            // Skip completely empty lines
            if (count(array_filter($cleaned, fn ($val) => (string) $val !== '')) > 0) {
                $cleaned['_source_line'] = $lineNum;
                $rows[] = $cleaned;
            }
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Auto-detect the most likely delimiter (, ; \t |).
     */
    protected function detectCsvDelimiter(string $content): string
    {
        $candidates = [',', ';', "\t", '|'];
        $lines = array_filter(array_map('trim', explode("\n", $content)), fn ($l) => $l !== '');
        if (empty($lines)) {
            return ',';
        }

        $sampleLines = array_slice($lines, 0, 15);
        $bestDelim = ',';
        $bestScore = -1;

        $knownKeywords = [
            'sku', 'item_code', 'product_code', 'code', 'item', 'item_number',
            'name', 'item_name', 'product_name', 'title', 'description',
            'category', 'unit', 'unit_cost', 'cost', 'price', 'reorder_level',
            'default_location', 'location', 'storage_location',
            'supplier', 'vendor', 'email', 'phone', 'address', 'capacity', 'zone',
        ];

        foreach ($candidates as $delim) {
            $score = 0;
            $maxCols = 0;
            $validLineCount = 0;
            $colCounts = [];

            foreach ($sampleLines as $l) {
                $cols = str_getcsv($l, $delim, '"', '');
                $colCount = count($cols);
                if ($colCount >= 2) {
                    $validLineCount++;
                    $colCounts[] = $colCount;
                    if ($colCount > $maxCols) {
                        $maxCols = $colCount;
                    }
                    foreach ($cols as $c) {
                        $norm = $this->normalizeHeaderName($c);
                        if (in_array($norm, $knownKeywords, true)) {
                            $score += 15;
                        }
                    }
                }
            }

            if ($validLineCount === 0) {
                continue;
            }

            $score += ($validLineCount * 5) + ($maxCols * 2);

            if (! empty($colCounts) && count(array_unique($colCounts)) === 1) {
                $score += 25; // Consistency bonus
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestDelim = $delim;
            }
        }

        return $bestDelim;
    }

    /**
     * Parse Excel .xlsx (OpenXML) files using native PHP ZipArchive and SimpleXML
     * with XML namespace stripping and rich-text extraction.
     *
     * @return array<int, array<int, mixed>>
     */
    public function readXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('Unable to open the Excel (.xlsx) file. It may be corrupted or password-protected.');
        }

        // 1. Read shared strings table (xl/sharedStrings.xml)
        $sharedStrings = [];
        $sstXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sstXml !== false) {
            $cleanSstXml = preg_replace('/xmlns(:\w+)?="[^"]*"/', '', $sstXml);
            $sst = @simplexml_load_string($cleanSstXml);
            if ($sst && isset($sst->si)) {
                foreach ($sst->si as $si) {
                    $rawText = strip_tags($si->asXML());
                    $sharedStrings[] = trim(html_entity_decode($rawText, ENT_QUOTES | ENT_XML1, 'UTF-8'));
                }
            }
        }

        // 2. Locate the primary worksheet
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat && str_starts_with($stat['name'], 'xl/worksheets/sheet') && str_ends_with($stat['name'], '.xml')) {
                    $sheetXml = $zip->getFromIndex($i);
                    break;
                }
            }
        }

        $zip->close();

        if ($sheetXml === false) {
            throw new InvalidArgumentException('No readable worksheet found inside the Excel (.xlsx) file.');
        }

        $cleanSheetXml = preg_replace('/xmlns(:\w+)?="[^"]*"/', '', $sheetXml);
        $sheet = @simplexml_load_string($cleanSheetXml);
        if (! $sheet || ! isset($sheet->sheetData->row)) {
            return [];
        }

        $rows = [];
        foreach ($sheet->sheetData->row as $rowNode) {
            $rowCells = [];
            $nextColIdx = 0;

            foreach ($rowNode->c as $cell) {
                if (isset($cell['r'])) {
                    $coord = (string) $cell['r'];
                    $colLetter = preg_replace('/[0-9]/', '', $coord);
                    $colIdx = $this->columnLetterToIndex($colLetter);
                    $nextColIdx = $colIdx + 1;
                } else {
                    $colIdx = $nextColIdx++;
                }

                $type = (string) ($cell['t'] ?? '');
                $val = '';

                if (isset($cell->v)) {
                    $rawVal = (string) $cell->v;
                    if ($type === 's') {
                        $val = $sharedStrings[(int) $rawVal] ?? '';
                    } elseif ($type === 'b') {
                        $val = $rawVal === '1' ? 'true' : 'false';
                    } else {
                        $val = $rawVal;
                    }
                } elseif (isset($cell->is)) {
                    $val = strip_tags($cell->is->asXML());
                }

                $rowCells[$colIdx] = trim(html_entity_decode((string) $val, ENT_QUOTES | ENT_XML1, 'UTF-8'));
            }

            if (! empty($rowCells)) {
                $maxIdx = max(array_keys($rowCells));
                $fullRow = [];
                for ($c = 0; $c <= $maxIdx; $c++) {
                    $fullRow[$c] = $rowCells[$c] ?? '';
                }
                if (count(array_filter($fullRow, fn ($v) => (string) $v !== '')) > 0) {
                    $fullRow['_source_line'] = (int) ($rowNode['r'] ?? (count($rows) + 1));
                    $rows[] = $fullRow;
                }
            }
        }

        return $rows;
    }

    /**
     * Parse Excel .xls files (SpreadsheetML XML, HTML table format, or CSV).
     *
     * @return array<int, array<int, mixed>>
     */
    public function readXls(string $path): array
    {
        $content = file_get_contents($path);
        if (! $content || trim($content) === '') {
            return [];
        }

        // Case 1: SpreadsheetML (XML Spreadsheet)
        if (str_contains($content, 'urn:schemas-microsoft-com:office:spreadsheet') || str_contains($content, '<Workbook')) {
            $xml = @simplexml_load_string($content);
            if ($xml) {
                $rows = [];
                $xml->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
                $rowNodes = $xml->xpath('//ss:Row');
                $lineNum = 0;
                foreach ($rowNodes as $rowNode) {
                    $lineNum++;
                    $cells = [];
                    foreach ($rowNode->xpath('ss:Cell') as $cellNode) {
                        $data = $cellNode->xpath('ss:Data');
                        $cells[] = isset($data[0]) ? trim((string) $data[0]) : '';
                    }
                    if (count(array_filter($cells, fn ($c) => $c !== '')) > 0) {
                        $cells['_source_line'] = $lineNum;
                        $rows[] = $cells;
                    }
                }
                if (! empty($rows)) {
                    return $rows;
                }
            }
        }

        // Case 2: HTML Table (frequently exported with .xls extension)
        if (str_contains($content, '<table') || str_contains($content, '<tr')) {
            $dom = new DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
            $trNodes = $dom->getElementsByTagName('tr');
            $rows = [];
            $lineNum = 0;
            foreach ($trNodes as $tr) {
                $lineNum++;
                $cells = [];
                foreach ($tr->childNodes as $child) {
                    if ($child->nodeName === 'th' || $child->nodeName === 'td') {
                        $cells[] = trim($child->textContent);
                    }
                }
                if (count(array_filter($cells, fn ($c) => $c !== '')) > 0) {
                    $cells['_source_line'] = $lineNum;
                    $rows[] = $cells;
                }
            }
            if (! empty($rows)) {
                return $rows;
            }
        }

        // Fallback: CSV parsing
        return $this->readCsv($path);
    }

    /**
     * Convert raw array of arrays into normalized associative rows for CSV and Excel.
     * Evaluates up to 50 rows to detect the real table header row containing recognized
     * column aliases, safely bypassing institutional banners, titles, or preamble lines.
     *
     * @param  array<int, array<int, mixed>>  $rawRows
     * @param  string|null  $target  'items' | 'locations' | 'suppliers'
     * @param  string  $format
     * @return array{
     *     format: string,
     *     headers: array<string>,
     *     original_headers: array<string>,
     *     header_map: array<string, string>,
     *     header_row_number: int,
     *     rows: array<int, array<string, mixed>>,
     *     total_rows: int,
     *     raw_data_count: int
     * }
     */
    public function normalizeTable(array $rawRows, ?string $target = null, string $format = 'csv'): array
    {
        if (empty($rawRows)) {
            return [
                'format' => $format,
                'headers' => [],
                'original_headers' => [],
                'header_map' => [],
                'header_row_number' => 1,
                'rows' => [],
                'total_rows' => 0,
                'raw_data_count' => 0,
            ];
        }

        $targetKey = $target && isset(self::TARGET_ALIASES[$target]) ? $target : 'items';
        $aliases = self::TARGET_ALIASES[$targetKey];

        // 1. Determine the table header row.
        // In standard CSV / Excel files, the header row is the first non-empty row (Row 0).
        // If Row 0 has >= 2 columns and matches at least 1 known alias, or if Row 0 has 1 column matching an alias,
        // Row 0 is definitively the header row.
        // If Row 0 does NOT match any alias, we only skip it if a subsequent row within the preamble
        // has a strong tabular header signature (score >= 2).
        // CRITICAL INVARIANT: Data rows must NEVER be evaluated or chosen as headers!
        $headerRowIdx = 0;
        $rowCount = count($rawRows);

        if ($rowCount > 1) {
            $row0 = $rawRows[0];
            $row0NonEmpty = count(array_filter($row0, fn ($c, $k) => $k !== '_source_line' && trim((string) $c) !== '', ARRAY_FILTER_USE_BOTH));
            $row0Score = $this->scoreHeaderRow($row0, $aliases);

            if (($row0NonEmpty >= 2 && $row0Score >= 1) || ($row0NonEmpty === 1 && $row0Score >= 1)) {
                $headerRowIdx = 0;
            } else {
                $foundPreambleHeader = false;
                $searchLimit = min(25, $rowCount);
                for ($i = 1; $i < $searchLimit; $i++) {
                    $candidateRow = $rawRows[$i];
                    $candidateNonEmpty = count(array_filter($candidateRow, fn ($c, $k) => $k !== '_source_line' && trim((string) $c) !== '', ARRAY_FILTER_USE_BOTH));
                    if ($candidateNonEmpty < 2) {
                        continue;
                    }
                    $candidateScore = $this->scoreHeaderRow($candidateRow, $aliases);
                    if ($candidateScore >= 2) {
                        $headerRowIdx = $i;
                        $foundPreambleHeader = true;
                        break;
                    }
                }

                if (! $foundPreambleHeader) {
                    if ($row0NonEmpty >= 2) {
                        $headerRowIdx = 0;
                    } else {
                        for ($i = 1; $i < min(5, $rowCount); $i++) {
                            $candidateNonEmpty = count(array_filter($rawRows[$i], fn ($c, $k) => $k !== '_source_line' && trim((string) $c) !== '', ARRAY_FILTER_USE_BOTH));
                            if ($candidateNonEmpty >= 2) {
                                $headerRowIdx = $i;
                                break;
                            }
                        }
                    }
                }
            }
        }

        $rawHeaderRow = $rawRows[$headerRowIdx];
        $headerRowNumber = (int) ($rawHeaderRow['_source_line'] ?? ($headerRowIdx + 1));
        $dataRows = array_slice($rawRows, $headerRowIdx + 1);

        // 2. Map and normalize each header column
        $originalHeaders = [];
        $canonicalHeaders = [];
        $headerMap = []; // index => canonical field name

        foreach ($rawHeaderRow as $colIdx => $rawHeader) {
            if ($colIdx === '_source_line') {
                continue;
            }
            $rawStr = trim((string) $rawHeader);
            if ($rawStr === '') {
                continue;
            }

            $originalHeaders[$colIdx] = $rawStr;
            $normalizedName = $this->normalizeHeaderName($rawStr);
            $canonicalField = $this->resolveCanonicalField($normalizedName, $targetKey);

            $canonicalHeaders[] = $canonicalField;
            $headerMap[$colIdx] = $canonicalField;
        }

        // If 'name' is missing, but a 'description' column is present, promote 'description' to 'name'
        if ($targetKey === 'items' && ! in_array('name', $canonicalHeaders, true)) {
            $descColIdx = array_search('description', $headerMap, true);
            if ($descColIdx !== false) {
                $headerMap[$descColIdx] = 'name';
                $canonicalHeaders = array_map(fn ($f) => $f === 'description' ? 'name' : $f, $canonicalHeaders);
            }
        }

        // 3. Process data rows into associative structures
        $normalizedRows = [];
        foreach ($dataRows as $offset => $rowValues) {
            $sourceLineNumber = (int) ($rowValues['_source_line'] ?? ($headerRowIdx + 2 + $offset));

            // Skip section breaks e.g. "=== TOTALS ===", "--- SUMMARY ---"
            $firstCell = trim((string) ($rowValues[0] ?? ''));
            if (str_starts_with($firstCell, '===') || str_starts_with($firstCell, '---')) {
                continue;
            }

            $rowAssoc = [];
            $hasAnyValue = false;

            foreach ($headerMap as $colIdx => $canonicalField) {
                $val = $rowValues[$colIdx] ?? '';
                $cleanVal = is_string($val) ? trim($val) : $val;
                if ($cleanVal !== '') {
                    $hasAnyValue = true;
                }
                $rowAssoc[$canonicalField] = $cleanVal;
            }

            if ($hasAnyValue) {
                $rowAssoc['_row_number'] = $sourceLineNumber;
                $normalizedRows[] = $rowAssoc;
            }
        }

        return [
            'format' => $format,
            'headers' => array_values(array_unique($canonicalHeaders)),
            'original_headers' => array_values($originalHeaders),
            'header_map' => $headerMap,
            'header_row_number' => $headerRowNumber,
            'rows' => $normalizedRows,
            'total_rows' => count($normalizedRows),
            'raw_data_count' => count($normalizedRows),
        ];
    }

    /**
     * Normalize column header or JSON object key:
     * - Strips BOM, Unicode whitespace, non-breaking spaces
     * - Removes parenthesized notes like (Optional), (Required), (₱), [days]
     * - Replaces symbols and punctuation with underscores
     * - Converts to lowercase snake_case
     */
    public function normalizeHeaderName(mixed $header): string
    {
        $str = (string) $header;
        $str = str_replace(["\xEF\xBB\xBF", "\xC2\xA0", "\u{FEFF}", "\u{200B}", "\u{00A0}"], ' ', $str);
        $str = trim($str);

        $str = preg_replace('/\([^)]*\)/u', '', $str);
        $str = preg_replace('/\[[^\]]*\]/u', '', $str);

        $str = str_replace(['#', '/', '\\', '-', '.', '*', ':', ';', ',', '!', '?', '@', '$', '%', '^', '&', '`', '\'', '"'], ' ', $str);

        $str = preg_replace('/[^\p{L}\p{N}\s_]/u', '', $str);
        $str = trim($str);

        $str = strtolower(preg_replace('/\s+/', '_', $str));

        return trim($str, '_');
    }

    /**
     * Map a normalized header/key string to its canonical database field name.
     */
    public function resolveCanonicalField(string $normalizedHeader, string $target): string
    {
        $aliases = self::TARGET_ALIASES[$target] ?? self::TARGET_ALIASES['items'];

        foreach ($aliases as $canonicalField => $aliasList) {
            if ($normalizedHeader === $canonicalField || in_array($normalizedHeader, $aliasList, true)) {
                return $canonicalField;
            }
        }

        return $normalizedHeader;
    }

    /**
     * Score a row candidate to determine how well it represents a table header row.
     *
     * @param  array<int|string, mixed>  $row
     * @param  array<string, array<string>>  $aliases
     */
    protected function scoreHeaderRow(array $row, array $aliases): int
    {
        $score = 0;
        foreach ($row as $k => $cell) {
            if ($k === '_source_line') {
                continue;
            }
            $norm = $this->normalizeHeaderName($cell);
            if ($norm === '') {
                continue;
            }
            foreach ($aliases as $canonical => $list) {
                if ($norm === $canonical || in_array($norm, $list, true)) {
                    $score++;
                    break;
                }
            }
        }

        return $score;
    }

    /**
     * Convert Excel column letters (A, B, AA, etc.) to 0-based integer index.
     */
    protected function columnLetterToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }

        return max(0, $index - 1);
    }
}
