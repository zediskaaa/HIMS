<?php

namespace App\Services\Warehouse;

use App\Models\BarcodeAlias;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\StorageLocation;
use App\Models\WarehouseTask;
use Carbon\CarbonImmutable;

class BarcodeService
{
    private const GROUP_SEPARATOR = "\x1D";

    /**
     * @return array{raw:string,normalized:string,symbology:string,gtin:?string,batch:?string,expiry:?string,serial:?string,resolved_type:?string,resolved_id:?int,errors:array<int, string>}
     */
    public function parseAndResolve(string $raw): array
    {
        $raw = trim($raw);
        $normalized = trim(str_replace(["\r", "\n", "\t"], '', $raw));
        $symbology = 'internal';
        $gtin = $batch = $expiry = $serial = null;
        $errors = [];
        $isGs1 = false;

        if (str_starts_with($normalized, ']d2')) {
            $normalized = substr($normalized, 3);
            $symbology = 'gs1_datamatrix';
            $isGs1 = true;
        } elseif (str_starts_with($normalized, ']C1')) {
            $normalized = substr($normalized, 3);
            $symbology = 'gs1_128';
            $isGs1 = true;
        } elseif (str_starts_with($normalized, ']d1')) {
            $normalized = substr($normalized, 3);
            $symbology = 'data_matrix';
        } elseif (str_starts_with($normalized, ']C0')) {
            $normalized = substr($normalized, 3);
            $symbology = 'code_128';
        } elseif (str_contains($normalized, '(01)')) {
            $symbology = 'gs1_hri';
            $isGs1 = true;
        } elseif ($this->looksLikeGs1ElementString($normalized)) {
            $symbology = 'gs1_element_string';
            $isGs1 = true;
        }

        if ($isGs1) {
            $parsed = $this->parseGs1($normalized);
            $gtin = $parsed['gtin'];
            $batch = $parsed['batch'];
            $expiry = $parsed['expiry'];
            $serial = $parsed['serial'];
            $errors = $parsed['errors'];
        }

        $resolvedType = null;
        $resolvedId = null;

        if ($errors === []) {
            if ($gtin !== null) {
                $item = InventoryItem::query()->whereIn('gtin', $this->equivalentGtinValues($gtin))->first();
                if ($item !== null) {
                    $resolvedType = 'item';
                    $resolvedId = $item->id;
                }
            } else {
                [$resolvedType, $resolvedId, $lookupErrors] = $this->resolveDirectIdentifier($normalized, $symbology);
                $errors = [...$errors, ...$lookupErrors];

                if ($resolvedType === 'item' && $this->isValidGtin($normalized)) {
                    $gtin = str_pad($normalized, 14, '0', STR_PAD_LEFT);
                    if ($symbology === 'internal') {
                        $symbology = 'upc_ean';
                    }
                }
            }
        }

        return compact('raw', 'normalized', 'symbology', 'gtin', 'batch', 'expiry', 'serial', 'errors') + [
            'resolved_type' => $resolvedType,
            'resolved_id' => $resolvedId,
        ];
    }

    /** @return array{gtin:?string,batch:?string,expiry:?string,serial:?string,errors:array<int, string>} */
    private function parseGs1(string $value): array
    {
        $fields = [];
        $errors = [];

        if (str_contains($value, '(')) {
            preg_match_all('/\((01|10|17|21)\)([^()]*)/', $value, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                if (array_key_exists($match[1], $fields)) {
                    $errors[] = "GS1 AI ({$match[1]}) appears more than once.";

                    continue;
                }

                $fields[$match[1]] = $match[2];
            }
        } else {
            $this->parseElementString($value, $fields, $errors);
        }

        $gtin = $fields['01'] ?? null;
        if ($gtin === null) {
            $errors[] = 'A GS1 product barcode must include AI (01) GTIN.';
        } elseif (! preg_match('/^\d{14}$/', $gtin) || ! $this->isValidGtin($gtin)) {
            $errors[] = 'GS1 AI (01) must contain a valid 14-digit GTIN with a correct check digit.';
            $gtin = null;
        }

        $batch = $this->validateVariableField($fields['10'] ?? null, '10', 'batch/lot', $errors);
        $serial = $this->validateVariableField($fields['21'] ?? null, '21', 'serial number', $errors);
        $expiry = $this->parseExpiry($fields['17'] ?? null, $errors);

        return compact('gtin', 'batch', 'expiry', 'serial', 'errors');
    }

    /** @param array<string, string> $fields @param array<int, string> $errors */
    private function parseElementString(string $value, array &$fields, array &$errors): void
    {
        $offset = 0;
        $length = strlen($value);

        while ($offset < $length) {
            if ($value[$offset] === self::GROUP_SEPARATOR) {
                $offset++;

                continue;
            }

            $ai = substr($value, $offset, 2);
            if (! in_array($ai, ['01', '10', '17', '21'], true)) {
                $errors[] = "Unsupported or malformed GS1 Application Identifier at character {$offset}.";

                return;
            }
            if (array_key_exists($ai, $fields)) {
                $errors[] = "GS1 AI ({$ai}) appears more than once.";

                return;
            }

            $offset += 2;
            $fixedLength = match ($ai) {
                '01' => 14,
                '17' => 6,
                default => null,
            };

            if ($fixedLength !== null) {
                if (($length - $offset) < $fixedLength) {
                    $errors[] = "GS1 AI ({$ai}) is incomplete.";

                    return;
                }

                $fields[$ai] = substr($value, $offset, $fixedLength);
                $offset += $fixedLength;

                continue;
            }

            $separator = strpos($value, self::GROUP_SEPARATOR, $offset);
            $fieldLength = $separator === false ? $length - $offset : $separator - $offset;
            if ($fieldLength > 20) {
                $errors[] = "GS1 AI ({$ai}) exceeds its 20-character maximum or is missing an FNC1 separator.";

                return;
            }

            $fields[$ai] = substr($value, $offset, $fieldLength);
            $offset += $fieldLength;
        }
    }

    /** @param array<int, string> $errors */
    private function validateVariableField(?string $value, string $ai, string $label, array &$errors): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value === '' || strlen($value) > 20) {
            $errors[] = "GS1 AI ({$ai}) {$label} must contain 1 to 20 characters.";

            return null;
        }

        return $value;
    }

    /** @param array<int, string> $errors */
    private function parseExpiry(?string $value, array &$errors): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! preg_match('/^(\d{2})(\d{2})(\d{2})$/', $value, $matches)) {
            $errors[] = 'GS1 AI (17) expiration date must use YYMMDD format.';

            return null;
        }

        $twoDigitYear = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        $currentYear = (int) now()->format('Y');
        $year = intdiv($currentYear, 100) * 100 + $twoDigitYear;
        if ($year > $currentYear + 50) {
            $year -= 100;
        } elseif ($year < $currentYear - 49) {
            $year += 100;
        }

        if ($month < 1 || $month > 12) {
            $errors[] = 'GS1 AI (17) contains an invalid expiration month.';

            return null;
        }

        if ($day === 0) {
            return CarbonImmutable::create($year, $month, 1)->endOfMonth()->toDateString();
        }
        if (! checkdate($month, $day, $year)) {
            $errors[] = 'GS1 AI (17) contains an invalid calendar date.';

            return null;
        }

        return CarbonImmutable::create($year, $month, $day)->toDateString();
    }

    private function looksLikeGs1ElementString(string $value): bool
    {
        return preg_match('/^01\d{14}(?:17|10|21|\x1D|$)/', $value) === 1;
    }

    /** @return array{0:?string,1:?int,2:array<int, string>} */
    private function resolveDirectIdentifier(string $normalized, string &$symbology): array
    {
        $matches = [];
        $location = StorageLocation::query()->where('barcode_value', $normalized)->orWhere('code', $normalized)->first();
        if ($location !== null) {
            $matches["location:{$location->id}"] = ['location', $location->id];
        }

        $gtinValues = $this->isValidGtin($normalized) ? $this->equivalentGtinValues($normalized) : [];
        $item = InventoryItem::query()
            ->where(function ($query) use ($normalized, $gtinValues): void {
                $query->where('barcode_value', $normalized)->orWhere('sku', $normalized);
                if ($gtinValues !== []) {
                    $query->orWhereIn('gtin', $gtinValues);
                } else {
                    $query->orWhere('gtin', $normalized);
                }
            })
            ->first();
        if ($item !== null) {
            $matches["item:{$item->id}"] = ['item', $item->id];
        }

        $itemBatch = ItemBatch::query()->where('batch_number', $normalized)->orWhere('lot_number', $normalized)->first();
        if ($itemBatch !== null) {
            $matches["batch:{$itemBatch->id}"] = ['batch', $itemBatch->id];
        }

        $task = WarehouseTask::query()->where('task_number', $normalized)->first();
        if ($task !== null) {
            $matches["task:{$task->id}"] = ['task', $task->id];
        }

        $alias = BarcodeAlias::query()->where('code', $normalized)->where('is_active', true)->first();
        if ($alias !== null) {
            $aliasType = match ($alias->target_type) {
                'inventory_item' => 'item',
                'storage_location' => 'location',
                'item_batch' => 'batch',
                'warehouse_task' => 'task',
                default => $alias->target_type,
            };
            $matches["{$aliasType}:{$alias->target_id}"] = [$aliasType, (int) $alias->target_id];
            $symbology = $alias->symbology;
        }

        if (count($matches) > 1) {
            return [null, null, ['This identifier matches multiple records. Assign a unique barcode or use the item SKU.']];
        }
        if ($matches === []) {
            return [null, null, []];
        }

        [$type, $id] = array_values($matches)[0];

        return [$type, $id, []];
    }

    /** @return array<int, string> */
    private function equivalentGtinValues(string $gtin): array
    {
        if (! $this->isValidGtin($gtin)) {
            return [$gtin];
        }

        $canonical = str_pad($gtin, 14, '0', STR_PAD_LEFT);
        $values = [$canonical];
        foreach ([13, 12, 8] as $length) {
            $candidate = substr($canonical, -$length);
            if (str_pad($candidate, 14, '0', STR_PAD_LEFT) === $canonical && $this->isValidGtin($candidate)) {
                $values[] = $candidate;
            }
        }

        return array_values(array_unique($values));
    }

    private function isValidGtin(string $gtin): bool
    {
        if (! preg_match('/^\d{8}$|^\d{12}$|^\d{13}$|^\d{14}$/', $gtin)) {
            return false;
        }

        $digits = str_split($gtin);
        $checkDigit = (int) array_pop($digits);
        $sum = 0;
        $weight = 3;

        for ($index = count($digits) - 1; $index >= 0; $index--) {
            $sum += (int) $digits[$index] * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return (10 - ($sum % 10)) % 10 === $checkDigit;
    }
}
