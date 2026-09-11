<?php

namespace App\Services\Import;

use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\StorageLocation;
use App\Models\Supplier;
use InvalidArgumentException;

class DataImportValidator
{
    /**
     * Required canonical headers per module.
     *
     * @var array<string, array<string>>
     */
    public const REQUIRED_HEADERS = [
        'items' => ['sku', 'name'],
        'locations' => ['code', 'name'],
        'suppliers' => ['name'],
    ];

    /**
     * Human-friendly field labels.
     *
     * @var array<string, string>
     */
    public const FIELD_LABELS = [
        'sku' => 'SKU / Item Code',
        'name' => 'Name',
        'code' => 'Code',
        'category' => 'Category',
        'unit' => 'Unit of Measure',
        'unit_cost' => 'Unit Cost',
        'reorder_level' => 'Reorder Level',
        'safety_stock' => 'Safety Stock',
        'critical_level' => 'Critical Level',
        'expiry_alert_days' => 'Expiry Alert Days',
        'default_location' => 'Default Storage Location',
        'capacity' => 'Capacity',
        'email' => 'Email Address',
        'phone' => 'Phone Number',
        'tax_id' => 'Tax Identification Number (TIN)',
    ];

    /**
     * Validate table rows against schema for the given target module.
     *
     * @param  string  $target  'items' | 'locations' | 'suppliers'
     * @param  array<string, mixed>  $tableData
     * @param  string  $mode  'create_only' | 'update_or_create'
     * @return array<string, mixed>
     */
    public function validate(string $target, array $tableData, string $mode = 'create_only'): array
    {
        return match ($target) {
            'items' => $this->validateItems($tableData, $mode),
            'locations' => $this->validateLocations($tableData, $mode),
            'suppliers' => $this->validateSuppliers($tableData, $mode),
            default => throw new InvalidArgumentException("Unsupported import target [{$target}]."),
        };
    }

    /**
     * Validate Inventory Items table.
     *
     * @param  array<string, mixed>  $tableData
     * @param  string  $mode
     * @return array<string, mixed>
     */
    protected function validateItems(array $tableData, string $mode): array
    {
        $headers = $tableData['headers'] ?? [];
        $rows = $tableData['rows'] ?? [];
        $originalHeaders = $tableData['original_headers'] ?? [];
        $headerRow = $tableData['header_row_number'] ?? 1;

        // 1. File Structure Check: Ensure file is not empty
        if (empty($rows) && empty($headers)) {
            return $this->buildStructureError(
                'The uploaded file is empty or contains no readable data rows.',
                '[Empty File]'
            );
        }

        // 2. Missing Required Headers Check
        $requiredHeaders = self::REQUIRED_HEADERS['items'];
        $missingHeaders = array_diff($requiredHeaders, $headers);

        if (! empty($missingHeaders)) {
            $aliasHints = [
                'sku' => 'sku, item_code, product_code, code',
                'name' => 'name, item_name, product_name, title',
            ];
            $hints = array_map(fn ($f) => "{$f} (e.g. {$aliasHints[$f]})", $missingHeaders);

            $format = $tableData['format'] ?? 'csv';
            $term = $format === 'json' ? 'fields / keys' : 'column headers';

            return [
                'is_valid' => false,
                'total_rows' => count($rows),
                'valid_count' => 0,
                'invalid_count' => count($rows),
                'create_count' => 0,
                'update_count' => 0,
                'errors' => [
                    [
                        'row' => $headerRow,
                        'field' => implode(', ', $missingHeaders),
                        'value' => ! empty($originalHeaders) ? 'Detected: [' . implode(', ', array_slice($originalHeaders, 0, 8)) . ']' : '[None]',
                        'type' => 'missing_header',
                        'message' => "Missing required {$term}: " . implode(', ', $missingHeaders) . '. Required headers are: ' . implode(', ', $hints) . '.',
                    ],
                ],
                'warnings' => [],
                'preview_rows' => [],
                'validated_payload' => [],
            ];
        }

        // 3. Check for empty data rows after headers
        if (empty($rows)) {
            return $this->buildStructureError(
                'The uploaded file contains column headers but no data rows to import.',
                '[No Data Rows]'
            );
        }

        // Cache lookups to prevent N+1 queries during validation
        $existingItems = InventoryItem::query()
            ->select('id', 'sku', 'name')
            ->get()
            ->keyBy(fn ($item) => strtolower(trim((string) $item->sku)));

        $categoriesByName = ItemCategory::all()->keyBy(fn ($c) => strtolower(trim($c->name)));
        $categoriesByCode = ItemCategory::all()->keyBy(fn ($c) => strtolower(trim((string) $c->code)));
        $categoriesById = ItemCategory::all()->keyBy('id');

        $locationsByCode = StorageLocation::all()->keyBy(fn ($l) => strtolower(trim((string) $l->code)));
        $locationsByName = StorageLocation::all()->keyBy(fn ($l) => strtolower(trim($l->name)));
        $locationsById = StorageLocation::all()->keyBy('id');

        $seenSkusInFile = [];
        $errors = [];
        $previewRows = [];
        $validatedRecords = [];
        $createCount = 0;
        $updateCount = 0;

        foreach ($rows as $row) {
            $rowNum = $row['_row_number'] ?? 0;
            $rowErrors = [];

            // 1. Validate SKU (Required)
            $rawSku = isset($row['sku']) ? trim((string) $row['sku']) : '';
            if ($rawSku === '') {
                $rowErrors[] = [
                    'row' => $rowNum,
                    'field' => 'sku',
                    'value' => '[Empty]',
                    'type' => 'empty_required',
                    'message' => "Empty value in required field 'sku'. Row {$rowNum} must provide a valid SKU or Item Code.",
                ];
            } elseif (strlen($rawSku) > 100) {
                $rowErrors[] = [
                    'row' => $rowNum,
                    'field' => 'sku',
                    'value' => $rawSku,
                    'type' => 'invalid_value',
                    'message' => "SKU '{$rawSku}' on row {$rowNum} exceeds maximum length of 100 characters.",
                ];
            } else {
                $skuNormalized = strtolower($rawSku);

                // In-file duplicate check
                if (isset($seenSkusInFile[$skuNormalized])) {
                    $rowErrors[] = [
                        'row' => $rowNum,
                        'field' => 'sku',
                        'value' => $rawSku,
                        'type' => 'duplicate_record',
                        'message' => "Duplicate SKU '{$rawSku}' found on row {$rowNum} (already present on row {$seenSkusInFile[$skuNormalized]}).",
                    ];
                } else {
                    $seenSkusInFile[$skuNormalized] = $rowNum;
                }

                // Database collision check based on mode
                $isExisting = isset($existingItems[$skuNormalized]);
                if ($isExisting && $mode === 'create_only') {
                    $rowErrors[] = [
                        'row' => $rowNum,
                        'field' => 'sku',
                        'value' => $rawSku,
                        'type' => 'duplicate_record',
                        'message' => "SKU '{$rawSku}' already exists in the database. Choose 'Update Existing & Create New' mode to modify existing items.",
                    ];
                }
            }

            // 2. Validate Name (Required)
            $rawName = isset($row['name']) ? trim((string) $row['name']) : '';
            if ($rawName === '') {
                $rowErrors[] = [
                    'row' => $rowNum,
                    'field' => 'name',
                    'value' => '[Empty]',
                    'type' => 'empty_required',
                    'message' => "Empty value in required field 'name'. Row {$rowNum} must provide an item name.",
                ];
            } elseif (strlen($rawName) > 255) {
                $rowErrors[] = [
                    'row' => $rowNum,
                    'field' => 'name',
                    'value' => substr($rawName, 0, 30) . '...',
                    'type' => 'invalid_value',
                    'message' => "Item name on row {$rowNum} exceeds maximum length of 255 characters.",
                ];
            }

            // 3. Validate Category (Optional, referential check)
            $rawCat = trim((string) ($row['category'] ?? ''));
            $resolvedCategoryId = null;
            if ($rawCat !== '') {
                $catLower = strtolower($rawCat);
                $cat = $categoriesByName[$catLower] ?? $categoriesByCode[$catLower] ?? (is_numeric($rawCat) ? ($categoriesById[(int) $rawCat] ?? null) : null);
                if ($cat) {
                    $resolvedCategoryId = $cat->id;
                } else {
                    $rowErrors[] = [
                        'row' => $rowNum,
                        'field' => 'category',
                        'value' => $rawCat,
                        'type' => 'referential_integrity',
                        'message' => "Category '{$rawCat}' on row {$rowNum} was not found in the master category catalogue.",
                    ];
                }
            }

            // 4. Validate Storage Location (Optional, referential check)
            $rawLoc = trim((string) ($row['default_location'] ?? ''));
            $resolvedLocationId = null;
            if ($rawLoc !== '') {
                $locLower = strtolower($rawLoc);
                $loc = $locationsByCode[$locLower] ?? $locationsByName[$locLower] ?? (is_numeric($rawLoc) ? ($locationsById[(int) $rawLoc] ?? null) : null);
                if ($loc) {
                    $resolvedLocationId = $loc->id;
                } else {
                    $rowErrors[] = [
                        'row' => $rowNum,
                        'field' => 'default_location',
                        'value' => $rawLoc,
                        'type' => 'referential_integrity',
                        'message' => "Storage location '{$rawLoc}' on row {$rowNum} was not found in active locations.",
                    ];
                }
            }

            // 5. Validate Unit Cost
            $rawCost = isset($row['unit_cost']) ? trim((string) $row['unit_cost']) : '';
            $unitCost = 0.0;
            if ($rawCost !== '') {
                $cleanCost = preg_replace('/[₱$,\s]/u', '', $rawCost);
                if (! is_numeric($cleanCost)) {
                    $rowErrors[] = [
                        'row' => $rowNum,
                        'field' => 'unit_cost',
                        'value' => $rawCost,
                        'type' => 'invalid_value',
                        'message' => "Unit cost on row {$rowNum} must be a valid number. Uploaded: '{$rawCost}'.",
                    ];
                } elseif ((float) $cleanCost < 0) {
                    $rowErrors[] = [
                        'row' => $rowNum,
                        'field' => 'unit_cost',
                        'value' => $rawCost,
                        'type' => 'invalid_value',
                        'message' => "Unit cost on row {$rowNum} cannot be negative. Uploaded: '{$rawCost}'.",
                    ];
                } else {
                    $unitCost = round((float) $cleanCost, 4);
                }
            }

            // 6. Validate Reorder Level
            $rawReorder = isset($row['reorder_level']) ? trim((string) $row['reorder_level']) : '';
            $reorderLevel = 0;
            if ($rawReorder !== '') {
                if (! ctype_digit($rawReorder) && (! is_numeric($rawReorder) || (int) $rawReorder < 0 || (float) $rawReorder != (int) $rawReorder)) {
                    $rowErrors[] = [
                        'row' => $rowNum,
                        'field' => 'reorder_level',
                        'value' => $rawReorder,
                        'type' => 'invalid_value',
                        'message' => "Reorder level on row {$rowNum} must be a non-negative integer (0 or greater). Uploaded: '{$rawReorder}'.",
                    ];
                } else {
                    $reorderLevel = max(0, (int) $rawReorder);
                }
            }

            // 7. Validate Safety Stock
            $rawSafety = isset($row['safety_stock']) ? trim((string) $row['safety_stock']) : '';
            $safetyStock = 0;
            if ($rawSafety !== '') {
                if (! ctype_digit($rawSafety) && (! is_numeric($rawSafety) || (int) $rawSafety < 0)) {
                    $rowErrors[] = [
                        'row' => $rowNum,
                        'field' => 'safety_stock',
                        'value' => $rawSafety,
                        'type' => 'invalid_value',
                        'message' => "Safety stock on row {$rowNum} must be a non-negative integer. Uploaded: '{$rawSafety}'.",
                    ];
                } else {
                    $safetyStock = max(0, (int) $rawSafety);
                }
            }

            // 8. Validate Critical Level
            $rawCritical = isset($row['critical_level']) ? trim((string) $row['critical_level']) : '';
            $criticalLevel = 0;
            if ($rawCritical !== '') {
                if (! ctype_digit($rawCritical) && (! is_numeric($rawCritical) || (int) $rawCritical < 0)) {
                    $rowErrors[] = [
                        'row' => $rowNum,
                        'field' => 'critical_level',
                        'value' => $rawCritical,
                        'type' => 'invalid_value',
                        'message' => "Critical level on row {$rowNum} must be a non-negative integer. Uploaded: '{$rawCritical}'.",
                    ];
                } else {
                    $criticalLevel = max(0, (int) $rawCritical);
                }
            }

            // 9. Validate Expiry Alert Days
            $rawExpiry = isset($row['expiry_alert_days']) ? trim((string) $row['expiry_alert_days']) : '';
            $expiryDays = 30;
            if ($rawExpiry !== '') {
                if (! is_numeric($rawExpiry) || (int) $rawExpiry <= 0 || (int) $rawExpiry > 3650) {
                    $rowErrors[] = [
                        'row' => $rowNum,
                        'field' => 'expiry_alert_days',
                        'value' => $rawExpiry,
                        'type' => 'invalid_value',
                        'message' => "Expiry alert days on row {$rowNum} must be an integer between 1 and 3650. Uploaded: '{$rawExpiry}'.",
                    ];
                } else {
                    $expiryDays = (int) $rawExpiry;
                }
            }

            $unit = trim((string) ($row['unit'] ?? 'unit'));
            if ($unit === '') {
                $unit = 'unit';
            }

            $requiresColdChain = $this->parseBoolean($row['requires_cold_chain'] ?? false);
            $isDangerousDrug = $this->parseBoolean($row['is_dangerous_drug'] ?? false);

            $status = strtolower(trim((string) ($row['status'] ?? 'in_stock')));
            if (! in_array($status, ['in_stock', 'low_stock', 'out_of_stock'], true)) {
                $status = 'in_stock';
            }

            $skuNormalized = strtolower($rawSku);
            $isExisting = $rawSku !== '' && isset($existingItems[$skuNormalized]);

            $rowValid = empty($rowErrors);
            if (! $rowValid) {
                $errors = array_merge($errors, $rowErrors);
            } else {
                if ($isExisting) {
                    $updateCount++;
                } else {
                    $createCount++;
                }

                $validatedRecords[] = [
                    'sku' => $rawSku,
                    'name' => $rawName,
                    'description' => trim((string) ($row['description'] ?? '')),
                    'category_id' => $resolvedCategoryId,
                    'default_location_id' => $resolvedLocationId,
                    'unit' => $unit,
                    'unit_cost' => $unitCost,
                    'reorder_level' => $reorderLevel,
                    'safety_stock' => $safetyStock,
                    'critical_level' => $criticalLevel,
                    'expiry_alert_days' => $expiryDays,
                    'requires_cold_chain' => $requiresColdChain,
                    'is_dangerous_drug' => $isDangerousDrug,
                    'status' => $status,
                    '_mode' => $isExisting ? 'update' : 'create',
                    '_existing_id' => $isExisting ? $existingItems[$skuNormalized]->id : null,
                ];
            }

            // Preview row for table UI
            if (count($previewRows) < 25) {
                $previewRows[] = [
                    'row' => $rowNum,
                    'sku' => $rawSku ?: '-',
                    'name' => $rawName ?: '-',
                    'category' => $rawCat ?: 'None',
                    'location' => $rawLoc ?: 'None',
                    'unit_cost' => '₱' . number_format($unitCost, 2),
                    'reorder_level' => $reorderLevel,
                    'status' => $rowValid ? ($isExisting ? 'update' : 'valid') : 'invalid',
                    'errors' => array_column($rowErrors, 'message'),
                ];
            }
        }

        $totalRows = count($rows);
        $invalidCount = count(array_unique(array_column($errors, 'row')));
        $validCount = $totalRows - $invalidCount;

        return [
            'is_valid' => empty($errors),
            'total_rows' => $totalRows,
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount,
            'create_count' => $createCount,
            'update_count' => $updateCount,
            'errors' => $errors,
            'warnings' => [],
            'preview_rows' => $previewRows,
            'validated_payload' => empty($errors) ? $validatedRecords : [],
        ];
    }

    /**
     * Validate Storage Locations table.
     *
     * @param  array<string, mixed>  $tableData
     * @param  string  $mode
     * @return array<string, mixed>
     */
    protected function validateLocations(array $tableData, string $mode): array
    {
        $headers = $tableData['headers'] ?? [];
        $rows = $tableData['rows'] ?? [];
        $originalHeaders = $tableData['original_headers'] ?? [];
        $headerRow = $tableData['header_row_number'] ?? 1;

        if (empty($rows) && empty($headers)) {
            return $this->buildStructureError(
                'The uploaded file is empty or contains no readable data rows.',
                '[Empty File]'
            );
        }

        $requiredHeaders = self::REQUIRED_HEADERS['locations'];
        $missingHeaders = array_diff($requiredHeaders, $headers);
        if (! empty($missingHeaders)) {
            $aliasHints = [
                'code' => 'code, location_code, storage_code',
                'name' => 'name, location_name, storage_name',
            ];
            $hints = array_map(fn ($f) => "{$f} (e.g. {$aliasHints[$f]})", $missingHeaders);

            $format = $tableData['format'] ?? 'csv';
            $term = $format === 'json' ? 'fields / keys' : 'column headers';

            return [
                'is_valid' => false,
                'total_rows' => count($rows),
                'valid_count' => 0,
                'invalid_count' => count($rows),
                'create_count' => 0,
                'update_count' => 0,
                'errors' => [
                    [
                        'row' => $headerRow,
                        'field' => implode(', ', $missingHeaders),
                        'value' => ! empty($originalHeaders) ? 'Detected: [' . implode(', ', array_slice($originalHeaders, 0, 8)) . ']' : '[None]',
                        'type' => 'missing_header',
                        'message' => "Missing required {$term}: " . implode(', ', $missingHeaders) . '. Required headers are: ' . implode(', ', $hints) . '.',
                    ],
                ],
                'warnings' => [],
                'preview_rows' => [],
                'validated_payload' => [],
            ];
        }

        if (empty($rows)) {
            return $this->buildStructureError(
                'The uploaded file contains column headers but no data rows to import.',
                '[No Data Rows]'
            );
        }

        $existingLocations = StorageLocation::all()->keyBy(fn ($l) => strtolower(trim((string) $l->code)));
        $seenCodesInFile = [];
        $previewRows = [];
        $errors = [];
        $validatedRecords = [];
        $createCount = 0;
        $updateCount = 0;

        foreach ($rows as $row) {
            $rowNum = $row['_row_number'] ?? 0;
            $rowErrors = [];

            // 1. Code (Required)
            $rawCode = isset($row['code']) ? trim((string) $row['code']) : '';
            if ($rawCode === '') {
                $rowErrors[] = [
                    'row' => $rowNum,
                    'field' => 'code',
                    'value' => '[Empty]',
                    'type' => 'empty_required',
                    'message' => "Empty value in required field 'code'. Row {$rowNum} must provide a location code.",
                ];
            } elseif (strlen($rawCode) > 50) {
                $rowErrors[] = [
                    'row' => $rowNum,
                    'field' => 'code',
                    'value' => $rawCode,
                    'type' => 'invalid_value',
                    'message' => "Location code '{$rawCode}' on row {$rowNum} exceeds maximum length of 50 characters.",
                ];
            } else {
                $codeNormalized = strtolower($rawCode);
                if (isset($seenCodesInFile[$codeNormalized])) {
                    $rowErrors[] = [
                        'row' => $rowNum,
                        'field' => 'code',
                        'value' => $rawCode,
                        'type' => 'duplicate_record',
                        'message' => "Duplicate code '{$rawCode}' found on row {$rowNum} (already on row {$seenCodesInFile[$codeNormalized]}).",
                    ];
                } else {
                    $seenCodesInFile[$codeNormalized] = $rowNum;
                }

                $isExisting = isset($existingLocations[$codeNormalized]);
                if ($isExisting && $mode === 'create_only') {
                    $rowErrors[] = [
                        'row' => $rowNum,
                        'field' => 'code',
                        'value' => $rawCode,
                        'type' => 'duplicate_record',
                        'message' => "Location code '{$rawCode}' already exists in the database. Use 'Update Existing & Create New' mode to modify.",
                    ];
                }
            }

            // 2. Name (Required)
            $rawName = isset($row['name']) ? trim((string) $row['name']) : '';
            if ($rawName === '') {
                $rowErrors[] = [
                    'row' => $rowNum,
                    'field' => 'name',
                    'value' => '[Empty]',
                    'type' => 'empty_required',
                    'message' => "Empty value in required field 'name'. Row {$rowNum} must provide a location name.",
                ];
            } elseif (strlen($rawName) > 255) {
                $rowErrors[] = [
                    'row' => $rowNum,
                    'field' => 'name',
                    'value' => substr($rawName, 0, 30) . '...',
                    'type' => 'invalid_value',
                    'message' => "Location name on row {$rowNum} exceeds maximum length of 255 characters.",
                ];
            }

            // 3. Capacity
            $rawCapacity = isset($row['capacity']) ? trim((string) $row['capacity']) : '';
            $capacity = null;
            if ($rawCapacity !== '') {
                if (! is_numeric($rawCapacity) || (int) $rawCapacity < 0) {
                    $rowErrors[] = [
                        'row' => $rowNum,
                        'field' => 'capacity',
                        'value' => $rawCapacity,
                        'type' => 'invalid_value',
                        'message' => "Capacity on row {$rowNum} must be a non-negative integer. Uploaded: '{$rawCapacity}'.",
                    ];
                } else {
                    $capacity = (int) $rawCapacity;
                }
            }

            $type = trim((string) ($row['type'] ?? 'room'));
            $zone = trim((string) ($row['zone'] ?? 'General'));
            $status = strtolower(trim((string) ($row['status'] ?? 'active'))) === 'inactive' ? 'inactive' : 'active';

            $codeNormalized = strtolower($rawCode);
            $isExisting = $rawCode !== '' && isset($existingLocations[$codeNormalized]);

            $rowValid = empty($rowErrors);
            if (! $rowValid) {
                $errors = array_merge($errors, $rowErrors);
            } else {
                if ($isExisting) {
                    $updateCount++;
                } else {
                    $createCount++;
                }

                $validatedRecords[] = [
                    'code' => $rawCode,
                    'name' => $rawName,
                    'type' => $type ?: 'room',
                    'zone' => $zone ?: 'General',
                    'capacity' => $capacity,
                    'storage_classification' => trim((string) ($row['storage_classification'] ?? 'ambient')),
                    'temperature_classification' => trim((string) ($row['temperature_classification'] ?? 'ambient')),
                    'status' => $status,
                    '_mode' => $isExisting ? 'update' : 'create',
                    '_existing_id' => $isExisting ? $existingLocations[$codeNormalized]->id : null,
                ];
            }

            if (count($previewRows) < 25) {
                $previewRows[] = [
                    'row' => $rowNum,
                    'code' => $rawCode ?: '-',
                    'name' => $rawName ?: '-',
                    'type' => $type,
                    'zone' => $zone,
                    'capacity' => $capacity !== null ? number_format($capacity) : 'Uncapped',
                    'status' => $rowValid ? ($isExisting ? 'update' : 'valid') : 'invalid',
                    'errors' => array_column($rowErrors, 'message'),
                ];
            }
        }

        $totalRows = count($rows);
        $invalidCount = count(array_unique(array_column($errors, 'row')));
        $validCount = $totalRows - $invalidCount;

        return [
            'is_valid' => empty($errors),
            'total_rows' => $totalRows,
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount,
            'create_count' => $createCount,
            'update_count' => $updateCount,
            'errors' => $errors,
            'warnings' => [],
            'preview_rows' => $previewRows,
            'validated_payload' => empty($errors) ? $validatedRecords : [],
        ];
    }

    /**
     * Validate Suppliers table.
     *
     * @param  array<string, mixed>  $tableData
     * @param  string  $mode
     * @return array<string, mixed>
     */
    protected function validateSuppliers(array $tableData, string $mode): array
    {
        $headers = $tableData['headers'] ?? [];
        $rows = $tableData['rows'] ?? [];
        $originalHeaders = $tableData['original_headers'] ?? [];
        $headerRow = $tableData['header_row_number'] ?? 1;

        if (empty($rows) && empty($headers)) {
            return $this->buildStructureError(
                'The uploaded file is empty or contains no readable data rows.',
                '[Empty File]'
            );
        }

        $requiredHeaders = self::REQUIRED_HEADERS['suppliers'];
        $missingHeaders = array_diff($requiredHeaders, $headers);
        if (! empty($missingHeaders)) {
            $hints = ['name (e.g. name, supplier_name, vendor_name, company_name)'];

            $format = $tableData['format'] ?? 'csv';
            $term = $format === 'json' ? 'field / key' : 'column header';

            return [
                'is_valid' => false,
                'total_rows' => count($rows),
                'valid_count' => 0,
                'invalid_count' => count($rows),
                'create_count' => 0,
                'update_count' => 0,
                'errors' => [
                    [
                        'row' => $headerRow,
                        'field' => 'name',
                        'value' => ! empty($originalHeaders) ? 'Detected: [' . implode(', ', array_slice($originalHeaders, 0, 8)) . ']' : '[None]',
                        'type' => 'missing_header',
                        'message' => "Missing required {$term}: name. Required header: " . implode(', ', $hints) . '.',
                    ],
                ],
                'warnings' => [],
                'preview_rows' => [],
                'validated_payload' => [],
            ];
        }

        if (empty($rows)) {
            return $this->buildStructureError(
                'The uploaded file contains column headers but no data rows to import.',
                '[No Data Rows]'
            );
        }

        $existingSuppliersByName = Supplier::all()->keyBy(fn ($s) => strtolower(trim($s->name)));
        $seenNamesInFile = [];
        $previewRows = [];
        $errors = [];
        $validatedRecords = [];
        $createCount = 0;
        $updateCount = 0;

        foreach ($rows as $row) {
            $rowNum = $row['_row_number'] ?? 0;
            $rowErrors = [];

            // 1. Name (Required)
            $rawName = isset($row['name']) ? trim((string) $row['name']) : '';
            if ($rawName === '') {
                $rowErrors[] = [
                    'row' => $rowNum,
                    'field' => 'name',
                    'value' => '[Empty]',
                    'type' => 'empty_required',
                    'message' => "Empty value in required field 'name'. Row {$rowNum} must provide a supplier or company name.",
                ];
            } elseif (strlen($rawName) > 255) {
                $rowErrors[] = [
                    'row' => $rowNum,
                    'field' => 'name',
                    'value' => substr($rawName, 0, 30) . '...',
                    'type' => 'invalid_value',
                    'message' => "Supplier name on row {$rowNum} exceeds maximum length of 255 characters.",
                ];
            } else {
                $nameNormalized = strtolower($rawName);
                if (isset($seenNamesInFile[$nameNormalized])) {
                    $rowErrors[] = [
                        'row' => $rowNum,
                        'field' => 'name',
                        'value' => $rawName,
                        'type' => 'duplicate_record',
                        'message' => "Duplicate supplier '{$rawName}' found on row {$rowNum} (already on row {$seenNamesInFile[$nameNormalized]}).",
                    ];
                } else {
                    $seenNamesInFile[$nameNormalized] = $rowNum;
                }

                $isExisting = isset($existingSuppliersByName[$nameNormalized]);
                if ($isExisting && $mode === 'create_only') {
                    $rowErrors[] = [
                        'row' => $rowNum,
                        'field' => 'name',
                        'value' => $rawName,
                        'type' => 'duplicate_record',
                        'message' => "Supplier '{$rawName}' already exists in the system. Use 'Update Existing & Create New' mode to update.",
                    ];
                }
            }

            // 2. Email validation
            $email = trim((string) ($row['email'] ?? ''));
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = [
                    'row' => $rowNum,
                    'field' => 'email',
                    'value' => $email,
                    'type' => 'invalid_value',
                    'message' => "Invalid email address format on row {$rowNum}: '{$email}'.",
                ];
            }

            $leadDays = isset($row['standard_lead_time_days']) && trim((string) $row['standard_lead_time_days']) !== ''
                ? (int) $row['standard_lead_time_days']
                : 7;

            $nameNormalized = strtolower($rawName);
            $isExisting = $rawName !== '' && isset($existingSuppliersByName[$nameNormalized]);

            $rowValid = empty($rowErrors);
            if (! $rowValid) {
                $errors = array_merge($errors, $rowErrors);
            } else {
                if ($isExisting) {
                    $updateCount++;
                } else {
                    $createCount++;
                }

                $validatedRecords[] = [
                    'name' => $rawName,
                    'contact_person' => trim((string) ($row['contact_person'] ?? '')),
                    'email' => $email ?: null,
                    'phone' => trim((string) ($row['phone'] ?? '')) ?: null,
                    'address' => trim((string) ($row['address'] ?? '')) ?: null,
                    'tax_number' => trim((string) ($row['tax_id'] ?? $row['tax_number'] ?? $row['tin'] ?? '')) ?: null,
                    'payment_terms' => trim((string) ($row['payment_terms'] ?? '30 Days Net')),
                    'standard_lead_time_days' => max(1, $leadDays),
                    'status' => 'active',
                    '_mode' => $isExisting ? 'update' : 'create',
                    '_existing_id' => $isExisting ? $existingSuppliersByName[$nameNormalized]->id : null,
                ];
            }

            if (count($previewRows) < 25) {
                $previewRows[] = [
                    'row' => $rowNum,
                    'name' => $rawName ?: '-',
                    'contact_person' => $row['contact_person'] ?? '-',
                    'email' => $email ?: '-',
                    'phone' => $row['phone'] ?? '-',
                    'status' => $rowValid ? ($isExisting ? 'update' : 'valid') : 'invalid',
                    'errors' => array_column($rowErrors, 'message'),
                ];
            }
        }

        $totalRows = count($rows);
        $invalidCount = count(array_unique(array_column($errors, 'row')));
        $validCount = $totalRows - $invalidCount;

        return [
            'is_valid' => empty($errors),
            'total_rows' => $totalRows,
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount,
            'create_count' => $createCount,
            'update_count' => $updateCount,
            'errors' => $errors,
            'warnings' => [],
            'preview_rows' => $previewRows,
            'validated_payload' => empty($errors) ? $validatedRecords : [],
        ];
    }

    /**
     * Build an invalid structure response.
     *
     * @return array<string, mixed>
     */
    protected function buildStructureError(string $message, string $value): array
    {
        return [
            'is_valid' => false,
            'total_rows' => 0,
            'valid_count' => 0,
            'invalid_count' => 1,
            'create_count' => 0,
            'update_count' => 0,
            'errors' => [
                [
                    'row' => 1,
                    'field' => 'file',
                    'value' => $value,
                    'type' => 'invalid_structure',
                    'message' => $message,
                ],
            ],
            'warnings' => [],
            'preview_rows' => [],
            'validated_payload' => [],
        ];
    }

    /**
     * Parse boolean values from varied text representations.
     */
    protected function parseBoolean(mixed $val): bool
    {
        if (is_bool($val)) {
            return $val;
        }
        $str = strtolower(trim((string) $val));

        return in_array($str, ['1', 'true', 'yes', 'y'], true);
    }
}
