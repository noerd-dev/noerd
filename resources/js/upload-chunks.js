/**
 * Split a file selection into the batches ONE upload request may carry.
 *
 * The browser posts the whole selection to Livewire at once, and PHP turns the
 * request away when it holds more than `max_file_uploads` files or more than
 * `post_max_size` bytes — not with an error the page can show, but with a
 * warning in place of the response, so the upload fails without a trace. The
 * limits therefore have to be honoured before the request is sent.
 *
 * A single file larger than the byte limit gets a batch of its own: it cannot
 * be made to fit, and the server's size validation is what should reject it.
 *
 * @param {File[]} files    the selection, in the order it was given
 * @param {number} maxFiles files per request (PHP `max_file_uploads`)
 * @param {number} maxBytes bytes per request, `0` for no limit
 * @returns {File[][]}
 */
export function noerdChunkUploads(files, maxFiles, maxBytes) {
    const limit = maxFiles > 0 ? maxFiles : files.length;
    const chunks = [];

    let chunk = [];
    let bytes = 0;

    for (const file of files) {
        const full = chunk.length >= limit;
        const tooLarge = maxBytes > 0 && chunk.length > 0 && bytes + file.size > maxBytes;

        if (full || tooLarge) {
            chunks.push(chunk);
            chunk = [];
            bytes = 0;
        }

        chunk.push(file);
        bytes += file.size;
    }

    if (chunk.length > 0) {
        chunks.push(chunk);
    }

    return chunks;
}
