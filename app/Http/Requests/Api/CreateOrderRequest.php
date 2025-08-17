<?php

namespace App\Http\Requests\Api;

use App\Services\PaymentService;
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
        $paymentService = app(PaymentService::class);
        $availablePaymentMethods = $paymentService->getAvailablePaymentMethodValues();

        // If no payment methods are available, we still need to validate the field
        // but it will fail validation with a custom message
        $paymentMethodRule = empty($availablePaymentMethods)
            ? ['required', 'string', 'in:'] // This will always fail
            : ['required', 'string', 'in:' . implode(',', $availablePaymentMethods)];

        return [
            'membership_plan_id' => ['required', 'integer', 'exists:membership_plans,id'],
            'payment_method' => $paymentMethodRule,
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $paymentService = app(PaymentService::class);
        $availablePaymentMethods = $paymentService->getAvailablePaymentMethods();

        $paymentMethodMessage = empty($availablePaymentMethods)
            ? __('payment_gateway.validation.no_payment_methods_enabled')
            : __('validation.in', ['attribute' => __('models.order.fields.payment_method')]);

        return [
            'membership_plan_id.required' => __('validation.required', ['attribute' => __('models.membership_plan.singular')]),
            'membership_plan_id.exists' => __('validation.exists', ['attribute' => __('models.membership_plan.singular')]),
            'payment_method.required' => __('validation.required', ['attribute' => __('models.order.fields.payment_method')]),
            'payment_method.in' => $paymentMethodMessage,
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
