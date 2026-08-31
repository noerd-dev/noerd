<?php

declare(strict_types=1);

use Noerd\Tests\HelperLoader;

/*
 | The global test helpers must stay out of the production autoload, but must
 | still be reachable from a consuming project regardless of how noerd is
 | installed (composer package vs. git submodule). HelperLoader is that single
 | entry point — these tests pin both halves of the contract.
 */

it('exposes the global test helpers', function (): void {
    HelperLoader::load();

    expect(function_exists('validDetailPayload'))->toBeTrue()
        ->and(function_exists('requiredLayoutFields'))->toBeTrue()
        ->and(function_exists('registerTestLivewireRoute'))->toBeTrue();
});

it('is idempotent', function (): void {
    HelperLoader::load();
    HelperLoader::load();

    expect(function_exists('validDetailPayload'))->toBeTrue();
});

it('resolves the helper file through the autoloader, not through a fixed path', function (): void {
    $loaderFile = (new ReflectionClass(HelperLoader::class))->getFileName();

    expect($loaderFile)->not->toBeFalse()
        ->and(file_exists(dirname((string) $loaderFile) . '/helpers.php'))->toBeTrue();
});

it('keeps the helpers out of the production composer autoload', function (): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);

    expect($composer['autoload']['files'] ?? [])->toBe([])
        ->and($composer['autoload']['psr-4']['Noerd\Tests\\'] ?? null)->toBe('tests/');
});

it('ships the loader and the helpers in dist archives', function (): void {
    $lines = file(dirname(__DIR__, 2) . '/.gitattributes', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    $ignored = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, '#') || ! str_contains($line, 'export-ignore')) {
            continue;
        }
        $ignored[] = ltrim((string) strtok($line, " \t"), '/');
    }

    foreach (['tests/HelperLoader.php', 'tests/helpers.php', 'tests/TestCase.php'] as $path) {
        foreach ($ignored as $pattern) {
            expect($path === $pattern || str_starts_with($path, $pattern . '/'))
                ->toBeFalse("{$path} must ship in dist archives, but is export-ignored by '{$pattern}'");
        }
    }
});
