<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Services\ComponentSlotsRegistry;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $user = NoerdUser::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->tenants()->attach($tenant->id);
    TenantHelper::setSelectedTenantId($tenant->id);
    $this->actingAs($user);

    // Installed modules register themselves at boot, so start from a clean registry:
    // these tests are about the core's slot, not about whoever happens to be installed.
    app()->instance(ComponentSlotsRegistry::class, new ComponentSlotsRegistry());
});

it('renders a component a module registered above the app tiles', function (): void {
    Livewire::component('test-apps-overview-notice', new class extends Component {
        public function render(): string
        {
            return '<div>registered-apps-overview-notice</div>';
        }
    });

    app(ComponentSlotsRegistry::class)->register(ComponentSlotsRegistry::APPS_OVERVIEW, 'test-apps-overview-notice');

    Livewire::test('noerd::noerd-apps')
        ->assertOk()
        ->assertSet('overviewComponents', ['test-apps-overview-notice'])
        ->assertSee('registered-apps-overview-notice');
});

it('renders the apps overview when no module registered anything', function (): void {
    Livewire::test('noerd::noerd-apps')
        ->assertOk()
        ->assertSet('overviewComponents', []);
});
