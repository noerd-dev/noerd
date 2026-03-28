<?php

namespace Noerd\Contracts;

interface MediaResolverContract
{
    /**
     * Get a preview URL for a media item by its ID.
     */
    public function getPreviewUrl(int $mediaId): ?string;

    /**
     * Check if a media item exists.
     */
    public function exists(int $mediaId): bool;

    /**
     * Get a relative URL (without domain) for a media item.
     */
    public function getRelativeUrl(int $mediaId): ?string;

    /**
     * Store an uploaded file and return its relative URL.
     */
    public function storeUploadedFile(mixed $uploadedFile): ?string;
}
