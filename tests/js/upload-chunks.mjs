// Behavioural harness for noerdChunkUploads(): the dropzone must never hand
// PHP a request it refuses — more files than `max_file_uploads` or more bytes
// than `post_max_size` — because such a request fails without an error the
// page can show.
//
// Run by DropzoneChunkingTest.php (node <this file>); exits non-zero on failure.
import assert from 'node:assert/strict';

const { noerdChunkUploads } = await import('../../resources/js/upload-chunks.js');

const files = (count, size = 1) =>
    Array.from({ length: count }, (_, i) => ({ name: `file-${i}.txt`, size }));

const sizes = (chunks) => chunks.map((chunk) => chunk.length);

assert.deepEqual(
    sizes(noerdChunkUploads(files(40), 20, 0)),
    [20, 20],
    'forty files must go out as two requests of twenty',
);

assert.deepEqual(
    sizes(noerdChunkUploads(files(41), 20, 0)),
    [20, 20, 1],
    'the remainder gets a request of its own',
);

assert.deepEqual(
    sizes(noerdChunkUploads(files(15), 20, 0)),
    [15],
    'a selection within the limit stays one request',
);

assert.deepEqual(
    noerdChunkUploads([], 20, 0),
    [],
    'an empty selection uploads nothing',
);

assert.deepEqual(
    sizes(noerdChunkUploads(files(10, 30), 20, 100)),
    [3, 3, 3, 1],
    'the byte limit splits a selection the file count would have let through',
);

assert.deepEqual(
    sizes(noerdChunkUploads([{ size: 500 }, { size: 1 }], 20, 100)),
    [1, 1],
    'a file over the byte limit gets a request of its own instead of blocking the queue',
);

assert.deepEqual(
    sizes(noerdChunkUploads(files(5), 0, 0)),
    [5],
    'an unknown file limit must not produce empty chunks',
);

const order = noerdChunkUploads(files(25), 20, 0)
    .flat()
    .map((file) => file.name);
assert.deepEqual(order, files(25).map((file) => file.name), 'the selection order survives');

// The queue: errors and cancellations always end the upload, a second
// selection during an upload is queued.
const { noerdUploadQueue } = await import('../../resources/js/upload-chunks.js');

{
    const sent = [];
    let release;
    const states = [];
    const queue = noerdUploadQueue({
        maxFiles: 2,
        maxBytes: 0,
        send: (batch) => {
            sent.push(batch.map((file) => file.name));
            return sent.length === 1 ? new Promise((resolve) => { release = resolve; }) : Promise.resolve();
        },
        onChange: (state) => states.push(state),
    });

    const first = queue.add(files(2));
    assert.equal(queue.state().uploading, true);

    const second = queue.add([{ name: 'late.txt', size: 1 }]);
    assert.equal(queue.state().totalCount, 3, 'a selection during an upload is counted');

    release();
    await Promise.all([first, second]);

    assert.deepEqual(sent, [['file-0.txt', 'file-1.txt'], ['late.txt']], 'the late selection is uploaded, not dropped');
    assert.equal(queue.state().uploading, false);
}

{
    const queue = noerdUploadQueue({ maxFiles: 1, maxBytes: 0, send: () => Promise.reject('Upload failed') });
    await queue.add(files(3));

    assert.equal(queue.state().uploading, false, 'a failure unlocks the dropzone');
    assert.equal(queue.state().error, 'Upload failed');
}

{
    const sent = [];
    const queue = noerdUploadQueue({
        maxFiles: 1,
        maxBytes: 0,
        send: (batch) => { sent.push(batch); return Promise.reject(''); },
    });
    await queue.add(files(3));

    assert.equal(queue.state().uploading, false, 'a cancellation unlocks the dropzone');
    assert.equal(queue.state().error, '', 'a cancellation shows no error');
    assert.equal(sent.length, 1, 'nothing more is sent after a cancellation');

    // The dropzone takes the next selection again.
    const retry = noerdUploadQueue({ maxFiles: 5, maxBytes: 0, send: () => Promise.resolve() });
    await retry.add(files(2));
    assert.equal(retry.state().error, '');
}
