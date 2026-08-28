<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Noerd\Commands\NoerdInstallCommand;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/**
 * Fixture exposing the closing "Application ready" callout in isolation.
 */
class InstallReadyCalloutFixtureCommand extends NoerdInstallCommand
{
    protected $signature = 'test:install-ready-callout';

    public function handle()
    {
        $this->displayApplicationReady();

        return 0;
    }
}

beforeEach(function (): void {
    $this->app[Kernel::class]->registerCommand(new InstallReadyCalloutFixtureCommand());
});

it('displays the application ready callout with the next steps', function (): void {
    config(['app.url' => 'https://example.test']);

    Artisan::call('test:install-ready-callout');
    $output = Artisan::output();

    // The callout copy is content — asserted is only the functional next step:
    // the apps-dashboard URL derived from app.url.
    expect($output)->toContain('https://example.test/noerd-apps');
});
