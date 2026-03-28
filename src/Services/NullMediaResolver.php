<?php

namespace Noerd\Services;

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
        return null;
    }
}
