<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\AuthenticationContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $guard = AuthenticationContext::authenticatedGuard() ?? AuthenticationContext::WEB_GUARD;
        $emailRules = [
            'required',
            'string',
            'lowercase',
            'email',
            'max:255',
            Rule::unique(User::class)->ignore($this->user()->id),
        ];

        $currentPasswordRules = ['nullable', 'current_password:'.$guard];

        if (is_string($this->input('email')) && $this->input('email') !== $this->user()->email) {
            $currentPasswordRules = ['required', 'current_password:'.$guard];
        }

        return [
            'surname' => ['required', 'string', 'max:80'],
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'email' => $emailRules,
            'current_password' => $currentPasswordRules,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Current password is required to change your email address.',
            'current_password.current_password' => 'Current password is incorrect.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'surname' => 'last name',
            'first_name' => 'first name',
            'middle_name' => 'middle name',
        ];
    }
}
