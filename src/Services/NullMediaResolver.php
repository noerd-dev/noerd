<?php

namespace Noerd\Services;

use Illuminate\Support\Facades\Storage;
use Noerd\Contracts\MediaResolverContract;

class NullMediaResolver implements MediaResolverContract
{
    public function getPreviewUrl(int $mediaId): ?string
    {
        return null;
    }

    public function exists(int $mediaId): bool
    {
        return false;
    }

    public function getRelativeUrl(int $mediaId): ?string
    {
        return null;
    }

    public function storeUploadedFile(mixed $uploadedFile): ?string
    {
        if (! $uploadedFile) {
            return null;
        }

        $path = $uploadedFile->store('uploads', 'public');

        return '/storage/' . $path;
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
