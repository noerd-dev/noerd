<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Noerd\Commands\Concerns\PublishesNoerdContent;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/**
 * vendor:publish resolves its targets from the paths the service providers
 * registered when they BOOTED. A command that moved the application's base path
 * — an install command under test, a build tool — would otherwise overwrite the
 * REAL installation's files, and `--force` makes that silent: this is how a test
 * run used to reset the developer's own config/livewire.php to the Livewire
 * default and break every route test that followed.
 */
class ZzPublishGuardProbeCommand extends Command
{
    use PublishesNoerdContent;

    protected $signature = 'noerd:zz-publish-guard-probe';

    public function handle(): int
    {
        $this->updateLivewireConfig();

        return self::SUCCESS;
    }

    protected function getModuleName(): string
    {
        return 'Zz Publish Guard';
    }
}

beforeEach(function (): void {
    $this->app[Kernel::class]->registerCommand(new ZzPublishGuardProbeCommand());

    $this->realConfig = base_path('config/livewire.php');
    $this->realBackup = File::exists($this->realConfig) ? File::get($this->realConfig) : null;
});

afterEach(function (): void {
    if ($this->realBackup === null) {
        File::delete($this->realConfig);
    } else {
        File::put($this->realConfig, $this->realBackup);
    }
});

it('does not publish the livewire config into another installation', function (): void {
    $hostPath = storage_path('framework/testing/zz-publish-guard-' . getmypid());
    File::deleteDirectory($hostPath);
    File::ensureDirectoryExists($hostPath . '/config');

    $marker = "<?php\n\nreturn ['component_layout' => 'noerd::layouts.app'];\n";
    File::put($this->realConfig, $marker);

    $originalBasePath = $this->app->basePath();
    $this->app->setBasePath($hostPath);

    try {
        $this->artisan('noerd:zz-publish-guard-probe')
            ->expectsOutputToContain('would write outside this installation')
            ->assertExitCode(0);
    } finally {
        $this->app->setBasePath($originalBasePath);
        File::deleteDirectory($hostPath);
    }

    // The real installation's file is untouched, and nothing was written into
    // the throwaway one either.
    expect(File::get($this->realConfig))->toBe($marker)
        ->and(File::exists($hostPath . '/config/livewire.php'))->toBeFalse();
});

it('rewrites the component layout of the installation it runs in', function (): void {
    File::put($this->realConfig, "<?php\n\nreturn ['component_layout' => 'layouts::app'];\n");

    $this->artisan('noerd:zz-publish-guard-probe')->assertExitCode(0);

    expect(File::get($this->realConfig))->toContain("'noerd::layouts.app'");
});
