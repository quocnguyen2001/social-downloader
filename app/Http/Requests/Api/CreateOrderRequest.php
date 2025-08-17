<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'membership_plan_id' => ['required', 'integer', 'exists:membership_plans,id'],
            'payment_method' => ['required', 'string', 'in:bank_transfer,paypal'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'membership_plan_id.required' => __('validation.required', ['attribute' => __('models.membership_plan.singular')]),
            'membership_plan_id.exists' => __('validation.exists', ['attribute' => __('models.membership_plan.singular')]),
            'payment_method.required' => __('validation.required', ['attribute' => __('models.order.fields.payment_method')]),
            'payment_method.in' => __('validation.in', ['attribute' => __('models.order.fields.payment_method')]),
            'coupon_code.max' => __('validation.max.string', ['attribute' => __('models.order.fields.coupon_code'), 'max' => 50]),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'membership_plan_id' => __('models.membership_plan.singular'),
            'payment_method' => __('models.order.fields.payment_method'),
            'coupon_code' => __('models.order.fields.coupon_code'),
        ];
    }
}
