<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ManageSuppliers->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
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
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png', 'max:3072'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.file' => 'The uploaded file is not valid.',
            'logo.mimes' => 'The supplier logo must be a file of type: JPG, JPEG, PNG.',
            'logo.mimetypes' => 'The supplier logo must be a file of type: JPG, JPEG, PNG.',
            'logo.max' => 'The supplier logo must not exceed 3 MB.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $file = $this->file('logo');
            if (! $file || ! $file->isValid()) {
                return;
            }

            // Image integrity check: verify decodable image headers and dimensions.
            $imageInfo = @getimagesize($file->getRealPath());
            if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
                $validator->errors()->add('logo', 'The uploaded file is corrupted or not a valid image.');

                return;
            }

            if (! in_array($imageInfo['mime'], ['image/jpeg', 'image/png'], true)) {
                $validator->errors()->add('logo', 'The uploaded image must be a valid JPG, JPEG, or PNG format.');

                return;
            }

            if ($imageInfo[0] > 4096 || $imageInfo[1] > 4096) {
                $validator->errors()->add('logo', 'The image dimensions cannot exceed 4096x4096 pixels.');
            }
        });
    }
}
