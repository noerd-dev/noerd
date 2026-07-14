<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Symfony\Component\Yaml\Yaml;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * The top bar reads the real project config, so each test snapshots the file and
 * restores it afterwards.
 */
beforeEach(function (): void {
    $this->topBarPath = base_path('app-configs/top-bar.yml');
    $this->topBarBackup = file_exists($this->topBarPath) ? file_get_contents($this->topBarPath) : null;

    $user = NoerdUser::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->tenants()->attach($tenant->id);
    TenantHelper::setSelectedTenantId($tenant->id);
    $this->actingAs($user);
});

afterEach(function (): void {
    if ($this->topBarBackup !== null) {
        file_put_contents($this->topBarPath, $this->topBarBackup);
    } elseif (file_exists($this->topBarPath)) {
        unlink($this->topBarPath);
    }
});

function writeTopBar(string $path, array $items): void
{
    file_put_contents($path, Yaml::dump(['items' => $items], 10, 2));
}

it('renders an icon link for each configured item', function (): void {
    Route::get('/dummy-target', fn() => '')->name('dummy-target');

    writeTopBar($this->topBarPath, [
        ['route' => 'dummy-target', 'heroicon' => 'squares-2x2', 'label' => 'Dummy'],
    ]);

    Livewire::test('noerd::layout.top-bar')
        ->assertOk()
        ->assertSee(route('dummy-target', absolute: false))
        ->assertSee('Dummy');
});

/**
 * The load-bearing guard: uninstalling a module leaves its entry behind in the
 * YAML. Without Route::has() the route() call would take down every page.
 */
it('skips an item whose route does not exist instead of throwing', function (): void {
    writeTopBar($this->topBarPath, [
        ['route' => 'route-from-an-uninstalled-module', 'heroicon' => 'squares-2x2', 'label' => 'Gone'],
    ]);

    Livewire::test('noerd::layout.top-bar')
        ->assertOk()
        ->assertDontSee('Gone');
});

it('renders without items when the config file is absent', function (): void {
    if (file_exists($this->topBarPath)) {
        unlink($this->topBarPath);
    }

    Livewire::test('noerd::layout.top-bar')
        ->assertOk()
        ->assertSet('topBarItems', []);
});
