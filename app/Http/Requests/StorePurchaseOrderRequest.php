<?php

namespace App\Http\Requests;

use App\Rules\ProcurementEligibleSupplier;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'po_number' => 'required|string|unique:purchase_orders,po_number',
            'supplier_id' => ['required', 'integer', new ProcurementEligibleSupplier],
            'item_id' => 'required|integer|exists:inventory_items,id',
            'quantity' => 'required|numeric|min:0.01',
            'unit_cost' => 'nullable|numeric|min:0',
            'total_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:pending,approved,received,cancelled',
            'notes' => 'nullable|string',
            'requested_at' => 'nullable|date',
            'received_at' => 'nullable|date',
        ];
    }
}
