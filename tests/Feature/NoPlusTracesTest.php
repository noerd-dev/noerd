<?php

declare(strict_types=1);

use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | The open-source core must not hint at any commercial extension: whoever
 | installs noerd alone must not be able to tell from the source that such a
 | package (or its permission-grant feature) exists. This guard scans every
 | shipped file — tests excluded, they may exercise extension points.
 */
it('ships no extension-package traces in the distributed sources', function (): void {
    $root = dirname(__DIR__, 2);
    $needles = ['noerd-plus', 'noerd_permissions', 'NoerdPlus', 'noerd/plus'];

    $violations = [];
    foreach (['src', 'docs', 'resources', 'database', 'config', 'routes', 'stubs', 'app-configs'] as $dir) {
        $path = $root . '/' . $dir;
        if (! is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (! in_array($file->getExtension(), ['php', 'md', 'stub', 'yml', 'yaml', 'json', 'js', 'css'], true)) {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());
            foreach ($needles as $needle) {
                if (mb_stripos($content, $needle) !== false) {
                    $violations[] = mb_substr($file->getPathname(), mb_strlen($root) + 1) . " contains '{$needle}'";
                }
            }
        }
    }

    expect($violations)->toBe([]);
});
