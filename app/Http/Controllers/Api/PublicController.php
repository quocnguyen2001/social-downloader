<?php

namespace App\Http\Controllers\Api;

use App\Http\Traits\ApiResponseTrait;
use App\Models\Transaction;
use App\Services\PaymentService;

class PublicController
{
    use ApiResponseTrait;

    public function paymentMethods(PaymentService $paymentService)
    {
        $methods = $paymentService->getAvailablePaymentMethods();

        $data = [];

        foreach ($methods as $method => $label) {
            $data[] = [
                'label' => $label,
                'value' => $method,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function checkBankTransferStatus(string $chargeId)
    {
        $transaction = Transaction::query()
            ->with('subscription')
            ->where('charge_id', $chargeId)
            ->first();

        if (! $transaction) {
            return $this->errorResponse(__('messages.error.not_found', [
                'resource' => __('models.transaction.singular'),
            ]), 404);
        }

        if ($transaction->isCompleted()) {
            return $this->successResponse(
                [
                    'transaction' => $transaction,
                    'subscription' => $transaction->subscription,
                ],
                __('messages.payment.transaction_completed')
            );
        }

        return $this->errorResponse(__('messages.payment.transaction_not_completed'), 400);
    }
}
