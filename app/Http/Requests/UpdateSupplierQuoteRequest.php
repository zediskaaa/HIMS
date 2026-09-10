<?php

namespace App\Http\Requests;

use App\Rules\ProcurementEligibleSupplier;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierQuoteRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'procurement_request_id' => 'sometimes|required|integer|exists:procurement_requests,id',
            'supplier_id' => ['sometimes', 'required', 'integer', new ProcurementEligibleSupplier],
            'quoted_price' => 'sometimes|required|numeric|min:0',
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
        ];
    }
}
