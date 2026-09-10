<?php

namespace App\Http\Requests;

use App\Rules\ProcurementEligibleSupplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryItemRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $item = $this->route('inventory_item') ? $this->route('inventory_item') : null;
        $itemId = $item ? $item->id : null;

        return [
            'sku' => 'sometimes|required|string|unique:inventory_items,sku,'.$itemId,
            'barcode_value' => 'nullable|string|max:100|unique:inventory_items,barcode_value,'.$itemId,
            'gtin' => 'nullable|digits_between:8,14|unique:inventory_items,gtin,'.$itemId,
            'name' => 'sometimes|required|string|max:255',
            'category_id' => ['nullable', 'integer', Rule::exists('item_categories', 'id')->where('is_active', true)],
            'unit' => 'nullable|string|max:50',
            'is_batch_tracked' => 'sometimes|boolean',
            'is_serial_tracked' => 'sometimes|boolean',
            'is_expiry_tracked' => 'sometimes|boolean',
            'storage_classification' => ['nullable', Rule::in(['general', 'medical_supply', 'pharmaceutical', 'sterile', 'cold_chain', 'hazardous', 'flammable', 'controlled'])],
            'temperature_classification' => ['nullable', Rule::in(['ambient', 'controlled_room', 'refrigerated', 'frozen', 'deep_frozen'])],
            'pick_face_minimum' => 'nullable|integer|min:0',
            'pick_face_maximum' => 'nullable|integer|min:0|gte:pick_face_minimum',
            'reorder_level' => 'nullable|integer|min:0',
            'unit_cost' => 'nullable|numeric|min:0|max:9999999999.99|decimal:0,2',
            'supplier_id' => ['nullable', new ProcurementEligibleSupplier],
            'status' => 'nullable|string|in:active,inactive',
        ];
    }
}
