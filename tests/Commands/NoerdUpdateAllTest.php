<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Noerd\Commands\NoerdUpdateAllCommand;
use Noerd\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

uses(TestCase::class);

/**
 * Collects what the aggregate command actually invoked, in order.
 */
final class UpdateAllRecorder
{
    /** @var array<int, string> */
    public static array $calls = [];

    /** @var array<string, array{force: bool, build: bool}> */
    public static array $options = [];
}

/**
 * Stand-in for a module's update command. Only the core stub declares --build, so a
 * regression that forwards --build to a module command makes ArrayInput throw and
 * fails the test on its own.
 */
class RecordingUpdateCommand extends Command
{
    public function __construct(
        string $name,
        private int $exit = 0,
        private bool $throws = false,
        bool $withBuild = false,
    ) {
        $this->signature = $name
            . ' {--force : Overwrite existing files without asking}'
            . ($withBuild ? ' {--build : Run npm build}' : '');

        parent::__construct();
    }

    public function handle(): int
    {
        UpdateAllRecorder::$calls[] = (string) $this->getName();
        UpdateAllRecorder::$options[(string) $this->getName()] = [
            'force' => (bool) $this->option('force'),
            'build' => $this->getDefinition()->hasOption('build') && (bool) $this->option('build'),
        ];

        $this->info('ran ' . $this->getName());

        if ($this->throws) {
            throw new RuntimeException('boom');
        }

        return $this->exit;
    }
}

/**
 * Fixture that pretends noerd:install has not been run yet.
 */
class UpdateAllWithoutNoerdFixture extends NoerdUpdateAllCommand
{
    protected function isNoerdInstalled(): bool
    {
        return false;
    }
}

beforeEach(function (): void {
    UpdateAllRecorder::$calls = [];
    UpdateAllRecorder::$options = [];

    $kernel = $this->app[Kernel::class];

    // Any real update command that happens to be registered is excluded per run, so
    // the assertions hold no matter which modules the host application loads.
    $this->excluded = collect(array_keys(Artisan::all()))
        ->filter(fn(string $name): bool => $name === 'noerd:update' || str_starts_with($name, 'noerd:update-'))
        ->reject(fn(string $name): bool => in_array($name, ['noerd:update-all', 'noerd:update', 'noerd:update-plus'], true))
        ->values()
        ->all();

    // registerCommand() replaces a same-named entry — mandatory for noerd:update,
    // which would otherwise really run npm install and rewrite tailwind.config.js.
    $kernel->registerCommand(new RecordingUpdateCommand('noerd:update', withBuild: true));
    // Registered out of alphabetical order on purpose: the run must sort, not follow registration.
    $kernel->registerCommand(new RecordingUpdateCommand('noerd:update-zz-beta'));
    $kernel->registerCommand(new RecordingUpdateCommand('noerd:update-zz-alpha'));
    $kernel->registerCommand(new RecordingUpdateCommand('noerd:update-plus'));

    $this->run = fn(array $parameters = []) => $this->artisan(
        'noerd:update-all',
        array_merge(['--except' => $this->excluded], $parameters),
    );
});

it('runs the core update first, module updates alphabetically and noerd:update-plus last', function (): void {
    ($this->run)(['--force' => true])->assertExitCode(0);

    expect(UpdateAllRecorder::$calls)->toBe([
        'noerd:update',
        'noerd:update-zz-alpha',
        'noerd:update-zz-beta',
        'noerd:update-plus',
    ]);
});

it('never runs itself', function (): void {
    ($this->run)(['--force' => true])->assertExitCode(0);

    expect(UpdateAllRecorder::$calls)->not->toContain('noerd:update-all');
});

it('forwards --force to every sub-command', function (): void {
    ($this->run)(['--force' => true])->assertExitCode(0);

    expect(collect(UpdateAllRecorder::$options)->pluck('force')->all())
        ->toBe([true, true, true, true]);
});

it('runs the sub-commands without --force when it was not given', function (): void {
    ($this->run)(['--no-interaction' => true])->assertExitCode(0);

    expect(collect(UpdateAllRecorder::$options)->pluck('force')->all())
        ->toBe([false, false, false, false]);
});

it('forwards --build only to the core update', function (): void {
    ($this->run)(['--force' => true, '--build' => true])->assertExitCode(0);

    expect(UpdateAllRecorder::$options['noerd:update']['build'])->toBeTrue()
        ->and(UpdateAllRecorder::$options['noerd:update-zz-alpha']['build'])->toBeFalse()
        ->and(UpdateAllRecorder::$calls)->toHaveCount(4);
});

it('continues after a sub-command that fails and exits non-zero', function (): void {
    $this->app[Kernel::class]->registerCommand(new RecordingUpdateCommand('noerd:update-zz-alpha', exit: 1));

    ($this->run)(['--force' => true])
        ->expectsOutputToContain('failed')
        ->assertExitCode(1);

    expect(UpdateAllRecorder::$calls)->toContain('noerd:update-zz-beta', 'noerd:update-plus');
});

it('continues after a sub-command that throws', function (): void {
    $this->app[Kernel::class]->registerCommand(new RecordingUpdateCommand('noerd:update-zz-alpha', throws: true));

    ($this->run)(['--force' => true])
        ->expectsOutputToContain('boom')
        ->assertExitCode(1);

    expect(UpdateAllRecorder::$calls)->toContain('noerd:update-zz-beta', 'noerd:update-plus');
});

it('skips commands passed to --except and reports them as skipped', function (): void {
    $this->artisan('noerd:update-all', [
        '--force' => true,
        '--except' => array_merge($this->excluded, ['zz-beta', 'noerd:update']),
    ])
        ->expectsOutputToContain('skipped')
        ->assertExitCode(0);

    expect(UpdateAllRecorder::$calls)->toBe(['noerd:update-zz-alpha', 'noerd:update-plus']);
});

it('reports every command as updated when nothing fails', function (): void {
    ($this->run)(['--force' => true])
        ->expectsOutputToContain('updated')
        ->assertExitCode(0);
});

it('streams the sub-command output into its own output', function (): void {
    ($this->run)(['--force' => true])
        ->expectsOutputToContain('ran noerd:update-zz-alpha')
        ->assertExitCode(0);
});

it('aborts when noerd is not installed', function (): void {
    $command = new UpdateAllWithoutNoerdFixture();
    $command->setLaravel(app());

    $output = new BufferedOutput();
    $exitCode = $command->run(new ArrayInput([]), $output);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($output->fetch())->toContain('Noerd base package has not been installed yet.')
        ->and(UpdateAllRecorder::$calls)->toBeEmpty();
});
