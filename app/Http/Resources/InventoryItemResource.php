<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    public function toArray($request)
    {
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
            'unit_cost' => $this->unit_cost,
            'total_value' => $this->total_value,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->relationLoaded('supplier') && $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ] : null,
            'warehouse_name' => $this->warehouse_name,
            'batch_number' => $this->batch_number,
            'expiry_date' => optional($this->expiry_date)->toDateString(),
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
