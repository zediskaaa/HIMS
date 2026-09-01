<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\UserDepartment;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Services\UserAccountService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::ManageUsers) ?? false;
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
            'role' => [
                'required',
                Rule::enum(UserRole::class),
                Rule::in(app(UserAccountService::class)->assignableRoleValues($this->user())),
            ],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'department' => ['required', Rule::enum(UserDepartment::class)],
            'phone' => ['bail', 'required', 'string', 'digits:11', 'regex:/^09[0-9]{9}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.required' => 'Pick the role this account should have.',
            'role.in' => 'You are not authorized to assign that role.',
            'department.required' => 'Pick the department this employee belongs to.',
            'department.enum' => 'Pick a valid department from the list.',
            'phone.required' => 'Phone number is required.',
            'phone.digits' => 'Contact number must contain numbers only and exactly 11 digits.',
            'phone.regex' => 'Contact number must start with 09 and contain exactly 11 digits.',
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
