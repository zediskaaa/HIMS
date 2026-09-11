<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'po_number' => $this->po_number,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->relationLoaded('supplier') && $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ] : null,
            'item_id' => $this->item_id,
            'item' => $this->relationLoaded('item') && $this->item ? [
                'id' => $this->item->id,
                'name' => $this->item->name,
            ] : null,
            'quantity' => $this->quantity,
            'status' => $this->status,
            $this->mergeWhen($request->user()?->can(Permission::ViewProcurementSensitiveData->value), [
                'unit_cost' => $this->unit_cost,
                'total_amount' => $this->total_amount,
                'notes' => $this->notes,
            ]),
            'requested_at' => $this->requested_at,
            'received_at' => $this->received_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
