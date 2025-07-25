<?php

namespace App\Services\VideoExtraction\Contracts;

use App\Services\VideoExtraction\DTOs\ExtractionResult;

/**
 * Interface for video content extractors.
 *
 * This interface defines the contract for extracting video content
 * and metadata from various sources.
 */
interface ExtractorInterface
{
    /**
     * Extract video content and metadata from the given URL.
     *
     * @param  string  $url  The URL to extract content from
     * @param  array  $options  Additional extraction options
     * @return ExtractionResult The extraction result containing metadata and download URLs
     *
     * @throws \App\Services\VideoExtraction\Exceptions\ExtractionFailedException
     * @throws \App\Services\VideoExtraction\Exceptions\InvalidUrlException
     * @throws \App\Services\VideoExtraction\Exceptions\RateLimitExceededException
     */
    public function extract(string $url, array $options = []): ExtractionResult;

    /**
     * Check if the extractor supports the given URL.
     *
     * @param  string  $url  The URL to check
     * @return bool True if the URL is supported
     */
    public function supports(string $url): bool;

    /**
     * Get the priority of this extractor.
     * Higher values indicate higher priority.
     *
     * @return int The priority value
     */
    public function getPriority(): int;

    /**
     * Get the name of this extractor.
     *
     * @return string The extractor name
     */
    public function getName(): string;

    /**
     * Get the version of this extractor.
     *
     * @return string The version string
     */
    public function getVersion(): string;
}
