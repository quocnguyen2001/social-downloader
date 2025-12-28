<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Download Session Resource for API responses.
 *
 * Transforms download session data for consistent API output, including download options.
 */
class DownloadSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'api_key_id' => $this->api_key_id,
            'user_id' => $this->user_id,
            'original_url' => $this->original_url,
            'platform' => [
                'value' => $this->platform->value,
                'label' => $this->platform->getLabel(),
                'icon' => $this->platform_icon,
            ],
            'video_id' => $this->video_id,
            'title' => $this->title,
            'thumbnail_path' => $this->thumbnail_path,
            'thumbnail_disk' => $this->thumbnail_disk,
            'thumbnail_url' => $this->thumbnail_url,
            'is_thumbnail_valid' => $this->isThumbnailValid(),
            'duration' => $this->duration,
            'formatted_duration' => $this->formatted_duration,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->getLabel(),
                'color' => $this->status->getColor(),
                'icon' => $this->status->getIcon(),
                'badge_color' => $this->status_badge_color,
            ],
            'error_message' => $this->error_message,
            'expires_at' => $this->expires_at?->toISOString(),
            'time_until_expiration' => $this->time_until_expiration,
            'is_expired' => $this->isExpired(),
            'is_ready_for_download' => $this->isReadyForDownload(),
            'is_completed' => $this->isCompleted(),
            'is_failed' => $this->isFailed(),
            'is_pending' => $this->isPending(),
            'is_processing' => $this->isProcessing(),
            'is_fetching_metadata' => $this->isFetchingMetadata(),
            'is_metadata_fetched' => $this->isMetadataFetched(),
            'download_options' => DownloadOptionResource::collection($this->whenLoaded('downloadOptions')),
            'download_options_count' => $this->when(
                $this->relationLoaded('downloadOptions'),
                fn () => $this->downloadOptions->count(),
                fn () => $this->downloadOptions()->count()
            ),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
