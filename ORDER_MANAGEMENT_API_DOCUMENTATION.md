# Order Management API Documentation

## Overview

The Order Management API provides comprehensive functionality for handling order creation and retrieval in the social-downloader system. This API allows authenticated users to purchase membership plans and track their order history.

## Authentication

All order management endpoints require Sanctum authentication. Include the bearer token in the Authorization header:

```
Authorization: Bearer {your-token}
```

## Endpoints

### 1. Create Order

**Endpoint:** `POST /api/v1/orders`

Creates a new order for a membership plan with the specified payment method.

#### Request Body

```json
{
    "membership_plan_id": 1,
    "payment_method": "bank_transfer",
    "coupon_code": "DISCOUNT10"
}
```

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `membership_plan_id` | integer | Yes | ID of the membership plan to purchase |
| `payment_method` | string | Yes | Payment method: `bank_transfer` or `paypal` |
| `coupon_code` | string | No | Optional discount coupon code |

#### Response

**Success (201 Created):**

```json
{
    "success": true,
    "message": "Order created successfully",
    "data": {
        "id": "9d1e8c2a-4b5f-4c6d-8e9f-1a2b3c4d5e6f",
        "user_id": 1,
        "membership_plan": {
            "id": 1,
            "name": "Pro Plan",
            "slug": "pro-plan",
            "description": "Professional features with higher limits",
            "price": "299000.00",
            "currency": "VND",
            "billing_cycle": "monthly",
            "daily_request_limit": 1000,
            "total_request_download": 10000,
            "formatted_price": "VND 299,000.00"
        },
        "transaction": {
            "id": "9d1e8c2a-4b5f-4c6d-8e9f-1a2b3c4d5e6g",
            "charge_id": "BT_20250817_ABC12345",
            "order_id": "9d1e8c2a-4b5f-4c6d-8e9f-1a2b3c4d5e6f",
            "customer_name": "John Doe",
            "customer_email": "john@example.com",
            "payment_method": {
                "value": "bank_transfer",
                "label": "Bank Transfer",
                "color": "primary",
                "icon": "heroicon-o-building-library",
                "is_instant": false,
                "requires_verification": true
            },
            "amount": "299000.00",
            "currency": "VND",
            "formatted_amount": "299,000.00 VND",
            "status": {
                "value": "pending",
                "color": "warning",
                "is_completed": false,
                "is_pending": true,
                "is_failed": false
            },
            "created_at": "2025-08-17T10:35:58.000000Z",
            "updated_at": "2025-08-17T10:35:58.000000Z"
        },
        "subtotal": "299000.00",
        "discount": "0.00",
        "total": "299000.00",
        "formatted_subtotal": "299,000.00 VND",
        "formatted_discount": "0.00 VND",
        "formatted_total": "299,000.00 VND",
        "status": {
            "value": "pending",
            "label": "Pending",
            "color": "warning",
            "icon": "heroicon-o-clock"
        },
        "has_discount": false,
        "discount_percentage": 0,
        "created_at": "2025-08-17T10:35:58.000000Z",
        "updated_at": "2025-08-17T10:35:58.000000Z"
    },
    "payment": {
        "success": true,
        "message": "Bank transfer payment initiated. Please complete the transfer using the provided instructions.",
        "requires_verification": true,
        "instructions": "Please transfer the exact amount to the bank account details provided in your order confirmation email."
    }
}
```

**Error (422 Unprocessable Entity):**

```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "membership_plan_id": ["The membership plan field is required."],
        "payment_method": ["The payment method field is required."]
    }
}
```

**Error (404 Not Found):**

```json
{
    "success": false,
    "message": "Membership plan not found or inactive"
}
```

### 2. Get Customer Orders

**Endpoint:** `GET /api/v1/orders` or `GET /api/v1/customer/orders`

Retrieves paginated list of orders for the authenticated user.

#### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `status` | string | No | Filter by order status: `pending`, `processing`, `completed` |
| `from_date` | date | No | Filter orders from this date (YYYY-MM-DD) |
| `to_date` | date | No | Filter orders until this date (YYYY-MM-DD) |
| `per_page` | integer | No | Number of orders per page (default: 15) |

#### Response

**Success (200 OK):**

```json
{
    "success": true,
    "message": "Orders retrieved successfully",
    "data": [
        {
            "id": "9d1e8c2a-4b5f-4c6d-8e9f-1a2b3c4d5e6f",
            "user_id": 1,
            "membership_plan": {
                "id": 1,
                "name": "Pro Plan",
                "slug": "pro-plan",
                "price": "299000.00",
                "currency": "VND",
                "billing_cycle": "monthly"
            },
            "transaction": {
                "id": "9d1e8c2a-4b5f-4c6d-8e9f-1a2b3c4d5e6g",
                "charge_id": "BT_20250817_ABC12345",
                "payment_method": {
                    "value": "bank_transfer",
                    "label": "Bank Transfer",
                    "requires_verification": true
                },
                "status": {
                    "value": "pending",
                    "color": "warning"
                }
            },
            "total": "299000.00",
            "formatted_total": "299,000.00 VND",
            "status": {
                "value": "pending",
                "label": "Pending",
                "color": "warning",
                "icon": "heroicon-o-clock"
            },
            "created_at": "2025-08-17T10:35:58.000000Z"
        }
    ],
    "pagination": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 1,
        "from": 1,
        "to": 1,
        "has_more_pages": false
    }
}
```

### 3. Get Specific Order

**Endpoint:** `GET /api/v1/orders/{id}`

Retrieves details of a specific order belonging to the authenticated user.

#### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | string | Yes | UUID of the order |

#### Response

**Success (200 OK):**

```json
{
    "success": true,
    "message": "Order retrieved successfully",
    "data": {
        "id": "9d1e8c2a-4b5f-4c6d-8e9f-1a2b3c4d5e6f",
        "user_id": 1,
        "membership_plan": {
            "id": 1,
            "name": "Pro Plan",
            "slug": "pro-plan",
            "description": "Professional features with higher limits",
            "price": "299000.00",
            "currency": "VND",
            "billing_cycle": "monthly",
            "daily_request_limit": 1000,
            "total_request_download": 10000
        },
        "transaction": {
            "id": "9d1e8c2a-4b5f-4c6d-8e9f-1a2b3c4d5e6g",
            "charge_id": "BT_20250817_ABC12345",
            "payment_method": {
                "value": "bank_transfer",
                "label": "Bank Transfer",
                "color": "primary",
                "icon": "heroicon-o-building-library",
                "requires_verification": true
            },
            "amount": "299000.00",
            "currency": "VND",
            "status": {
                "value": "pending",
                "color": "warning"
            },
            "payment_logs": [
                {
                    "timestamp": "2025-08-17T10:35:58.000000Z",
                    "event": "transaction_created",
                    "payment_method": "bank_transfer",
                    "amount": "299000.00"
                }
            ]
        },
        "subtotal": "299000.00",
        "discount": "0.00",
        "total": "299000.00",
        "formatted_total": "299,000.00 VND",
        "status": {
            "value": "pending",
            "label": "Pending",
            "color": "warning",
            "icon": "heroicon-o-clock"
        },
        "has_discount": false,
        "discount_percentage": 0,
        "created_at": "2025-08-17T10:35:58.000000Z",
        "updated_at": "2025-08-17T10:35:58.000000Z"
    }
}
```

**Error (404 Not Found):**

```json
{
    "success": false,
    "message": "Order not found"
}
```

## Order Status Flow

Orders follow this status progression:

1. **pending** - Order created, payment not yet processed
2. **processing** - Payment being verified/processed
3. **completed** - Payment confirmed, membership plan activated

## Payment Methods

### Bank Transfer
- **Value:** `bank_transfer`
- **Instant:** No
- **Requires Verification:** Yes
- **Process:** Manual verification required after customer transfers funds

### PayPal
- **Value:** `paypal`
- **Instant:** Yes
- **Requires Verification:** No
- **Process:** Automated processing through PayPal API

## Error Codes

| HTTP Status | Error Code | Description |
|-------------|------------|-------------|
| 400 | BAD_REQUEST | Invalid request data |
| 401 | UNAUTHORIZED | Authentication required |
| 404 | NOT_FOUND | Resource not found |
| 422 | VALIDATION_ERROR | Validation failed |
| 500 | INTERNAL_ERROR | Server error |

## Service Layer Architecture

The order management system follows a clean service layer architecture:

### OrderService
- `createOrder(User $user, array $data): Order` - Creates new orders
- `getUserOrders(User $user, array $filters = [])` - Retrieves user orders
- `updateOrderStatus(Order $order, OrderStatus $status): Order` - Updates order status
- `completeOrder(Order $order): Order` - Completes order and assigns membership

### PaymentService
- `createTransaction(User $user, Order $order, PaymentMethod $method, float $amount): Transaction` - Creates payment transactions
- `processPayment(Transaction $transaction): array` - Processes payments
- `completeTransaction(Transaction $transaction, ?string $externalChargeId = null): Transaction` - Marks transactions as completed
- `failTransaction(Transaction $transaction, string $reason): Transaction` - Marks transactions as failed

## Database Schema

### Orders Table
- `id` (UUID, Primary Key)
- `user_id` (Foreign Key to users)
- `membership_plan_id` (Foreign Key to membership_plans)
- `payment_id` (Foreign Key to transactions)
- `subtotal` (Decimal)
- `discount` (Decimal)
- `total` (Decimal)
- `status` (Enum: pending, processing, completed)
- `created_at` (Timestamp)
- `updated_at` (Timestamp)

### Transactions Table
- `id` (UUID, Primary Key)
- `user_id` (Foreign Key to users)
- `customer_name` (String)
- `customer_email` (String)
- `charge_id` (String)
- `order_id` (String)
- `payment_method` (String)
- `currency` (String)
- `amount` (Decimal)
- `status` (String)
- `payment_logs` (JSON)
- `created_at` (Timestamp)
- `updated_at` (Timestamp)

## Testing

Use the following curl commands to test the API:

### Create Order
```bash
curl -X POST http://localhost:8000/api/v1/orders \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "membership_plan_id": 1,
    "payment_method": "bank_transfer",
    "coupon_code": "DISCOUNT10"
  }'
```

### Get Orders
```bash
curl -X GET "http://localhost:8000/api/v1/orders?status=pending&per_page=10" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Get Specific Order
```bash
curl -X GET http://localhost:8000/api/v1/orders/ORDER_UUID \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Implementation Notes

1. **Coupon System**: Currently implemented as a placeholder. The `calculateDiscount()` method in OrderService returns 0 and logs the coupon code for future implementation.

2. **PayPal Integration**: The PayPal payment processing is simulated. Actual PayPal SDK integration should be implemented in the `processPayPalPayment()` method.

3. **Membership Assignment**: When an order is completed, the membership plan is automatically assigned to the user with appropriate expiration dates based on the billing cycle.

4. **Transaction Logging**: All payment events are logged in the `payment_logs` JSON field for audit purposes.

5. **Internationalization**: All user-facing messages use Laravel's translation system with proper language keys.

6. **Error Handling**: Comprehensive error handling with proper HTTP status codes and descriptive error messages.

This implementation provides a solid foundation for order management that can be extended with additional features like refunds, order cancellation, and more sophisticated payment processing.