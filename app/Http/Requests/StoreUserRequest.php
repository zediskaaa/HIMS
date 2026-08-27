<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\UserDepartment;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::ManageUsers) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => preg_replace('/[\s()-]+/', '', (string) $this->input('phone')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'surname' => ['required', 'string', 'max:80'],
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'department' => ['required', Rule::enum(UserDepartment::class)],
            'phone' => ['required', 'string', 'max:13', 'regex:/^(?:\+63|0)9\d{9}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.required' => 'Pick the role this account should have.',
            'department.required' => 'Pick the department this employee belongs to.',
            'department.enum' => 'Pick a valid department from the list.',
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Enter a valid Philippine mobile number, such as 09171234567.',
            'password.confirmed' => 'The two passwords do not match.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'surname' => 'surname',
            'first_name' => 'first name',
            'middle_name' => 'middle name',
            'phone' => 'phone number',
        ];
    }
}
