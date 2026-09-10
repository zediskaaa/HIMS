<?php

namespace App\Http\Requests;

use App\Rules\ProcurementEligibleSupplier;
use Illuminate\Foundation\Http\FormRequest;

class StoreProcurementRequestRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'request_number' => 'required|string|unique:procurement_requests,request_number',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'item_id' => 'nullable|integer|exists:inventory_items,id',
            'requested_quantity' => 'nullable|numeric|min:0',
            'priority' => 'nullable|string',
            'status' => 'nullable|string',
            'requested_at' => 'nullable|date',
            'supplier_id' => ['nullable', 'integer', new ProcurementEligibleSupplier],
            'approved_by' => 'nullable|integer|exists:users,id',
            'approval_notes' => 'nullable|string',
            'evaluation_score' => 'nullable|numeric',
            'evaluation_status' => 'nullable|string',
        ];
    }
}
