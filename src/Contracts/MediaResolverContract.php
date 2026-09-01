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

    /**
     * Whether the full media module is available.
     */
    public function isAvailable(): bool;

    /**
     * The list component opened as the media picker (`selectMode`,
     * `selectContext`, `selectToken` arguments; answers with `mediaSelected`),
     * or null when no library exists.
     */
    public function pickerComponent(): ?string;
}
