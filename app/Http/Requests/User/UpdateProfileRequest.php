<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Update Profile Request for user profile updates.
 *
 * Handles validation for user profile update operations.
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'min:2',
            ],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email,'.$user->id,
            ],
            'password' => [
                'sometimes',
                'required',
                'string',
                'confirmed',
                'max:128',
                PasswordRule::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'password_confirmation' => [
                'required_with:password',
                'string',
            ],
            'current_password' => [
                'required_with:password',
                'string',
                'current_password',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('The name field is required.'),
            'name.min' => __('The name must be at least 2 characters.'),
            'email.required' => __('The email field is required.'),
            'email.email' => __('The email must be a valid email address.'),
            'email.unique' => __('The email has already been taken.'),
            'password.required' => __('The password field is required.'),
            'password.confirmed' => __('The password confirmation does not match.'),
            'current_password.required_with' => __('The current password is required when changing password.'),
            'current_password.current_password' => __('The current password is incorrect.'),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Name'),
            'email' => __('Email'),
            'password' => __('Password'),
            'password_confirmation' => __('Password Confirmation'),
            'current_password' => __('Current Password'),
        ];
    }
}
