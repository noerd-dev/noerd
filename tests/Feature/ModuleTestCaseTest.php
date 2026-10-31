<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Noerd\Providers\NoerdServiceProvider;
use Noerd\Tests\ModuleTestCase;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | ModuleTestCase adds a module's providers to the core providers and links
 | every listed module into the testbench skeleton. Proven with a module
 | written at runtime — its provider class is declared from that file.
 */

beforeEach(function (): void {
    $this->root = storage_path('framework/testing/zz-module-case-' . uniqid());
    File::ensureDirectoryExists($this->root . '/src/Providers');
    File::put($this->root . '/composer.json', '{"name": "zz/case"}');

    if (! class_exists('ZzCase\Providers\ZzCaseServiceProvider')) {
        File::put($this->root . '/src/Providers/ZzCaseServiceProvider.php', implode("\n", [
            '<?php',
            'namespace ZzCase\Providers;',
            'class ZzCaseServiceProvider extends \Illuminate\Support\ServiceProvider {}',
        ]));
        require_once $this->root . '/src/Providers/ZzCaseServiceProvider.php';
    }

    $this->case = new class ('zz') extends ModuleTestCase {
        protected array $moduleProviders = ['ZzCase\Providers\ZzCaseServiceProvider'];
    };
});

afterEach(function (): void {
    $link = base_path('app-modules/' . basename($this->root));
    if (is_link($link)) {
        unlink($link);
    }

    File::deleteDirectory($this->root);
});

it('boots the module providers after the core providers', function (): void {
    $providers = \Livewire\invade($this->case)->getPackageProviders($this->app);

    expect($providers)->toContain(NoerdServiceProvider::class, 'ZzCase\Providers\ZzCaseServiceProvider')
        ->and(array_search(NoerdServiceProvider::class, $providers, true))
        ->toBeLessThan(array_search('ZzCase\Providers\ZzCaseServiceProvider', $providers, true));
});

it('derives the module root from the provider location and links it into the skeleton', function (): void {
    // The provider class was declared from the FIRST module directory of this
    // process; later tests write new directories, so the root is read off the
    // declared class, not off $this->root.
    $declaredRoot = dirname((string) (new ReflectionClass('ZzCase\Providers\ZzCaseServiceProvider'))->getFileName(), 3);
    File::ensureDirectoryExists($declaredRoot);
    File::put($declaredRoot . '/composer.json', '{"name": "zz/case"}');

    $roots = \Livewire\invade($this->case)->moduleRoots();
    expect($roots)->toBe([realpath($declaredRoot)]);

    $link = base_path('app-modules/' . basename($declaredRoot));

    try {
        \Livewire\invade($this->case)->linkModuleIntoSkeleton($roots[0]);

        expect(is_link($link))->toBeTrue()
            ->and(realpath($link))->toBe(realpath($declaredRoot));

        // Linking again leaves the existing link alone.
        \Livewire\invade($this->case)->linkModuleIntoSkeleton($roots[0]);
        expect(is_link($link))->toBeTrue();
    } finally {
        if (is_link($link)) {
            unlink($link);
        }
        if ($declaredRoot !== $this->root) {
            File::deleteDirectory($declaredRoot);
        }
    }
});
