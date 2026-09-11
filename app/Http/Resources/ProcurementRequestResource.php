<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcurementRequestResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'title' => $this->title,
            'item' => $this->relationLoaded('item') && $this->item ? new InventoryItemResource($this->item) : null,
            'requested_quantity' => $this->requested_quantity,
            'priority' => $this->priority,
            'status' => $this->status,
            'requested_at' => $this->requested_at,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->relationLoaded('supplier') && $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ] : null,
            $this->mergeWhen($request->user()?->can(Permission::ViewProcurementSensitiveData->value), [
                'description' => $this->description,
                'approved_by' => $this->approved_by,
                'approval_notes' => $this->approval_notes,
                'evaluation_score' => $this->evaluation_score,
                'evaluation_status' => $this->evaluation_status,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
