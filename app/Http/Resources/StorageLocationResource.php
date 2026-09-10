<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StorageLocationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'barcode_value' => $this->barcode_value,
            'parent_id' => $this->parent_id,
            'type' => $this->type,
            'description' => $this->description,
            'zone' => $this->zone,
            'capacity' => $this->capacity,
            'capacity_unit' => $this->capacity_unit,
            'storage_classification' => $this->storage_classification,
            'temperature_classification' => $this->temperature_classification,
            'operational_purposes' => [
                'receiving_staging' => $this->is_receiving_staging,
                'quarantine' => $this->is_quarantine,
                'pick_face' => $this->is_pick_face,
                'reserve' => $this->is_reserve,
                'dispatch_staging' => $this->is_dispatch_staging,
                'returns_area' => $this->is_returns_area,
                'damaged_stock' => $this->is_damaged_stock,
            ],
            'sort_sequence' => $this->sort_sequence,
            'quantity' => $this->totalQuantity(),
            'utilisation_percent' => $this->utilisation(),
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
