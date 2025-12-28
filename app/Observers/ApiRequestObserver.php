<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\UpdateApiKeyUsageFromRequests;
use App\Models\ApiRequest;

/**
 * Observer that keeps ApiKey usage counters in sync with ApiRequest mutations.
 */
class ApiRequestObserver
{
    public function created(ApiRequest $apiRequest): void
    {
        $this->dispatchAggregationJobs($apiRequest);
    }

    public function updated(ApiRequest $apiRequest): void
    {
        $this->dispatchAggregationJobs($apiRequest);
    }

    public function deleted(ApiRequest $apiRequest): void
    {
        $this->dispatchAggregationJobs($apiRequest);
    }

    /**
     * Dispatch aggregation job(s) for any API keys affected by the change.
     */
    private function dispatchAggregationJobs(ApiRequest $apiRequest): void
    {
        if ($apiRequest->api_key_id) {
            UpdateApiKeyUsageFromRequests::dispatch($apiRequest->api_key_id);
        }

        $originalApiKeyId = $apiRequest->getOriginal('api_key_id');

        if ($originalApiKeyId && $originalApiKeyId !== $apiRequest->api_key_id) {
            UpdateApiKeyUsageFromRequests::dispatch($originalApiKeyId);
        }
    }
}
