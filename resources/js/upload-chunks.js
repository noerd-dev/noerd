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

/**
 * The upload queue of one dropzone: every selection is split into batches
 * (noerdChunkUploads) and sent one batch after the other through `send`.
 *
 * - A selection added while an upload runs is QUEUED and uploaded right after,
 *   never dropped.
 * - A batch that fails or is cancelled ends the run: `uploading` resets, the
 *   rest of the queue is discarded and `error` carries the reason (empty for a
 *   cancellation, which needs no message).
 *
 * `send(batch)` returns a promise that resolves when the batch arrived and
 * rejects with the message to show (or an empty string) otherwise. `onChange`
 * receives a copy of the state after every step.
 *
 * @param {{maxFiles: number, maxBytes: number, send: (batch: File[]) => Promise<void>, onChange?: (state: {uploading: boolean, uploadedCount: number, totalCount: number, error: string}) => void}} options
 */
export function noerdUploadQueue({ maxFiles, maxBytes, send, onChange = () => {} }) {
    const pending = [];
    const state = { uploading: false, uploadedCount: 0, totalCount: 0, error: '' };
    let running = Promise.resolve();

    const emit = () => onChange({ ...state });

    async function run() {
        state.uploading = true;
        emit();

        try {
            while (pending.length > 0) {
                const selection = pending.splice(0, pending.length);

                for (const batch of noerdChunkUploads(selection, maxFiles, maxBytes)) {
                    await send(batch);
                    state.uploadedCount += batch.length;
                    emit();
                }
            }
        } catch (reason) {
            pending.length = 0;
            state.error = typeof reason === 'string' ? reason : '';
        } finally {
            state.uploading = false;
            state.uploadedCount = 0;
            state.totalCount = 0;
            emit();
        }
    }

    return {
        /**
         * @param {File[]} files
         * @returns {Promise<void>} settles once everything queued so far is done
         */
        add(files) {
            const selection = Array.from(files || []);

            if (selection.length === 0) {
                return running;
            }

            if (! state.uploading) {
                state.error = '';
                state.uploadedCount = 0;
                state.totalCount = 0;
            }

            pending.push(...selection);
            state.totalCount += selection.length;

            if (state.uploading) {
                emit();

                return running;
            }

            running = run();

            return running;
        },
        state: () => ({ ...state }),
    };
}
