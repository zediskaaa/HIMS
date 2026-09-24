<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    public function toArray($request)
    {
        $expiryBatches = $this->relationLoaded('batches')
            ? $this->batches
                ->map(function ($batch): array {
                    $quantity = (int) $batch->stockLevels->sum('quantity');

                    return [
                        'batch_id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'lot_number' => $batch->lot_number,
                        'quantity_on_hand' => $quantity,
                        'expiry_date' => $batch->expiry_date?->toDateString(),
                        'days_remaining' => $batch->daysUntilExpiry(),
                        'expiry_status' => $batch->expiryClassification(),
                        'expiry_status_label' => $batch->expiryStatusLabel(),
                    ];
                })
                ->filter(fn (array $batch): bool => $batch['quantity_on_hand'] > 0)
                ->sortBy('days_remaining')
                ->values()
            : collect();

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode_value' => $this->barcode_value,
            'gtin' => $this->gtin,
            'name' => $this->name,
            'category' => $this->category,
            'unit' => $this->unit,
            'is_batch_tracked' => $this->is_batch_tracked,
            'is_serial_tracked' => $this->is_serial_tracked,
            'is_expiry_tracked' => $this->is_expiry_tracked,
            'storage_classification' => $this->storage_classification,
            'temperature_classification' => $this->temperature_classification,
            'pick_face_minimum' => $this->pick_face_minimum,
            'pick_face_maximum' => $this->pick_face_maximum,
            'quantity_on_hand' => $this->quantity_on_hand,
            'reserved_quantity' => $this->reserved_quantity,
            'reorder_level' => $this->reorder_level,
            $this->mergeWhen($request->user()?->can(Permission::ViewProcurementSensitiveData->value), [
                'unit_cost' => $this->unit_cost,
                'total_value' => $this->total_value,
            ]),
            $this->mergeWhen($request->user()?->can(Permission::ViewSuppliers->value), [
                'supplier_id' => $this->supplier_id,
                'supplier' => $this->relationLoaded('supplier') && $this->supplier ? [
                    'id' => $this->supplier->id,
                    'name' => $this->supplier->name,
                ] : null,
            ]),
            'warehouse_name' => $this->warehouse_name,
            'batch_number' => $this->batch_number,
            'expiry_date' => optional($this->expiry_date)->toDateString(),
            'expiry_batches' => $expiryBatches,
            // `status` is the item's lifecycle; the stock condition is derived
            // from the quantities so an API consumer sees the same state the
            // catalogue and the reports render.
            'status' => $this->status,
            'stock_status' => $this->stockStatus(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
