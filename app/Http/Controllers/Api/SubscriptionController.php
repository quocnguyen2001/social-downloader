<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Subscription;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly PaymentService $paymentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'keyword' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(SubscriptionStatus::class)],
            'per_page' => 'integer|min:1|max:100',
        ]);

        $user = $request->user();

        $query = Subscription::query()
            ->where('user_id', $user->id)
            ->with(['membershipPlan', 'transaction'])
            ->orderBy('created_at', 'desc');

        if ($request->has('keyword')) {
            $query->where(function (Builder $query) use ($request) {
                $query->where('id', 'like', "%{$request->keyword}%")
                    ->orWhereHas('membershipPlan', function (Builder $query) use ($request) {
                        $query->where('name', 'like', "%{$request->keyword}%");
                    })
                    ->orWhereHas('transaction', function (Builder $query) use ($request) {
                        $query->where('charge_id', 'like', "%{$request->keyword}%");
                    })
                    ->orWhereHas('transaction', function (Builder $query) use ($request) {
                        $query->where('customer_name', 'like', "%{$request->keyword}%");
                    })
                    ->orWhereHas('transaction', function (Builder $query) use ($request) {
                        $query->where('customer_email', 'like', "%{$request->keyword}%");
                    });
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $subscriptions = $query->paginate($request->per_page ?? 15);

        return $this->paginatedResponse(
            $subscriptions->through(fn ($subscription) => new SubscriptionResource($subscription)),
            __('messages.success.subscriptions_retrieved')
        );
    }

    public function store(CreateSubscriptionRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return $this->unauthorizedResponse(__('messages.error.authentication_required'));
            }

            $validatedData = $request->validated();

            if ($user->hasMembershipActive() && $user->membership_plan_id == $validatedData['membership_plan_id']) {
                return $this->errorResponse(__('messages.error.already_subscribed_to_plan'));
            }

            if (! $this->paymentService->validatePaymentMethod($validatedData['payment_method'])) {
                return $this->errorResponse(__('payment_gateway.validation.invalid_payment_method'));
            }

            $availablePaymentMethods = $this->paymentService->getAvailablePaymentMethods();

            if (empty($availablePaymentMethods)) {
                return $this->errorResponse(__('payment_gateway.validation.no_payment_methods_enabled'));
            }

            $subscription = $this->subscriptionService->createSubscription($user, $validatedData);

            $paymentResult = $this->paymentService->processPayment($subscription->transaction);

            return $this->createdResponse(
                [
                    ...(new SubscriptionResource($subscription)->toArray($request)),
                    'payment' => $paymentResult,
                ],
                __('messages.success.subscription_created')
            );

        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse($e->getMessage());
        } catch (Exception $e) {
            Log::error('Subscription creation failed', [
                'user_id' => auth()->id(),
                'request_data' => $request->validated(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->serverErrorResponse(__('messages.error.subscription_creation_failed'));
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $user = auth()->user();

            if (! $user) {
                return $this->unauthorizedResponse(__('messages.error.authentication_required'));
            }

            $subscription = $user->subscriptions()
                ->with(['membershipPlan', 'transaction'])
                ->findOrFail($id);

            return $this->successResponse(
                new SubscriptionResource($subscription),
                __('messages.success.subscription_retrieved')
            );

        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse(__('messages.error.subscription_not_found'));
        } catch (Exception $e) {
            Log::error('Failed to retrieve subscription', [
                'user_id' => auth()->id(),
                'subscription_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->serverErrorResponse(__('messages.error.subscription_retrieval_failed'));
        }
    }
}
