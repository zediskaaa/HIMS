<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ManageSuppliers->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'business_structure' => ['nullable', Rule::in(['sole_proprietorship', 'partnership', 'corporation', 'cooperative', 'government_entity', 'foreign_entity', 'other'])],
            'provides_regulated_health_products' => 'sometimes|boolean',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+().\-\s]+$/'],
            'address' => 'nullable|string|max:255',
            'billing_address' => 'nullable|string|max:1000',
            'delivery_address' => 'nullable|string|max:1000',
            'tax_number' => 'nullable|string|max:100',
            'standard_lead_time_days' => 'nullable|integer|min:0|max:3650',
            'payment_terms' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:5000',
        ];
    }
}
