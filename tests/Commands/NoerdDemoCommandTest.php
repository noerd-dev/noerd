<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Noerd\Commands\NoerdDemoCommand;
use Noerd\Traits\RequiresNoerdInstallation;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

uses(Tests\TestCase::class);

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

it('uses the RequiresNoerdInstallation trait', function () {
    expect(class_uses_recursive(NoerdDemoCommand::class))
        ->toContain(RequiresNoerdInstallation::class);
});

it('aborts without installing anything when noerd is not installed', function () {
    $command = new DemoCommandWithoutNoerdFixture();
    $command->setLaravel(app());

    $output = new BufferedOutput();
    $exitCode = $command->run(new ArrayInput([]), $output);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($output->fetch())
        ->toContain('Noerd base package has not been installed yet.')
        ->not->toContain('Installing noerd demo data');
});
