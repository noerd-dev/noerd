<?php

declare(strict_types=1);

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

/**
 * The demo app is the first thing a new user opens: it must work with the
 * slim components exactly as shipped. The demo's own files are wired into
 * the testbench skeleton here — models required directly, migrations run,
 * app configs published, views registered as a Livewire location.
 */
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
