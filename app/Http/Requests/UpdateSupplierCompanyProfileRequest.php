<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierCompanyProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $registeredAddress = $this->input('address');
        $tin = $this->input('tax_number');
        if (is_string($tin) && preg_match('/^[0-9-]+$/D', $tin) === 1) {
            $digits = preg_replace('/\D/', '', $tin);
            $tin = implode('-', str_split($digits, 3));
        }

        $firstName = $this->input('contact_first_name');
        $middleName = $this->input('contact_middle_name');
        $surname = $this->input('contact_surname');
        if (! $this->exists('contact_first_name') && filled($this->input('contact_person'))) {
            [$firstName, $middleName, $surname] = User::splitName($this->input('contact_person'));
        }

        $paymentTerms = $this->input('payment_terms');
        if ($this->exists('payment_terms_choice')) {
            $paymentTerms = $this->input('payment_terms_choice') === 'custom'
                ? $this->input('payment_terms_custom')
                : $this->input('payment_terms_choice');
        }

        $this->merge([
            'tax_number' => $tin,
            'billing_address' => $this->boolean('billing_same_as_registered') ? $registeredAddress : $this->input('billing_address'),
            'delivery_address' => $this->boolean('delivery_same_as_registered') ? $registeredAddress : $this->input('delivery_address'),
            'provides_regulated_health_products' => $this->boolean('provides_regulated_health_products'),
            'contact_first_name' => $firstName,
            'contact_middle_name' => $middleName,
            'contact_surname' => $surname,
            'contact_person' => User::composeName($firstName, $middleName, $surname),
            'payment_terms' => $paymentTerms,
        ]);
    }

    public function authorize(): bool
    {
        return ($this->user()?->can(Permission::SupplierManageProfile->value) ?? false)
            && $this->user()?->supplier_id !== null;
    }

    public function rules(): array
    {
        $required = $this->routeIs('supplier.company-profile.submit') ? 'required' : 'nullable';

        return [
            'name' => [$required, 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'business_structure' => [$required, Rule::in(['sole_proprietorship', 'partnership', 'corporation', 'cooperative', 'government_entity', 'foreign_entity', 'other'])],
            'tax_number' => [$required, 'string', 'size:15', 'regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/'],
            'provides_regulated_health_products' => ['required', 'boolean'],
            'address' => [$required, 'string', 'max:1000'],
            'billing_address' => [$required, 'string', 'max:1000'],
            'delivery_address' => [$required, 'string', 'max:1000'],
            'contact_first_name' => [$required, 'string', 'max:80', 'regex:/^\p{L}+(?:[ \'-]\p{L}+)*$/u'],
            'contact_middle_name' => ['nullable', 'string', 'max:80', 'regex:/^\p{L}+(?:[ \'-]\p{L}+)*$/u'],
            'contact_surname' => [$required, 'string', 'max:80', 'regex:/^\p{L}+(?:[ \'-]\p{L}+)*$/u'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_position' => [$required, 'string', 'max:255'],
            'email' => [$required, 'email', 'max:255'],
            'phone' => [$required, 'string', 'digits:11', 'regex:/^09[0-9]{9}$/'],
            'standard_lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'payment_terms_choice' => ['nullable', Rule::in(['Net 15', 'Net 30', 'Net 45', 'Net 60', 'Due on Receipt', 'COD', 'custom'])],
            'payment_terms_custom' => ['nullable', 'string', 'max:200', Rule::requiredIf(fn (): bool => $this->input('payment_terms_choice') === 'custom')],
            'payment_terms' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'tax_number.size' => 'TIN must contain exactly 12 digits.',
            'tax_number.regex' => 'Enter a valid 12-digit TIN in 000-000-000-000 format.',
            'contact_first_name.regex' => 'First name may only contain letters, spaces, apostrophes, and hyphens.',
            'contact_middle_name.regex' => 'Middle name may only contain letters, spaces, apostrophes, and hyphens.',
            'contact_surname.regex' => 'Surname may only contain letters, spaces, apostrophes, and hyphens.',
            'phone.digits' => 'Contact number must contain exactly 11 digits.',
            'phone.regex' => 'Contact number must start with 09 and contain exactly 11 digits.',
            'standard_lead_time_days.max' => 'Standard lead time cannot exceed 3650 days.',
        ];
    }
}
