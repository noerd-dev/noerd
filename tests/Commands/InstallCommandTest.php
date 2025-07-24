<?php

use Illuminate\Support\Facades\File;
use Nywerk\Noerd\Commands\InstallCommand;

it('successfully installs noerd css file', function (): void {
    // Define paths relative to Laravel app
    $targetFile = base_path('public/css/noerd.css');
    
    // Ensure clean start - remove any existing file
    if (File::exists($targetFile)) {
        File::delete($targetFile);
    }

    // Run the install command
    $this->artisan('noerd:install')
        ->expectsOutput('Installing Noerd UI CSS...')
        ->expectsOutput('✅ Successfully installed Noerd UI CSS (standard version)')
        ->expectsOutput('📝 Usage in your Blade templates:')
        ->expectsOutput('<link rel="stylesheet" href="{{ asset(\'css/noerd.css\') }}">')
        ->assertExitCode(0);

    // Verify the file was created
    expect(File::exists($targetFile))->toBeTrue();

    // Verify the file has content (should be around 55KB)
    $fileSize = File::size($targetFile);
    expect($fileSize)->toBeGreaterThan(50000); // At least 50KB
    expect($fileSize)->toBeLessThan(70000);    // Less than 70KB

    // Clean up
    File::delete($targetFile);
});

it('installs clean version when --clean option is used', function (): void {
    // Define paths relative to Laravel app
    $targetFile = base_path('public/css/noerd.css');
    
    // Ensure clean start
    if (File::exists($targetFile)) {
        File::delete($targetFile);
    }

    // Run the install command with --clean option
    $this->artisan('noerd:install', ['--clean' => true])
        ->expectsOutput('Installing Noerd UI CSS...')
        ->expectsOutput('✅ Successfully installed Noerd UI CSS (clean version (without Tailwind header))')
        ->assertExitCode(0);

    // Verify the file was created
    expect(File::exists($targetFile))->toBeTrue();

    // Verify it doesn't start with Tailwind comment
    $content = File::get($targetFile);
    expect($content)->not->toStartWith('/*! tailwindcss');

    // Clean up
    File::delete($targetFile);
});

it('prompts for confirmation when file exists without --force', function (): void {
    $targetFile = base_path('public/css/noerd.css');
    
    // Create a dummy file first
    File::ensureDirectoryExists(dirname($targetFile));
    File::put($targetFile, 'dummy content');

    // Run the install command and decline overwriting
    $this->artisan('noerd:install')
        ->expectsQuestion("File {$targetFile} already exists. Overwrite?", false)
        ->expectsOutput('Installation cancelled.')
        ->assertExitCode(0);

    // Verify the file still contains dummy content
    expect(File::get($targetFile))->toBe('dummy content');

    // Clean up
    File::delete($targetFile);
});

it('overwrites file when --force option is used', function (): void {
    $targetFile = base_path('public/css/noerd.css');
    
    // Create a dummy file first
    File::ensureDirectoryExists(dirname($targetFile));
    File::put($targetFile, 'dummy content');

    // Run the install command with --force
    $this->artisan('noerd:install', ['--force' => true])
        ->expectsOutput('Installing Noerd UI CSS...')
        ->expectsOutput('✅ Successfully installed Noerd UI CSS (standard version)')
        ->assertExitCode(0);

    // Verify the file was overwritten (should be much larger than dummy content)
    $fileSize = File::size($targetFile);
    expect($fileSize)->toBeGreaterThan(50000);

    // Clean up
    File::delete($targetFile);
});

it('creates css directory if it does not exist', function (): void {
    $cssDir = base_path('public/css');
    $targetFile = base_path('public/css/noerd.css');
    
    // Remove css directory if it exists
    if (File::exists($cssDir)) {
        File::deleteDirectory($cssDir);
    }

    // Run the install command
    $this->artisan('noerd:install')
        ->expectsOutput("Created directory: {$cssDir}")
        ->expectsOutput('✅ Successfully installed Noerd UI CSS (standard version)')
        ->assertExitCode(0);

    // Verify directory and file were created
    expect(File::exists($cssDir))->toBeTrue();
    expect(File::exists($targetFile))->toBeTrue();

    // Clean up
    File::delete($targetFile);
}); 