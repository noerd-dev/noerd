<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\File;
use Noerd\Commands\NoerdDemoCommand;
use Noerd\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

uses(TestCase::class);

/**
 * Invoke the private addRoutes() step in isolation, without running the
 * full demo installation (models, migrations, views, database seeding).
 */
function runDemoAddRoutes(): void
{
    $command = new NoerdDemoCommand();
    $command->setLaravel(app());
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));

    (new ReflectionMethod($command, 'addRoutes'))->invoke($command);
}

beforeEach(function (): void {
    $routeFile = base_path('routes/web.php');

    $this->routeFileExisted = File::exists($routeFile);
    $this->originalRoutes = $this->routeFileExisted ? File::get($routeFile) : null;

    if (! $this->routeFileExisted) {
        File::ensureDirectoryExists(dirname($routeFile));
        File::put($routeFile, "<?php\n");
    }
});

afterEach(function (): void {
    $routeFile = base_path('routes/web.php');

    if ($this->routeFileExisted) {
        File::put($routeFile, $this->originalRoutes);
    } else {
        File::delete($routeFile);
    }
});

it('appends the demo route block protected by the noerd middleware group', function (): void {
    $routeFile = base_path('routes/web.php');
    $before = File::get($routeFile);

    runDemoAddRoutes();

    $appended = mb_substr(File::get($routeFile), mb_strlen($before));

    expect($appended)
        ->toContain("Route::group(['middleware' => ['noerd']]")
        ->toContain("Route::livewire('demo-customers', 'demo-customers-list')")
        // A bare 'auth' middleware checks the default 'web' guard while noerd logs
        // in via its own 'noerd' guard, producing an endless login redirect loop.
        ->not->toContain("'auth'")
        ->not->toContain("'verified'")
        ->not->toContain("'web'");
});

it('does not append the demo route block twice', function (): void {
    runDemoAddRoutes();
    $afterFirst = File::get(base_path('routes/web.php'));

    runDemoAddRoutes();

    expect(File::get(base_path('routes/web.php')))->toBe($afterFirst);
});
