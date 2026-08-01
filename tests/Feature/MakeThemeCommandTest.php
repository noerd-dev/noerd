<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Noerd\Tests\TestCase;

uses(TestCase::class);

afterEach(function (): void {
    File::deleteDirectory(resource_path('views/themes/zz-scaffold'));
});

it('scaffolds a project theme from the default theme folder', function (): void {
    $this->artisan('noerd:theme zz-scaffold')->assertSuccessful();

    $target = resource_path('views/themes/zz-scaffold');

    expect(File::isDirectory($target))->toBeTrue()
        ->and(File::exists("{$target}/theme.yml"))->toBeTrue()
        ->and(File::exists("{$target}/input.blade.php"))->toBeTrue()
        ->and(File::exists("{$target}/button.blade.php"))->toBeTrue()
        ->and(File::exists("{$target}/relation-field.blade.php"))->toBeTrue()
        ->and(File::get("{$target}/theme.yml"))->toContain('label: Zz Scaffold');
});

it('refuses to overwrite an existing theme folder', function (): void {
    File::ensureDirectoryExists(resource_path('views/themes/zz-scaffold'));

    $this->artisan('noerd:theme zz-scaffold')->assertFailed();
});

it('rejects an invalid theme name', function (): void {
    $this->artisan('noerd:theme "Ödes_Theme!"')->assertFailed();
});
