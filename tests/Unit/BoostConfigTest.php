<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Noerd\Support\BoostConfig;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | BoostConfig is the only writer noerd has for the host's boost.json. It must
 | only ever ADD entries — Boost reads the file as its package selection, so a
 | lost entry silently drops a package's rendered guidelines.
 */
beforeEach(function (): void {
    $this->dir = storage_path('framework/testing/zz-boost-config');
    File::deleteDirectory($this->dir);
    File::ensureDirectoryExists($this->dir);
    $this->path = $this->dir . '/boost.json';
});

afterEach(function (): void {
    File::deleteDirectory($this->dir);
});

function zzWriteBoostJson(string $path, array $config): void
{
    File::put($path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

it('appends missing packages and skills and reports what it added', function (): void {
    zzWriteBoostJson($this->path, ['guidelines' => true, 'packages' => ['spatie/laravel-pdf'], 'skills' => ['laravel-pdf']]);

    $added = (new BoostConfig($this->path))->ensure(['noerd/noerd'], ['noerd-testing', 'laravel-pdf']);

    expect($added)->toBe(['packages' => ['noerd/noerd'], 'skills' => ['noerd-testing']]);

    $config = new BoostConfig($this->path);
    expect($config->packages())->toBe(['spatie/laravel-pdf', 'noerd/noerd'])
        ->and($config->skills())->toBe(['laravel-pdf', 'noerd-testing']);
});

it('is idempotent and leaves the file bytes untouched on a repeated call', function (): void {
    zzWriteBoostJson($this->path, ['packages' => ['noerd/noerd'], 'skills' => ['noerd-testing']]);
    $before = File::get($this->path);

    $added = (new BoostConfig($this->path))->ensure(['noerd/noerd'], ['noerd-testing']);

    expect($added)->toBe(['packages' => [], 'skills' => []])
        ->and(File::get($this->path))->toBe($before);
});

it('never removes entries it does not know and keeps the key order', function (): void {
    // Deliberately NOT alphabetical: Boost's own writer ksorts, a hand-edited file may not.
    File::put($this->path, "{\n    \"skills\": [\n        \"foreign-skill\"\n    ],\n    \"agents\": [\n        \"claude_code\"\n    ],\n    \"packages\": [\n        \"vendor/other\"\n    ]\n}\n");

    (new BoostConfig($this->path))->ensure(['noerd/noerd'], []);

    $config = new BoostConfig($this->path);
    expect($config->packages())->toBe(['vendor/other', 'noerd/noerd'])
        ->and($config->skills())->toBe(['foreign-skill'])
        ->and(array_keys($config->all()))->toBe(['skills', 'agents', 'packages']);
});

it('writes in the format Boost uses: four-space pretty print, unescaped slashes, trailing newline', function (): void {
    zzWriteBoostJson($this->path, ['guidelines' => true, 'packages' => []]);

    (new BoostConfig($this->path))->ensure(['noerd/noerd'], ['noerd-testing']);

    $expected = ['guidelines' => true, 'packages' => ['noerd/noerd'], 'skills' => ['noerd-testing']];

    expect(File::get($this->path))->toBe(json_encode($expected, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
});

it('creates a missing list key but never a missing file', function (): void {
    zzWriteBoostJson($this->path, ['guidelines' => true]);
    (new BoostConfig($this->path))->ensure(['noerd/noerd'], []);
    expect((new BoostConfig($this->path))->packages())->toBe(['noerd/noerd']);

    $missing = new BoostConfig($this->dir . '/nope.json');
    expect($missing->exists())->toBeFalse()
        ->and($missing->ensure(['noerd/noerd'], []))->toBe(['packages' => [], 'skills' => []])
        ->and(File::exists($this->dir . '/nope.json'))->toBeFalse();
});

it('does not overwrite a file that is not a JSON object', function (): void {
    File::put($this->path, "{ not json\n");

    $config = new BoostConfig($this->path);

    expect($config->exists())->toBeTrue()
        ->and($config->isValid())->toBeFalse()
        ->and($config->ensure(['noerd/noerd'], []))->toBe(['packages' => [], 'skills' => []])
        ->and(File::get($this->path))->toBe("{ not json\n");
});
