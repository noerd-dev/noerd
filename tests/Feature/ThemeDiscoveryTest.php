<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Services\ThemeRegistry;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | Theme folders are discovered from registered roots: a directory containing a
 | theme.yml defines a theme, higher-priority roots win name collisions and the
 | element templates resolve through the themes:: namespace with a per-element
 | fallback to the default theme.
 */

beforeEach(function (): void {
    $this->admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $this->actingAs($this->admin);

    $this->themesRoot = storage_path('framework/testing/zz-themes');
    File::deleteDirectory($this->themesRoot);
});

afterEach(function (): void {
    File::deleteDirectory($this->themesRoot);
});

function writeFixtureTheme(string $root, string $name, array $yaml = [], array $elements = []): void
{
    File::ensureDirectoryExists("{$root}/{$name}");

    $lines = [];
    foreach ($yaml as $key => $value) {
        $lines[] = is_bool($value) ? "{$key}: " . ($value ? 'true' : 'false') : "{$key}: '{$value}'";
    }
    File::put("{$root}/{$name}/theme.yml", implode("\n", $lines));

    foreach ($elements as $element => $content) {
        File::put("{$root}/{$name}/{$element}.blade.php", $content);
    }
}

it('discovers a theme folder registered at runtime', function (): void {
    writeFixtureTheme($this->themesRoot, 'clientx', [
        'label' => 'Client X',
        'gridClasses' => 'py-1 gap-2',
        'numbersRows' => true,
    ]);

    $registry = app(ThemeRegistry::class);
    $registry->registerPath($this->themesRoot);

    expect($registry->has('clientx'))->toBeTrue()
        ->and($registry->get('clientx')->label)->toBe('Client X')
        ->and($registry->get('clientx')->gridClasses)->toBe('py-1 gap-2')
        ->and($registry->get('clientx')->numbersRows)->toBeTrue();
});

it('renders a discovered theme with its own element template and default fallback', function (): void {
    writeFixtureTheme(
        $this->themesRoot,
        'clientx',
        ['label' => 'Client X', 'gridClasses' => 'py-1'],
        // Own input template; every other element falls back to the default theme.
        ['input' => '<div data-clientx-input>{{ $field["label"] ?? "" }}</div>'],
    );

    app(ThemeRegistry::class)->registerPath($this->themesRoot);

    Livewire::test('noerd::theme-test', ['initialModel' => [], 'theme' => 'clientx'])
        ->assertSuccessful()
        ->assertSeeHtml('data-theme="clientx"')
        // Text fields use the theme's own template …
        ->assertSeeHtml('data-clientx-input')
        // … while the select element falls back to the default theme's markup.
        ->assertSeeHtml('<select');
});

it('lets a higher-priority root win the theme.yml of the same name', function (): void {
    writeFixtureTheme("{$this->themesRoot}/low", 'shared', ['label' => 'Low', 'gridClasses' => 'py-9']);
    writeFixtureTheme("{$this->themesRoot}/high", 'shared', ['label' => 'High', 'gridClasses' => 'py-1']);

    $registry = app(ThemeRegistry::class);
    $registry->registerPath("{$this->themesRoot}/low", priority: 10);
    $registry->registerPath("{$this->themesRoot}/high", priority: 90);

    expect($registry->get('shared')->label)->toBe('High')
        ->and($registry->get('shared')->gridClasses)->toBe('py-1');
});

it('can override a built-in theme from a higher-priority root', function (): void {
    writeFixtureTheme($this->themesRoot, 'compact', ['label' => 'Project Compact', 'gridClasses' => 'py-0']);

    $registry = app(ThemeRegistry::class);
    $registry->registerPath($this->themesRoot, priority: 100);

    expect($registry->get('compact')->label)->toBe('Project Compact')
        ->and($registry->get('compact')->gridClasses)->toBe('py-0');
});

it('ignores folders without a theme.yml and missing roots', function (): void {
    File::ensureDirectoryExists("{$this->themesRoot}/not-a-theme");

    $registry = app(ThemeRegistry::class);
    $registry->registerPath($this->themesRoot);
    $registry->registerPath($this->themesRoot . '/does-not-exist');

    expect($registry->has('not-a-theme'))->toBeFalse()
        ->and($registry->has('default'))->toBeTrue();
});

it('re-discovers after clearCache picks up new theme folders', function (): void {
    $registry = app(ThemeRegistry::class);
    $registry->registerPath($this->themesRoot);

    expect($registry->has('latecomer'))->toBeFalse();

    writeFixtureTheme($this->themesRoot, 'latecomer', ['label' => 'Late']);
    $registry->clearCache();

    expect($registry->has('latecomer'))->toBeTrue();
});
