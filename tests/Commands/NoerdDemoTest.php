<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Noerd\Commands\NoerdDemoCommand;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

uses(TestCase::class, RefreshDatabase::class);

describe('install guard', function (): void {
    /**
     * Fixture that pretends noerd:install has not been run yet, so the guard
     * can be exercised without touching the real config/noerd.php.
     */
    it('aborts without installing anything when noerd is not installed', function (): void {
        $command = new ZzDemoCommandWithoutNoerdFixture();
        $command->setLaravel(app());

        $output = new BufferedOutput();
        $exitCode = $command->run(new ArrayInput([]), $output);

        expect($exitCode)->toBe(Command::FAILURE)
            ->and($output->fetch())
            ->toContain('Noerd base package has not been installed yet.')
            ->not->toContain('Installing noerd demo data');
    });
});

describe('routes', function (): void {
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
            ->toContain("Route::group(['middleware' => ['noerd', 'app-access:demo']]")
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
});

/**
 * The demo app is the first thing a new user opens: it must work with the
 * slim components exactly as shipped. The demo's own files are wired into
 * the testbench skeleton here — models required directly, migrations run,
 * app configs published, views registered as a Livewire location.
 */
describe('showcase', function (): void {
    beforeEach(function (): void {
        $demoDir = dirname(__DIR__, 2) . '/demo';

        foreach (['DemoCategory', 'DemoTag', 'DemoCustomer'] as $model) {
            if (! class_exists('App\\Models\\' . $model)) {
                require_once "{$demoDir}/Models/{$model}.php";
            }
        }

        foreach (['create_demo_categories_table', 'create_demo_tags_table', 'create_demo_customers_table', 'create_demo_customer_demo_tag_table'] as $migration) {
            (require "{$demoDir}/migrations/{$migration}.php")->up();
        }

        $command = new NoerdDemoCommand();
        $command->setLaravel(app());
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));
        (new ReflectionProperty($command, 'demoDir'))->setValue($command, $demoDir);
        (new ReflectionMethod($command, 'publishAppConfigs'))->invoke($command);

        Livewire::addLocation(viewPath: "{$demoDir}/views");

        $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('demo')->create());
    });

    afterEach(function (): void {
        File::deleteDirectory(base_path('app-configs/demo'));
    });

    it('stores a demo customer with its tags through the slim detail', function (): void {
        $category = App\Models\DemoCategory::create(['name' => 'Enterprise']);
        $tag = App\Models\DemoTag::create(['name' => 'VIP']);

        Livewire::test('demo-customer-detail')
            ->set('detailData.name', 'Sarah Johnson')
            ->set('detailData.demo_category_id', $category->id)
            ->set('detailData.status', 'completed')
            ->set('detailData.revenue', 245000)
            ->set('tagIds', [$tag->id])
            ->call('store')
            ->assertHasNoErrors()
            ->assertSet('showSuccessIndicator', true);

        $customer = App\Models\DemoCustomer::firstWhere('name', 'Sarah Johnson');

        expect($customer)->not->toBeNull()
            ->and($customer->tenant_id)->toBe(NoerdUser::first()->selected_tenant_id)
            ->and($customer->demo_category_id)->toBe($category->id)
            ->and($customer->status)->toBe('completed')
            ->and($customer->tags->pluck('id')->all())->toBe([$tag->id]);
    });

    it('renders the demo customers list with the picklist badge of the paired detail', function (): void {
        App\Models\DemoCustomer::create(['name' => 'Acme', 'status' => 'in_progress']);

        Livewire::test('demo-customers-list')
            ->assertOk()
            ->assertSee('Acme')
            ->assertSee('In Progress');
    });

    it('opens a record through the demo detail route when it is registered', function (): void {
        registerTestLivewireRoute('demo-customer/{modelId}', 'demo-customer-detail', 'demo-customer.detail');
        $customer = App\Models\DemoCustomer::create(['name' => 'Acme']);

        Livewire::test('demo-customers-list')
            ->call('openListRow', $customer->id)
            ->assertDispatched('noerdModal');
    });
});

/**
 * Fixture that pretends noerd:install has not been run yet.
 */
class ZzDemoCommandWithoutNoerdFixture extends NoerdDemoCommand
{
    protected function isNoerdInstalled(): bool
    {
        return false;
    }
}

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
