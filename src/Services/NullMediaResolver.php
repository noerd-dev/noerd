<?php

declare(strict_types=1);

namespace Noerd\Services;

use Illuminate\Support\Str;
use Noerd\Contracts\MediaResolverContract;

final class NullMediaResolver implements MediaResolverContract
{
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    private const MAX_BYTES = 10 * 1024 * 1024;

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

        // The fallback resolver stores to the PUBLIC disk, so anything served
        // inline as active content (SVG, HTML) would be stored XSS on the app
        // origin. Accept only raster images (by server-detected mime, not the
        // client extension), cap the size, and force a safe extension + random
        // name so the original filename can never traverse or execute.
        $mime = $uploadedFile->getMimeType();
        if (! isset(self::ALLOWED_MIMES[$mime]) || $uploadedFile->getSize() > self::MAX_BYTES) {
            return null;
        }

        $path = $uploadedFile->storeAs('uploads', Str::uuid() . '.' . self::ALLOWED_MIMES[$mime], 'public');

        return '/storage/' . $path;
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function pickerComponent(): ?string
    {
        return null;
    }
}
