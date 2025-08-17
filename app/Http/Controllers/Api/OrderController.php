<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Traits\ApiResponseTrait;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly OrderService $orderService,
        private readonly PaymentService $paymentService
    ) {}

    /**
     * Create a new order.
     *
     * @param CreateOrderRequest $request
     * @return JsonResponse
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return $this->unauthorizedResponse(__('messages.error.authentication_required'));
            }

            $validatedData = $request->validated();

            // Additional payment method validation
            if (!$this->paymentService->validatePaymentMethod($validatedData['payment_method'])) {
                return $this->badRequestResponse(__('payment_gateway.validation.invalid_payment_method'));
            }

            // Check if any payment methods are available
            $availablePaymentMethods = $this->paymentService->getAvailablePaymentMethods();
            if (empty($availablePaymentMethods)) {
                return $this->badRequestResponse(__('payment_gateway.validation.no_payment_methods_enabled'));
            }

            $order = $this->orderService->createOrder($user, $validatedData);

            // Process payment
            $paymentResult = $this->paymentService->processPayment($order->transaction);

            return $this->createdResponse(
                new OrderResource($order)
                    ->additional(['payment' => $paymentResult]),
                __('messages.success.order_created')
            );

        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse($e->getMessage());
        } catch (\Exception $e) {
            Log::error('Order creation failed', [
                'user_id' => auth()->id(),
                'request_data' => $request->validated(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->serverErrorResponse(__('messages.error.order_creation_failed'));
        }
    }

    /**
     * Get customer orders.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return $this->unauthorizedResponse(__('messages.error.authentication_required'));
            }

            $filters = $request->only(['status', 'from_date', 'to_date', 'per_page']);
            $orders = $this->orderService->getUserOrders($user, $filters);

            return $this->paginatedResponse(
                $orders->through(fn($order) => new OrderResource($order)),
                __('messages.success.orders_retrieved')
            );

        } catch (\Exception $e) {
            Log::error('Failed to retrieve user orders', [
                'user_id' => auth()->id(),
                'filters' => $request->only(['status', 'from_date', 'to_date', 'per_page']),
                'error' => $e->getMessage(),
            ]);

            return $this->serverErrorResponse(__('messages.error.orders_retrieval_failed'));
        }
    }

    /**
     * Get a specific order.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return $this->unauthorizedResponse(__('messages.error.authentication_required'));
            }

            $order = $user->orders()
                ->with(['membershipPlan', 'transaction'])
                ->findOrFail($id);

            return $this->successResponse(
                new OrderResource($order),
                __('messages.success.order_retrieved')
            );

        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse(__('messages.error.order_not_found'));
        } catch (\Exception $e) {
            Log::error('Failed to retrieve order', [
                'user_id' => auth()->id(),
                'order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->serverErrorResponse(__('messages.error.order_retrieval_failed'));
        }
    }
}
