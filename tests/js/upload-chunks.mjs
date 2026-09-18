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
