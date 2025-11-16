<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'membership_plan' => $this->whenLoaded('membershipPlan', function () {
                return new MembershipPlanResource($this->membershipPlan);
            }),
            'transaction' => $this->whenLoaded('transaction', function () {
                return new TransactionResource($this->transaction);
            }),
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'currency' => $this->transaction->currency ?? 'VND',
            'formatted_subtotal' => $this->formatted_subtotal,
            'formatted_discount' => $this->formatted_discount,
            'formatted_total' => $this->formatted_total,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->getLabel(),
                'color' => $this->status->getColor(),
                'icon' => $this->status->getIcon(),
            ],
            'has_discount' => $this->hasDiscount(),
            'discount_percentage' => $this->getDiscountPercentage(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
