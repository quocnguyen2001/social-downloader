<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Middleware to handle API exceptions and provide consistent error responses.
 */
class ApiExceptionHandler
{
    use ApiResponseTrait;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (Throwable $e) {
            return $this->handleException($request, $e);
        }
    }

    /**
     * Handle the exception and return appropriate JSON response.
     */
    protected function handleException(Request $request, Throwable $e): JsonResponse
    {
        // Log the exception
        Log::error('API Exception', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
        ]);

        // Handle specific exception types
        return match (true) {
            $e instanceof ValidationException => $this->handleValidationException($e),
            $e instanceof AuthenticationException => $this->handleAuthenticationException($e),
            $e instanceof ModelNotFoundException => $this->handleModelNotFoundException($e),
            $e instanceof NotFoundHttpException => $this->handleNotFoundHttpException($e),
            $e instanceof HttpException => $this->handleHttpException($e),
            default => $this->handleGenericException($e),
        };
    }

    /**
     * Handle validation exceptions.
     */
    protected function handleValidationException(ValidationException $e): JsonResponse
    {
        return $this->validationErrorResponse(
            $e->errors(),
            trans('validation.failed', [], 'Validation failed')
        );
    }

    /**
     * Handle authentication exceptions.
     */
    protected function handleAuthenticationException(AuthenticationException $e): JsonResponse
    {
        return $this->unauthorizedResponse(
            trans('auth.permissions.unauthorized', [], 'Unauthorized')
        );
    }

    /**
     * Handle model not found exceptions.
     */
    protected function handleModelNotFoundException(ModelNotFoundException $e): JsonResponse
    {
        $model = class_basename($e->getModel());

        return $this->notFoundResponse(
            trans('errors.model_not_found', ['model' => $model], "{$model} not found")
        );
    }

    /**
     * Handle not found HTTP exceptions.
     */
    protected function handleNotFoundHttpException(NotFoundHttpException $e): JsonResponse
    {
        return $this->notFoundResponse(
            trans('errors.route_not_found', [], 'Route not found')
        );
    }

    /**
     * Handle HTTP exceptions.
     */
    protected function handleHttpException(HttpException $e): JsonResponse
    {
        $statusCode = $e->getStatusCode();
        $message = $e->getMessage() ?: Response::$statusTexts[$statusCode] ?? 'HTTP Error';

        return $this->errorResponse($message, null, $statusCode);
    }

    /**
     * Handle generic exceptions.
     */
    protected function handleGenericException(Throwable $e): JsonResponse
    {
        // In production, don't expose internal error details
        $message = app()->environment('production')
            ? trans('errors.internal_server_error', [], 'Internal server error')
            : $e->getMessage();

        return $this->serverErrorResponse($message);
    }
}
