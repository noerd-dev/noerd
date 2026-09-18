<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Resolves one entry of the dropzone's `$files` array back to the upload it
 * describes.
 *
 * The dropzone hands consumers a plain array (name, extension, size, …) so a
 * component can render a file list without touching the upload object. That
 * array lives in a PUBLIC Livewire property, and Livewire lets the client
 * rewrite public properties wholesale — so every scalar in it is attacker
 * controlled. A consumer that reads a file system path out of it and calls
 * file_get_contents() on it hands any authenticated user an arbitrary file
 * read (.env, keys, whatever the PHP process can open).
 *
 * The only trustworthy member is `_original`: Livewire serialises a temporary
 * upload as a signed `livewire-file:…` reference, and
 * unserializeFromLivewireRequest() resolves it exclusively inside Livewire's
 * temporary upload directory. That is why the array carries no path any more
 * and why every consumer goes through this class.
 */
final class DropzoneFile
{
    /**
     * The upload behind one dropzone entry, or null when the entry does not
     * describe a real, still-present upload.
     *
     * @param  mixed  $file  One element of the dropzone's `$files` array.
     */
    public static function resolve(mixed $file): ?UploadedFile
    {
        $upload = is_array($file) ? ($file['_original'] ?? null) : $file;

        if (is_string($upload)) {
            $upload = TemporaryUploadedFile::unserializeFromLivewireRequest($upload);
        }

        if (! $upload instanceof UploadedFile) {
            return null;
        }

        if ($upload instanceof TemporaryUploadedFile && ! $upload->exists()) {
            return null;
        }

        return $upload;
    }

    /**
     * Every resolvable upload of a dropzone `$files` array, unresolvable
     * entries dropped.
     *
     * @param  mixed  $files
     * @return array<int, UploadedFile>
     */
    public static function resolveAll(mixed $files): array
    {
        if (! is_array($files)) {
            return [];
        }

        $resolved = [];

        foreach ($files as $file) {
            $upload = self::resolve($file);

            if ($upload instanceof UploadedFile) {
                $resolved[] = $upload;
            }
        }

        return $resolved;
    }

    /**
     * A read stream for the upload's bytes. The caller closes it.
     *
     * @return resource|null
     */
    public static function stream(UploadedFile $upload)
    {
        $stream = fopen($upload->getRealPath(), 'r');

        return is_resource($stream) ? $stream : null;
    }
}
