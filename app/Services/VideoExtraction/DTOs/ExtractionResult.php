<?php

namespace App\Services\VideoExtraction\DTOs;

use App\Enums\Platform;
use App\Enums\VideoFormat;
use App\Enums\VideoQuality;
use App\Services\VideoExtraction\Contracts\MetadataInterface;

/**
 * Data Transfer Object for video extraction results.
 *
 * This class encapsulates all the data extracted from a video URL,
 * including metadata and download information.
 */
class ExtractionResult implements MetadataInterface
{
    public function __construct(
        private ?string $title = null,
        private ?string $thumbnailUrl = null,
        private ?string $thumbnailDisk = null,
        private ?string $thumbnailPath = null,
        private ?int $duration = null,
        private ?string $videoId = null,
        private ?int $fileSize = null,
        private ?string $downloadUrl = null,
        private ?Platform $platform = null,
        private ?VideoQuality $quality = null,
        private ?VideoFormat $format = null,
        private ?string $description = null,
        private ?string $author = null,
        private ?\DateTimeInterface $uploadDate = null,
        private ?int $viewCount = null,
        private array $additionalMetadata = []
    ) {}

    /**
     * Create a new ExtractionResult instance.
     *
     * @param  array  $data  The extraction data
     */
    public static function create(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            thumbnailUrl: $data['thumbnail_url'] ?? null,
            thumbnailDisk: $data['thumbnail_disk'] ?? null,
            thumbnailPath: $data['thumbnail_path'] ?? null,
            duration: $data['duration'] ?? null,
            videoId: $data['video_id'] ?? null,
            fileSize: $data['file_size'] ?? null,
            downloadUrl: $data['download_url'] ?? null,
            platform: $data['platform'] ?? null,
            quality: $data['quality'] ?? null,
            format: $data['format'] ?? null,
            description: $data['description'] ?? null,
            author: $data['author'] ?? null,
            uploadDate: $data['upload_date'] ?? null,
            viewCount: $data['view_count'] ?? null,
            additionalMetadata: $data['additional_metadata'] ?? []
        );
    }

    /**
     * Convert the result to an array suitable for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'thumbnail_url' => $this->thumbnailUrl,
            'thumbnail_disk' => $this->thumbnailDisk,
            'thumbnail_path' => $this->thumbnailPath,
            'duration' => $this->duration,
            'video_id' => $this->videoId,
            'file_size' => $this->fileSize,
            'download_url' => $this->downloadUrl,
            'platform' => $this->platform?->value,
            'quality' => $this->quality?->value,
            'format' => $this->format?->value,
            'description' => $this->description,
            'author' => $this->author,
            'upload_date' => $this->uploadDate?->format('Y-m-d H:i:s'),
            'view_count' => $this->viewCount,
            'additional_metadata' => $this->additionalMetadata,
        ];
    }

    // MetadataInterface implementation
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Get thumbnail URL (backward compatibility).
     *
     * @deprecated Use getThumbnailDisk() and getThumbnailPath() instead
     */
    public function getThumbnail(): ?string
    {
        // If thumbnailUrl is set, return it
        if ($this->thumbnailUrl) {
            return $this->thumbnailUrl;
        }

        // If disk and path are set, generate URL
        if ($this->thumbnailDisk && $this->thumbnailPath) {
            try {
                return \Storage::disk($this->thumbnailDisk)->url($this->thumbnailPath);
            } catch (\Exception $e) {
                \Log::warning('Failed to generate thumbnail URL from disk/path', [
                    'disk' => $this->thumbnailDisk,
                    'path' => $this->thumbnailPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function getVideoId(): ?string
    {
        return $this->videoId;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function getUploadDate(): ?\DateTimeInterface
    {
        return $this->uploadDate;
    }

    public function getViewCount(): ?int
    {
        return $this->viewCount;
    }

    public function getAdditionalMetadata(): array
    {
        return $this->additionalMetadata;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Set thumbnail URL (backward compatibility).
     *
     * @deprecated Use setThumbnailDisk() and setThumbnailPath() instead
     */
    public function setThumbnail(?string $thumbnail): self
    {
        $this->thumbnailUrl = $thumbnail;

        return $this;
    }

    /**
     * Get thumbnail storage disk.
     */
    public function getThumbnailDisk(): ?string
    {
        return $this->thumbnailDisk;
    }

    /**
     * Get thumbnail storage path.
     */
    public function getThumbnailPath(): ?string
    {
        return $this->thumbnailPath;
    }

    /**
     * Set thumbnail storage disk.
     */
    public function setThumbnailDisk(?string $disk): self
    {
        $this->thumbnailDisk = $disk;

        return $this;
    }

    /**
     * Set thumbnail storage path.
     */
    public function setThumbnailPath(?string $path): self
    {
        $this->thumbnailPath = $path;

        return $this;
    }

    public function setDuration(?int $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    public function setVideoId(?string $videoId): self
    {
        $this->videoId = $videoId;

        return $this;
    }

    public function setFileSize(?int $fileSize): self
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    // Additional getters for properties not in MetadataInterface
    public function getDownloadUrl(): ?string
    {
        return $this->downloadUrl;
    }

    public function getPlatform(): ?Platform
    {
        return $this->platform;
    }

    public function getQuality(): ?VideoQuality
    {
        return $this->quality;
    }

    public function getFormat(): ?VideoFormat
    {
        return $this->format;
    }

    // Additional setters
    public function setDownloadUrl(?string $downloadUrl): self
    {
        $this->downloadUrl = $downloadUrl;

        return $this;
    }

    public function setPlatform(?Platform $platform): self
    {
        $this->platform = $platform;

        return $this;
    }

    public function setQuality(?VideoQuality $quality): self
    {
        $this->quality = $quality;

        return $this;
    }

    public function setFormat(?VideoFormat $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function setAuthor(?string $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function setUploadDate(?\DateTimeInterface $uploadDate): self
    {
        $this->uploadDate = $uploadDate;

        return $this;
    }

    public function setViewCount(?int $viewCount): self
    {
        $this->viewCount = $viewCount;

        return $this;
    }

    public function setAdditionalMetadata(array $additionalMetadata): self
    {
        $this->additionalMetadata = $additionalMetadata;

        return $this;
    }
}
