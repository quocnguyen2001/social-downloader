<?php

declare(strict_types=1);

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Trait for standardized API responses.
 *
 * Provides consistent response format across all API endpoints.
 */
trait ApiResponseTrait
{
    /**
     * Return a success response.
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = Response::HTTP_OK,
        array $headers = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode, $headers);
    }

    /**
     * Return an error response.
     */
    protected function errorResponse(
        string $message = 'Error',
        mixed $errors = null,
        int $statusCode = Response::HTTP_BAD_REQUEST,
        array $headers = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode, $headers);
    }

    /**
     * Return a paginated response.
     */
    protected function paginatedResponse(
        LengthAwarePaginator $paginator,
        string $message = 'Success',
        array $headers = []
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ], Response::HTTP_OK, $headers);
    }

    /**
     * Return a collection response.
     */
    protected function collectionResponse(
        Collection $collection,
        string $message = 'Success',
        array $headers = []
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $collection->toArray(),
            'count' => $collection->count(),
        ], Response::HTTP_OK, $headers);
    }

    /**
     * Return a created response.
     */
    protected function createdResponse(
        mixed $data = null,
        string $message = 'Created successfully',
        array $headers = []
    ): JsonResponse {
        return $this->successResponse($data, $message, Response::HTTP_CREATED, $headers);
    }

    /**
     * Return a no content response.
     */
    protected function noContentResponse(
        string $message = 'No content',
        array $headers = []
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
        ], Response::HTTP_NO_CONTENT, $headers);
    }

    /**
     * Return an unauthorized response.
     */
    protected function unauthorizedResponse(
        string $message = 'Unauthorized',
        array $headers = []
    ): JsonResponse {
        return $this->errorResponse($message, null, Response::HTTP_UNAUTHORIZED, $headers);
    }

    /**
     * Return a forbidden response.
     */
    protected function forbiddenResponse(
        string $message = 'Forbidden',
        array $headers = []
    ): JsonResponse {
        return $this->errorResponse($message, null, Response::HTTP_FORBIDDEN, $headers);
    }

    /**
     * Return a not found response.
     */
    protected function notFoundResponse(
        string $message = 'Not found',
        array $headers = []
    ): JsonResponse {
        return $this->errorResponse($message, null, Response::HTTP_NOT_FOUND, $headers);
    }

    /**
     * Return a validation error response.
     */
    protected function validationErrorResponse(
        mixed $errors,
        string $message = 'Validation failed',
        array $headers = []
    ): JsonResponse {
        return $this->errorResponse($message, $errors, Response::HTTP_UNPROCESSABLE_ENTITY, $headers);
    }

    /**
     * Return a server error response.
     */
    protected function serverErrorResponse(
        string $message = 'Internal server error',
        array $headers = []
    ): JsonResponse {
        return $this->errorResponse($message, null, Response::HTTP_INTERNAL_SERVER_ERROR, $headers);
    }

    /**
     * Return a rate limit exceeded response.
     */
    protected function rateLimitResponse(
        string $message = 'Too many requests',
        int $retryAfter = 60,
        array $headers = []
    ): JsonResponse {
        $headers['Retry-After'] = $retryAfter;

        return $this->errorResponse($message, null, Response::HTTP_TOO_MANY_REQUESTS, $headers);
    }

    /**
     * Return a success response with the required API format.
     */
    protected function apiSuccessResponse(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = Response::HTTP_OK,
        array $headers = []
    ): JsonResponse {
        $response = [
            'error' => false,
            'data' => $data,
            'message' => $message,
        ];

        return response()->json($response, $statusCode, $headers);
    }

    /**
     * Return an error response with the required API format.
     */
    protected function apiErrorResponse(
        string $message = 'Error',
        mixed $data = null,
        int $statusCode = Response::HTTP_BAD_REQUEST,
        array $headers = []
    ): JsonResponse {
        $response = [
            'error' => true,
            'data' => $data,
            'message' => $message,
        ];

        return response()->json($response, $statusCode, $headers);
    }
}
