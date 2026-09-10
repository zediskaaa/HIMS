<?php

namespace App\Http\Requests;

use App\Rules\ProcurementEligibleSupplier;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'po_number' => 'sometimes|required|string',
            'supplier_id' => ['sometimes', 'required', 'integer', new ProcurementEligibleSupplier],
            'item_id' => 'sometimes|required|integer|exists:inventory_items,id',
            'quantity' => 'sometimes|required|numeric|min:0.01',
            'unit_cost' => 'nullable|numeric|min:0',
            'total_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:pending,approved,received,cancelled',
            'notes' => 'nullable|string',
            'requested_at' => 'nullable|date',
            'received_at' => 'nullable|date',
        ];
    }
}
