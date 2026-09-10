<?php

namespace App\Http\Requests;

use App\Rules\ProcurementEligibleSupplier;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierQuoteRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'procurement_request_id' => 'required|integer|exists:procurement_requests,id',
            'supplier_id' => ['required', 'integer', new ProcurementEligibleSupplier],
            'quoted_price' => 'required|numeric|min:0',
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
        ];
    }
}
