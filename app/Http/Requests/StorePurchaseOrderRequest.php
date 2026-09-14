<?php

namespace App\Http\Requests;

use App\Rules\ProcurementEligibleSupplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('cost_center_id')) {
            $fallbackId = \App\Models\CostCenter::query()->where('is_active', true)->orderBy('id')->value('id');
            if ($fallbackId) {
                $this->merge(['cost_center_id' => $fallbackId]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', new ProcurementEligibleSupplier],
            'item_id' => [
                'required',
                'integer',
                Rule::exists('inventory_items', 'id')->where(fn ($query) => $query->where('status', '!=', 'inactive')),
            ],
            'cost_center_id' => [
                'nullable',
                'integer',
                Rule::exists('cost_centers', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'payment_terms' => ['nullable', Rule::in(['Net 15', 'Net 30', 'Net 60', 'COD'])],
            'incoterms' => ['nullable', Rule::in(['DDP', 'FOB', 'CIF', 'EXW'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
