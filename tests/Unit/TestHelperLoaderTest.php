<?php

declare(strict_types=1);

use Noerd\Testing\HelperLoader;
use Noerd\Tests\TestCase;

uses(TestCase::class);

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

it('keeps the helpers and the package suite out of the production composer autoload', function (): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);

    expect($composer['autoload']['files'] ?? [])->toBe([])
        ->and($composer['autoload']['psr-4'])->not->toHaveKey('Noerd\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Noerd\\Tests\\'] ?? null)->toBe('tests/');

    foreach ($composer['autoload']['psr-4'] as $directory) {
        expect(str_starts_with((string) $directory, 'tests'))->toBeFalse("tests/ must not be in the production autoload ({$directory})");
    }
});

it('ships the shipped test case free of the package-only fixture namespace', function (): void {
    $testCase = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Testing/TestCase.php');

    expect($testCase)->not->toContain('noerd-test')
        ->and($testCase)->not->toContain('/Feature/fixtures');
});

it('keeps the contributor guide out of dist archives', function (): void {
    $attributes = (string) file_get_contents(dirname(__DIR__, 2) . '/.gitattributes');

    expect($attributes)->toMatch('#^/AGENTS\.md\s+export-ignore$#m')
        ->and($attributes)->toMatch('#^/CLAUDE\.md\s+export-ignore$#m');
});

it('ships the test API in dist archives and keeps the package suite out of them', function (): void {
    $lines = file(dirname(__DIR__, 2) . '/.gitattributes', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    $ignored = [];
    foreach ($lines as $line) {
        $line = mb_trim($line);
        if (str_starts_with($line, '#') || ! str_contains($line, 'export-ignore')) {
            continue;
        }
        $ignored[] = mb_ltrim((string) strtok($line, " \t"), '/');
    }

    $isIgnored = static function (string $path) use ($ignored): bool {
        foreach ($ignored as $pattern) {
            if ($path === $pattern || str_starts_with($path, $pattern . '/')) {
                return true;
            }
        }

        return false;
    };

    $shipped = [
        'src/Testing/HelperLoader.php',
        'src/Testing/helpers.php',
        'src/Testing/TestCase.php',
        'src/Testing/ModuleTestCase.php',
        'src/Testing/Concerns/CreatesSetupUser.php',
    ];

    foreach ($shipped as $path) {
        expect(file_exists(dirname(__DIR__, 2) . '/' . $path))->toBeTrue("{$path} is missing")
            ->and($isIgnored($path))->toBeFalse("{$path} must ship in dist archives, but is export-ignored");
    }

    expect($isIgnored('tests/TestCase.php'))->toBeTrue('tests/ must be export-ignored as a whole')
        ->and($isIgnored('tests/Feature/fixtures/components/theme-test.blade.php'))->toBeTrue();
});
