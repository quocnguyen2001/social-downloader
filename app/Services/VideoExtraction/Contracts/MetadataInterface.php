<?php

namespace App\Services\VideoExtraction\Contracts;

/**
 * Interface for video metadata handling.
 *
 * This interface defines the contract for accessing and manipulating
 * video metadata extracted from various platforms.
 */
interface MetadataInterface
{
    /**
     * Get the video title.
     *
     * @return string|null The video title or null if not available
     */
    public function getTitle(): ?string;

    /**
     * Get the video thumbnail URL.
     *
     * @return string|null The thumbnail URL or null if not available
     */
    public function getThumbnail(): ?string;

    /**
     * Get the video duration in seconds.
     *
     * @return int|null The duration in seconds or null if not available
     */
    public function getDuration(): ?int;

    /**
     * Get the video ID from the platform.
     *
     * @return string|null The video ID or null if not available
     */
    public function getVideoId(): ?string;

    /**
     * Get the estimated file size in bytes.
     *
     * @return int|null The file size in bytes or null if not available
     */
    public function getFileSize(): ?int;

    /**
     * Get the video description.
     *
     * @return string|null The video description or null if not available
     */
    public function getDescription(): ?string;

    /**
     * Get the video author/uploader name.
     *
     * @return string|null The author name or null if not available
     */
    public function getAuthor(): ?string;

    /**
     * Get the video upload date.
     *
     * @return \DateTimeInterface|null The upload date or null if not available
     */
    public function getUploadDate(): ?\DateTimeInterface;

    /**
     * Get the view count.
     *
     * @return int|null The view count or null if not available
     */
    public function getViewCount(): ?int;

    /**
     * Get additional metadata as an associative array.
     *
     * @return array<string, mixed> Additional metadata
     */
    public function getAdditionalMetadata(): array;

    /**
     * Set the video title.
     *
     * @param  string|null  $title  The video title
     */
    public function setTitle(?string $title): self;

    /**
     * Set the video thumbnail URL.
     *
     * @param  string|null  $thumbnail  The thumbnail URL
     */
    public function setThumbnail(?string $thumbnail): self;

    /**
     * Set the video duration.
     *
     * @param  int|null  $duration  The duration in seconds
     */
    public function setDuration(?int $duration): self;

    /**
     * Set the video ID.
     *
     * @param  string|null  $videoId  The video ID
     */
    public function setVideoId(?string $videoId): self;

    /**
     * Set the file size.
     *
     * @param  int|null  $fileSize  The file size in bytes
     */
    public function setFileSize(?int $fileSize): self;
}
