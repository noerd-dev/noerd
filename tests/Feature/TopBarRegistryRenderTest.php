<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Services\TopBarRegistry;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $user = NoerdUser::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->tenants()->attach($tenant->id);
    TenantHelper::setSelectedTenantId($tenant->id);
    $this->actingAs($user);

    // Installed modules register themselves at boot, so start from a clean registry:
    // these tests are about the core's slot, not about whoever happens to be installed.
    app()->instance(TopBarRegistry::class, new TopBarRegistry());
});

it('renders a component a module registered', function (): void {
    Livewire::component('test-top-bar-item', new class () extends Component {
        public function render(): string
        {
            return '<div>registered-top-bar-item</div>';
        }
    });

    app(TopBarRegistry::class)->register('test-top-bar-item');

    Livewire::test('noerd::layout.top-bar')
        ->assertOk()
        ->assertSee('registered-top-bar-item');
});

it('renders normally when no module registered anything', function (): void {
    expect(app(TopBarRegistry::class)->all())->toBe([]);

    Livewire::test('noerd::layout.top-bar')
        ->assertOk()
        ->assertSet('topBarComponents', []);
});
