<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password as PasswordRule;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'min:2',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users',
            ],
            'password' => [
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
            'password_confirmation' => ['required', 'string'],
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
            'name.required' => trans('auth.validation.name_required'),
            'email.required' => trans('auth.validation.email_required'),
            'email.email' => trans('auth.validation.email_invalid'),
            'email.unique' => trans('auth.validation.email_exists'),
            'password.required' => trans('auth.validation.password_required'),
            'password.confirmed' => trans('auth.validation.password_confirmation'),
            'password.min' => trans('auth.validation.password_min'),
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
            'name' => trans('auth.fields.name', [], 'Name'),
            'email' => trans('auth.fields.email', [], 'Email'),
            'password' => trans('auth.fields.password', [], 'Password'),
        ];
    }
}
