<?php

namespace App\Services\Warehouse;

use App\Models\BarcodeAlias;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\StorageLocation;
use App\Models\WarehouseTask;

class BarcodeService
{
    /** @return array{raw:string, normalized:string, symbology:string, gtin:?string, batch:?string, expiry:?string, serial:?string, resolved_type:?string, resolved_id:?int} */
    public function parseAndResolve(string $raw): array
    {
        $normalized = trim(str_replace(["\r", "\n", "\t"], '', $raw));
        $symbology = 'internal';
        $gtin = $batch = $expiry = $serial = null;

        if (str_starts_with($normalized, ']d2')) {
            $normalized = substr($normalized, 3);
            $symbology = 'gs1_datamatrix';
        } elseif (str_contains($normalized, '(01)')) {
            $symbology = 'gs1_hri';
        }

        if (in_array($symbology, ['gs1_datamatrix', 'gs1_hri'], true) || str_starts_with($normalized, '01')) {
            $parsed = $this->parseGs1($normalized);
            $gtin = $parsed['gtin'];
            $batch = $parsed['batch'];
            $expiry = $parsed['expiry'];
            $serial = $parsed['serial'];
            $symbology = $symbology === 'internal' ? 'gs1_element_string' : $symbology;
        }

        $resolvedType = null;
        $resolvedId = null;

        $item = $gtin ? InventoryItem::query()->where('gtin', $gtin)->first() : null;
        if ($item) {
            $resolvedType = 'item';
            $resolvedId = $item->id;
        } else {
            $location = StorageLocation::query()
                ->where('barcode_value', $normalized)
                ->orWhere('code', $normalized)
                ->first();
            $item = InventoryItem::query()
                ->where('barcode_value', $normalized)
                ->orWhere('sku', $normalized)
                ->orWhere('gtin', $normalized)
                ->first();
            $itemBatch = ItemBatch::query()
                ->where('batch_number', $normalized)
                ->orWhere('lot_number', $normalized)
                ->first();
            $task = WarehouseTask::query()->where('task_number', $normalized)->first();
            $alias = BarcodeAlias::query()->where('code', $normalized)->where('is_active', true)->first();

            foreach ([['location', $location], ['item', $item], ['batch', $itemBatch], ['task', $task]] as [$type, $model]) {
                if ($model) {
                    $resolvedType = $type;
                    $resolvedId = $model->id;
                    break;
                }
            }

            if ($resolvedType === null && $alias) {
                $resolvedType = $alias->target_type;
                $resolvedId = (int) $alias->target_id;
                $symbology = $alias->symbology;
            }
        }

        return compact('raw', 'normalized', 'symbology', 'gtin', 'batch', 'expiry', 'serial') + [
            'resolved_type' => $resolvedType,
            'resolved_id' => $resolvedId,
        ];
    }

    /** @return array{gtin:?string,batch:?string,expiry:?string,serial:?string} */
    private function parseGs1(string $value): array
    {
        $fields = [];

        if (str_contains($value, '(')) {
            preg_match_all('/\((01|10|17|21)\)([^()]*)/', $value, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $fields[$match[1]] = $match[2];
            }
        } else {
            $segments = explode(chr(29), $value);
            $head = array_shift($segments) ?? '';
            if (str_starts_with($head, '01') && strlen($head) >= 16) {
                $fields['01'] = substr($head, 2, 14);
                $head = substr($head, 16);
            }
            while ($head !== '') {
                $ai = substr($head, 0, 2);
                if ($ai === '17' && strlen($head) >= 8) {
                    $fields['17'] = substr($head, 2, 6);
                    $head = substr($head, 8);
                    continue;
                }
                if (in_array($ai, ['10', '21'], true)) {
                    $fields[$ai] = substr($head, 2);
                }
                break;
            }
            foreach ($segments as $segment) {
                $ai = substr($segment, 0, 2);
                if (in_array($ai, ['10', '21'], true)) {
                    $fields[$ai] = substr($segment, 2);
                }
            }
        }

        $gtin = $fields['01'] ?? null;
        if ($gtin !== null && ! $this->hasValidGtinCheckDigit($gtin)) {
            $gtin = null;
        }

        $expiry = null;
        if (isset($fields['17']) && preg_match('/^\d{6}$/', $fields['17'])) {
            $expiry = '20'.substr($fields['17'], 0, 2).'-'.substr($fields['17'], 2, 2).'-'.substr($fields['17'], 4, 2);
        }

        return ['gtin' => $gtin, 'batch' => $fields['10'] ?? null, 'expiry' => $expiry, 'serial' => $fields['21'] ?? null];
    }

    private function hasValidGtinCheckDigit(string $gtin): bool
    {
        if (! preg_match('/^\d{14}$/', $gtin)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += (int) $gtin[$i] * ($i % 2 === 0 ? 3 : 1);
        }

        return (10 - ($sum % 10)) % 10 === (int) $gtin[13];
    }
}
