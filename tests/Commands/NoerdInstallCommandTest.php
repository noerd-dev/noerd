<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Create a temporary test directory for source content
    $this->testSourceDir = base_path('vendor/noerd/noerd/content');
    $this->testTargetDir = base_path('content');

    // Backup existing files if they exist
    $this->backupConfigPath = null;
    if (file_exists(base_path('config/noerd.php'))) {
        $this->backupConfigPath = base_path('config/noerd.php.backup');
        copy(base_path('config/noerd.php'), $this->backupConfigPath);
    }
});

afterEach(function (): void {
    // Restore backed up config if it existed
    if ($this->backupConfigPath && file_exists($this->backupConfigPath)) {
        rename($this->backupConfigPath, base_path('config/noerd.php'));
    }
});

it('shows error when source directory does not exist', function (): void {
    // Temporarily rename the source directory to simulate missing directory
    $sourceDir = base_path('vendor/noerd/noerd/content');
    $tempDir = base_path('vendor/noerd/noerd/content_temp_backup');

    if (is_dir($sourceDir)) {
        rename($sourceDir, $tempDir);
    }

    try {
        $this->artisan('noerd:install', ['--force' => true, '--no-interaction' => true])
            ->expectsOutput("Source directory not found: {$sourceDir}")
            ->assertExitCode(1);
    } finally {
        // Restore the source directory
        if (is_dir($tempDir)) {
            rename($tempDir, $sourceDir);
        }
    }
});

it('displays installation summary', function (): void {
    $this->artisan('noerd:install', ['--force' => true])
        ->expectsOutput('Installing noerd content...')
        ->expectsOutput('Installation Summary:')
        ->expectsConfirmation('Would you like to run "php artisan migrate" now?', 'no')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
        ->expectsOutput('Noerd content successfully installed!')
        ->assertExitCode(0);
});

it('creates app-modules directory with gitkeep', function (): void {
    $appModulesPath = base_path('app-modules');
    $gitkeepPath = $appModulesPath . '/.gitkeep';

    // Ensure app-modules exists (it should already in this project)
    expect(is_dir($appModulesPath))->toBeTrue();

    $this->artisan('noerd:install', ['--force' => true])
        ->expectsConfirmation('Would you like to run "php artisan migrate" now?', 'no')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
        ->assertExitCode(0);

    // Verify .gitkeep file exists
    expect(file_exists($gitkeepPath))->toBeTrue();
});

it('publishes noerd config file', function (): void {
    $configPath = base_path('config/noerd.php');

    $this->artisan('noerd:install', ['--force' => true])
        ->expectsConfirmation('Would you like to run "php artisan migrate" now?', 'no')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
        ->assertExitCode(0);

    expect(file_exists($configPath))->toBeTrue();
});

it('sets up frontend assets', function (): void {
    $this->artisan('noerd:install', ['--force' => true])
        ->expectsOutput('Setting up frontend assets...')
        ->expectsConfirmation('Would you like to run "php artisan migrate" now?', 'no')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
        ->assertExitCode(0);
});

it('respects force option for overwriting files', function (): void {
    // First run to ensure files exist
    $this->artisan('noerd:install', ['--force' => true])
        ->expectsConfirmation('Would you like to run "php artisan migrate" now?', 'no')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
        ->assertExitCode(0);

    // Second run with force - should overwrite without prompting
    $this->artisan('noerd:install', ['--force' => true])
        ->expectsConfirmation('Would you like to run "php artisan migrate" now?', 'no')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
        ->expectsOutput('Noerd content successfully installed!')
        ->assertExitCode(0);
});

it('can skip migrations when declined', function (): void {
    $this->artisan('noerd:install', ['--force' => true])
        ->expectsConfirmation('Would you like to run "php artisan migrate" now?', 'no')
        ->expectsOutput('Skipping migrations. You can run them manually later with: php artisan migrate')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
        ->assertExitCode(0);
});

it('can skip npm build when declined', function (): void {
    $this->artisan('noerd:install', ['--force' => true])
        ->expectsConfirmation('Would you like to run "php artisan migrate" now?', 'no')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
        ->expectsOutput('Skipping npm build. You can run it manually later with: npm run build')
        ->assertExitCode(0);
});

it('runs migrations when confirmed', function (): void {
    $this->artisan('noerd:install', ['--force' => true])
        ->expectsConfirmation('Would you like to run "php artisan migrate" now?', 'yes')
        ->expectsOutput('Running migrations...')
        ->expectsConfirmation('Would you like to create an admin user now?', 'no')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
        ->assertExitCode(0);
});

it('updates composer repositories configuration', function (): void {
    $composerPath = base_path('composer.json');

    $this->artisan('noerd:install', ['--force' => true])
        ->expectsConfirmation('Would you like to run "php artisan migrate" now?', 'no')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
        ->assertExitCode(0);

    // Verify composer.json contains the path repository
    $composerContent = file_get_contents($composerPath);
    $composerData = json_decode($composerContent, true);

    expect($composerData)->toHaveKey('repositories');

    $hasPathRepo = false;
    foreach ($composerData['repositories'] as $repo) {
        if (($repo['type'] ?? '') === 'path' && ($repo['url'] ?? '') === 'app-modules/*') {
            $hasPathRepo = true;
            break;
        }
    }
    expect($hasPathRepo)->toBeTrue();
});

it('copies content directory structure', function (): void {
    $this->artisan('noerd:install', ['--force' => true])
        ->expectsConfirmation('Would you like to run "php artisan migrate" now?', 'no')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
        ->assertExitCode(0);

    // Verify target content directory exists
    expect(is_dir(base_path('content')))->toBeTrue();
});

it('has correct command signature', function (): void {
    $command = $this->app->make(\Noerd\Noerd\Commands\NoerdInstallCommand::class);

    expect($command->getName())->toBe('noerd:install');
    expect($command->getDescription())->toBe('Install noerd content to the local content directory');
});

it('includes force option in signature', function (): void {
    $command = $this->app->make(\Noerd\Noerd\Commands\NoerdInstallCommand::class);
    $definition = $command->getDefinition();

    expect($definition->hasOption('force'))->toBeTrue();
    expect($definition->getOption('force')->getDescription())->toBe('Overwrite existing files without asking');
});
