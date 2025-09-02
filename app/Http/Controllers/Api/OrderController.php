<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly OrderService   $orderService,
        private readonly PaymentService $paymentService
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'keyword' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'per_page' => 'integer|min:1|max:100',
        ]);

        $user = $request->user();

        $query = Order::query()
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

        $orders = $query->paginate($request->per_page ?? 15);

        return $this->paginatedResponse(
            $orders->through(fn($order) => new OrderResource($order)),
            __('messages.success.orders_retrieved')
        );
    }


    public function store(CreateOrderRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return $this->unauthorizedResponse(__('messages.error.authentication_required'));
            }

            $validatedData = $request->validated();

            if (!$this->paymentService->validatePaymentMethod($validatedData['payment_method'])) {
                return $this->errorResponse(__('payment_gateway.validation.invalid_payment_method'));
            }

            $availablePaymentMethods = $this->paymentService->getAvailablePaymentMethods();

            if (empty($availablePaymentMethods)) {
                return $this->errorResponse(__('payment_gateway.validation.no_payment_methods_enabled'));
            }

            $order = $this->orderService->createOrder($user, $validatedData);

            $paymentResult = $this->paymentService->processPayment($order->transaction);

            return $this->createdResponse(
                [
                    ...(new OrderResource($order)->toArray($request)),
                    'payment' => $paymentResult,
                ],
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
