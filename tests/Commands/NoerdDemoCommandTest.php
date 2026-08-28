<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Noerd\Commands\NoerdDemoCommand;
use Noerd\Tests\TestCase;
use Noerd\Traits\RequiresNoerdInstallation;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

uses(TestCase::class);

/**
 * Fixture that pretends noerd:install has not been run yet, so the guard
 * can be exercised without touching the real config/noerd.php.
 */
class DemoCommandWithoutNoerdFixture extends NoerdDemoCommand
{
    protected function isNoerdInstalled(): bool
    {
        return false;
    }
}

it('aborts without installing anything when noerd is not installed', function (): void {
    $command = new DemoCommandWithoutNoerdFixture();
    $command->setLaravel(app());

    $output = new BufferedOutput();
    $exitCode = $command->run(new ArrayInput([]), $output);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($output->fetch())
        ->toContain('Noerd base package has not been installed yet.')
        ->not->toContain('Installing noerd demo data');
});
