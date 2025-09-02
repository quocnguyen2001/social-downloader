<?php

namespace App\Http\Requests\Api;

use App\Services\PaymentService;
use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function rules(): array
    {
        $paymentService = app(PaymentService::class);
        $availablePaymentMethods = $paymentService->getAvailablePaymentMethodValues();

        $paymentMethodRule = empty($availablePaymentMethods)
            ? ['required', 'string', 'in:']
            : ['required', 'string', 'in:'.implode(',', $availablePaymentMethods)];

        return [
            'membership_plan_id' => ['required', 'integer', 'exists:membership_plans,id'],
            'payment_method' => $paymentMethodRule,
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ];
    }

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

    public function attributes(): array
    {
        return [
            'membership_plan_id' => __('models.membership_plan.singular'),
            'payment_method' => __('models.order.fields.payment_method'),
            'coupon_code' => __('models.order.fields.coupon_code'),
        ];
    }
}
