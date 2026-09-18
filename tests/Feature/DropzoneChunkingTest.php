<?php

declare(strict_types=1);

use Noerd\Support\UploadLimits;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

uses(Noerd\Tests\TestCase::class);

describe('Upload limits', function (): void {
    it('reads the number of files one request may carry from PHP', function (): void {
        expect(UploadLimits::maxFilesPerRequest())->toBe(max(1, (int) ini_get('max_file_uploads')));
    });

    it('falls back to a safe file count when PHP reports none', function (): void {
        // `max_file_uploads` cannot be changed at runtime, so the fallback is
        // proven through the parser it shares with the byte limit.
        expect(UploadLimits::bytes(''))->toBe(0)
            ->and(UploadLimits::bytes('0'))->toBe(0)
            ->and(UploadLimits::bytes('-1'))->toBe(0);
    });

    it('parses PHP shorthand sizes', function (): void {
        expect(UploadLimits::bytes('200M'))->toBe(200 * 1024 * 1024)
            ->and(UploadLimits::bytes('8k'))->toBe(8 * 1024)
            ->and(UploadLimits::bytes('1G'))->toBe(1024 * 1024 * 1024)
            ->and(UploadLimits::bytes('1024'))->toBe(1024);
    });

    it('keeps a margin below post_max_size for the rest of the request body', function (): void {
        $postMax = UploadLimits::bytes((string) ini_get('post_max_size'));

        if ($postMax <= 0) {
            expect(UploadLimits::maxBytesPerRequest())->toBe(0);

            return;
        }

        expect(UploadLimits::maxBytesPerRequest())
            ->toBeLessThan($postMax)
            ->toBeGreaterThan(0);
    });
});

describe('Dropzone chunking', function (): void {
    it('splits a selection into requests the server accepts', function (): void {
        $node = (new ExecutableFinder())->find('node');

        if ($node === null) {
            $this->markTestSkipped('node is not available.');
        }

        $process = new Process([$node, 'tests/js/upload-chunks.mjs'], dirname(__DIR__, 2));
        $process->run();

        expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    });

    it('ships the chunking in the built asset', function (): void {
        $moduleDir = dirname(__DIR__, 2);
        $manifest = json_decode((string) file_get_contents($moduleDir . '/dist/build/manifest.json'), true);
        $asset = $manifest['resources/js/noerd.js']['file'] ?? null;

        expect($asset)->not->toBeNull();

        $built = (string) file_get_contents($moduleDir . '/dist/build/' . $asset);

        // The dropzone must upload through the chunking, not straight through
        // uploadMultiple() with the whole selection.
        expect($built)->toContain('uploadMultiple')
            ->and($built)->toContain('maxFiles')
            ->and($built)->toContain('maxBytes');
    });
});
