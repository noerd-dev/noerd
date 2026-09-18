<?php

declare(strict_types=1);

namespace Noerd\Support;

/**
 * What ONE upload request may carry, read from the PHP configuration.
 *
 * A browser hands the whole selection to Livewire in a single POST, so the
 * server's own limits decide how much fits: `max_file_uploads` caps the number
 * of files (PHP answers the request with a warning instead of a response once
 * it is exceeded — the upload fails silently in the browser), `post_max_size`
 * caps their combined size. The dropzone reads both and splits a larger
 * selection into consecutive requests, so a reader may drop forty files at
 * once on a server that accepts twenty.
 */
final class UploadLimits
{
    /**
     * Files per request. `max_file_uploads` is a hard PHP limit, not a
     * validation rule — nothing in the application can raise it.
     */
    public static function maxFilesPerRequest(): int
    {
        $limit = (int) ini_get('max_file_uploads');

        return $limit > 0 ? $limit : 20;
    }

    /**
     * Bytes per request, with a margin for the rest of the multipart body
     * (field names, boundaries, the Livewire payload). `0` means the server
     * sets no limit.
     */
    public static function maxBytesPerRequest(): int
    {
        $postMax = self::bytes((string) ini_get('post_max_size'));

        if ($postMax <= 0) {
            return 0;
        }

        return max(1, $postMax - (int) max(524288, $postMax * 0.05));
    }

    /**
     * Parse a PHP shorthand size (`200M`, `8K`, `1G`) into bytes. A plain
     * number is already bytes; `0` and `-1` mean "no limit".
     */
    public static function bytes(string $value): int
    {
        $value = mb_trim($value);

        if ($value === '') {
            return 0;
        }

        $number = (float) $value;

        if ($number <= 0) {
            return 0;
        }

        return match (mb_strtolower(mb_substr($value, -1))) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }
}
