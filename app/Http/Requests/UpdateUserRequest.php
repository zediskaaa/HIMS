<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\UserDepartment;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->route('user');

        return $actor?->hasPermission(Permission::ManageUsers)
            && $target instanceof User
            && app(UserAccountService::class)->canManage($actor, $target);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $user = $this->route('user');
        $departments = array_keys(UserDepartment::optionsIncluding($this->route('user')?->department));

        return [
            'surname' => ['required', 'string', 'max:80'],
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            // Left blank on the edit form when the password is not changing.
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => [
                'required',
                Rule::enum(UserRole::class),
                Rule::in(app(UserAccountService::class)->assignableRoleValues($this->user(), $user)),
            ],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'department' => ['required', 'string', Rule::in($departments)],
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
            'department.in' => 'Pick a valid department from the list.',
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
