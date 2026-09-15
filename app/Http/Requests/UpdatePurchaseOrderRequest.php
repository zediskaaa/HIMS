<?php

namespace App\Http\Requests;

use App\Enums\PurchaseOrderStatus;
use App\Rules\ProcurementEligibleSupplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // A status outside the enum would leave the order in a state no
            // guard recognises: unreceivable, and not closed either.
            'status' => ['sometimes', 'required', Rule::enum(PurchaseOrderStatus::class)],
            'notes' => 'nullable|string',
            'requested_at' => 'nullable|date',
            'received_at' => 'nullable|date',
        ];
    }
}
