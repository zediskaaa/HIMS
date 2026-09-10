<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStorageLocationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100|regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/|unique:storage_locations,code',
            'parent_id' => 'nullable|integer|exists:storage_locations,id',
            'type' => ['required', Rule::in(['warehouse', 'zone', 'aisle', 'rack', 'shelf', 'level', 'bin', 'pharmacy', 'department'])],
            'description' => 'nullable|string|max:255',
            'zone' => 'nullable|string|max:100',
            'storage_classification' => ['nullable', Rule::in(['general', 'medical_supply', 'pharmaceutical', 'sterile', 'cold_chain', 'hazardous', 'flammable', 'controlled'])],
            'temperature_classification' => ['nullable', Rule::in(['ambient', 'controlled_room', 'refrigerated', 'frozen', 'deep_frozen'])],
            'capacity' => 'nullable|integer|min:1|max:1000000000',
            'capacity_unit' => ['required_with:capacity', Rule::in(['units', 'boxes', 'pallets'])],
            'status' => 'nullable|string|in:active,blocked,inactive',
            'is_receiving_staging' => 'nullable|boolean',
            'is_quarantine' => 'nullable|boolean',
            'is_pick_face' => 'nullable|boolean',
            'is_reserve' => 'nullable|boolean',
            'is_dispatch_staging' => 'nullable|boolean',
            'is_returns_area' => 'nullable|boolean',
            'is_damaged_stock' => 'nullable|boolean',
            'sort_sequence' => 'nullable|integer|min:0|max:100000',
        ];
    }
}
