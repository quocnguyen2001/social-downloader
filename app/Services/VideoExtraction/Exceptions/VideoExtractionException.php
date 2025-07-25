<?php

namespace App\Services\VideoExtraction\Exceptions;

use Exception;

/**
 * Base exception for video extraction operations.
 *
 * All video extraction related exceptions should extend this class
 * to provide consistent error handling across the system.
 */
class VideoExtractionException extends Exception
{
    /**
     * Additional context data for the exception.
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * Create a new video extraction exception.
     *
     * @param  string  $message  The exception message
     * @param  int  $code  The exception code
     * @param  \Throwable|null  $previous  The previous exception
     * @param  array<string, mixed>  $context  Additional context data
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get the exception context data.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set additional context data.
     *
     * @param  array<string, mixed>  $context
     */
    public function setContext(array $context): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Add a context item.
     */
    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;

        return $this;
    }

    /**
     * Convert the exception to an array for logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'context' => $this->context,
        ];
    }
}
