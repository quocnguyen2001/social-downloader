<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Download Option Resource for API responses.
 *
 * Transforms download option data for consistent API output.
 */
class DownloadOptionResource extends JsonResource
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
            'cdn_id' => $this->cdn_id,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'formatted_file_size' => $this->formatted_file_size,
            'estimated_download_time' => $this->estimated_download_time,
            'formatted_estimated_time' => $this->formatted_estimated_time,
            'storage_disk' => $this->storage_disk,
            'storage_file_path' => $this->storage_file_path,
            'quality' => $this->quality,
            'download_cdn_url' => $this->download_cdn_url,
            'download_url' => $this->getDownloadUrl(),
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->getLabel(),
                'color' => $this->status->getColor(),
            ],
            'type' => [
                'value' => $this->getType()->value,
                'label' => $this->getType()->getLabel(),
            ],
            'is_available' => $this->isAvailable(),
            'is_stored_locally' => $this->isStoredLocally(),
            'is_on_cdn' => $this->isOnCdn(),
            'is_audio_only' => $this->isAudioOnly(),
            'is_video_only' => $this->isVideoOnly(),
            'is_full' => $this->isFull(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}