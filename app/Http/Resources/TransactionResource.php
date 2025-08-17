<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transaction Resource for API responses.
 *
 * Transforms transaction data for consistent API output.
 */
class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $paymentMethod = PaymentMethod::from($this->payment_method);

        return [
            'id' => $this->id,
            'charge_id' => $this->charge_id,
            'order_id' => $this->order_id,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'payment_method' => [
                'value' => $paymentMethod->value,
                'label' => $paymentMethod->getLabel(),
                'color' => $paymentMethod->getColor(),
                'icon' => $paymentMethod->getIcon(),
                'is_instant' => $paymentMethod->isInstant(),
                'requires_verification' => $paymentMethod->requiresVerification(),
            ],
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted_amount' => $this->formatted_amount,
            'status' => [
                'value' => $this->status,
                'color' => $this->status_badge_color,
                'is_completed' => $this->isCompleted(),
                'is_pending' => $this->isPending(),
                'is_failed' => $this->isFailed(),
            ],
            'payment_logs' => $this->payment_logs,
            'latest_payment_log' => $this->latest_payment_log,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}