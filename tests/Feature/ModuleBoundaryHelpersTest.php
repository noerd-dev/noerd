<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Noerd\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;

uses(TestCase::class);

/*
 | The module boundary guards of tests/helpers.php, proven against a modules
 | root written at runtime: a module, the sibling it declares, an optional
 | sibling it may reference without declaring, and one it must never meet.
 */

/**
 * @param  array<string, mixed>  $composer
 */
function zzWriteBoundaryModule(string $dir, string $name, string $namespace, array $composer = []): void
{
    File::ensureDirectoryExists($dir . '/src');
    File::put($dir . '/composer.json', json_encode(array_merge([
        'name' => $name,
        'autoload' => ['psr-4' => [$namespace . '\\' => 'src/']],
    ], $composer), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

beforeEach(function (): void {
    $this->root = storage_path('framework/testing/zz-boundary-' . uniqid());
    $this->module = $this->root . '/zz-module';

    zzWriteBoundaryModule($this->root . '/zz-other', 'zz/other', 'ZzOther');
    zzWriteBoundaryModule($this->root . '/zz-optional', 'zz/optional', 'ZzOptional');
    zzWriteBoundaryModule($this->root . '/zz-forbidden', 'zz/forbidden', 'ZzForbidden');
    zzWriteBoundaryModule($this->module, 'zz/module', 'ZzModule', [
        'require' => ['noerd/noerd' => '*', 'zz/other' => '*'],
        'extra' => ['noerd' => ['boundary' => [
            'forbidden' => ['ZzForbidden\\'],
            'allowed' => ['zz/optional' => 'class_exists-guarded optional integration'],
        ]]],
    ]);
    File::put($this->module . '/src/Thing.php', "<?php\n\nuse ZzOther\\Thing;\n");
});

afterEach(function (): void {
    File::deleteDirectory($this->root);
});

it('passes a module that declares what it uses and keeps clear of what it must never meet', function (): void {
    assertModuleBoundary($this->module);
    assertModuleDependenciesDeclared($this->module);
    assertModuleDoesNotReference($this->module, ['ZzForbidden\\', 'zz/forbidden']);
});

it('fails on a reference to an undeclared sibling and names the file, unless the package is allowed', function (): void {
    File::put($this->module . '/src/Leak.php', "<?php\n\nuse ZzOptional\\Export;\n");

    expect(fn() => assertModuleDependenciesDeclared($this->module))
        ->toThrow(AssertionFailedError::class, 'src/Leak.php');

    assertModuleDependenciesDeclared($this->module, ['zz/optional']);
    // composer.json allows zz/optional (with its reason), so the standard test passes.
    assertModuleBoundary($this->module);
});

it('fails on a reference to a forbidden namespace, in PHP and in the escaped YAML form', function (): void {
    File::put($this->module . '/src/Cross.php', "<?php\n\nuse ZzForbidden\\Party;\n");

    expect(fn() => assertModuleBoundary($this->module))
        ->toThrow(AssertionFailedError::class, 'src/Cross.php references ZzForbidden\\');

    File::delete($this->module . '/src/Cross.php');
    File::ensureDirectoryExists($this->module . '/app-configs/zz/lists');
    File::put($this->module . '/app-configs/zz/lists/things-list.yml', "model: ZzForbidden\\\\Party\n");

    expect(fn() => assertModuleDoesNotReference($this->module, ['ZzForbidden']))
        ->toThrow(AssertionFailedError::class, 'app-configs/zz/lists/things-list.yml references ZzForbidden\\');
});

it('fails when composer.json requires the forbidden package, even though the dependency is then declared', function (): void {
    zzWriteBoundaryModule($this->module, 'zz/module', 'ZzModule', [
        'require' => ['noerd/noerd' => '*', 'zz/forbidden' => '*'],
        'extra' => ['noerd' => ['boundary' => ['forbidden' => ['ZzForbidden\\']]]],
    ]);
    File::put($this->module . '/src/Thing.php', "<?php\n\nuse ZzForbidden\\Party;\n");

    assertModuleDependenciesDeclared($this->module);

    expect(fn() => assertModuleBoundary($this->module))
        ->toThrow(AssertionFailedError::class, 'composer.json requires zz/forbidden');
    expect(fn() => assertModuleDoesNotReference($this->module, ['zz/forbidden']))
        ->toThrow(AssertionFailedError::class, 'composer.json requires zz/forbidden');
});

it('skips the boundary test itself and the agent documentation', function (): void {
    File::ensureDirectoryExists($this->module . '/tests/Feature');
    File::ensureDirectoryExists($this->module . '/resources/boost/guidelines');
    File::put($this->module . '/tests/Feature/ModuleBoundaryTest.php', "<?php\n\n// never ZzForbidden\\Party\n");
    File::put($this->module . '/resources/boost/guidelines/core.blade.php', "Never reference `ZzForbidden\\` here.\n");

    assertModuleBoundary($this->module);
});

it('runs only the dependency guard for a module without a boundary block', function (): void {
    zzWriteBoundaryModule($this->module, 'zz/module', 'ZzModule', [
        'require' => ['noerd/noerd' => '*', 'zz/forbidden' => '*'],
    ]);
    File::put($this->module . '/src/Thing.php', "<?php\n\nuse ZzForbidden\\Party;\n");

    assertModuleBoundary($this->module);

    File::put($this->module . '/src/Leak.php', "<?php\n\nuse ZzOther\\Thing;\n");

    expect(fn() => assertModuleBoundary($this->module))
        ->toThrow(AssertionFailedError::class, 'zz/other');
});
