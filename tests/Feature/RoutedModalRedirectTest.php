<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Middleware\RememberPageUrl;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdPage;

uses(TestCase::class, RefreshDatabase::class);

/*
 | RoutedModal mechanics: pasting a routed-modal URL (…?modal=true) into a tab
 | with browsing history redirects back to the previous page and flashes the
 | reopen instruction the modal stack consumes; without history the plain full
 | page renders. The `new` sentinel maps back to a null modelId.
 */

class ZzRoutedModalPageComponent extends Component
{
    use NoerdPage;

    public $detailModel = Tenant::class;

    public function render(): string
    {
        return '<div>zz-routed-modal-page</div>';
    }

    protected function componentName(): string
    {
        return 'zz-routed-modal-page';
    }
}

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    // The factory registers the app tile with a module icon view that does not
    // exist in the testbench — full-page renders need a resolvable icon.
    \Noerd\Models\TenantApp::query()->update(['icon' => 'heroicon:outline:cog-6-tooth']);

    Livewire::component('zz-routed-modal-page', ZzRoutedModalPageComponent::class);
    registerTestLivewireRoute('zz-routed/{modelId}', 'zz-routed-modal-page', 'zz.routed.page');
});

it('redirects a ?modal=true page load back to the previous page and flashes the reopen instruction', function (): void {
    $tenant = Tenant::factory()->create();

    $response = $this->session([RememberPageUrl::SESSION_KEY => url('/setup')])
        ->get(route('zz.routed.page', ['modelId' => $tenant->id, 'modal' => true]));

    $response->assertRedirect(url('/setup'));

    $flash = session('noerd-modal.open');
    expect($flash['component'])->toBe('zz-routed-modal-page')
        ->and($flash['arguments']['modelId'])->toBe((string) $tenant->id)
        ->and($flash['url'])->toContain('modal=');
});

it('renders the plain full page without a previous url', function (): void {
    $tenant = Tenant::factory()->create();

    $this->get(route('zz.routed.page', ['modelId' => $tenant->id, 'modal' => true]))
        ->assertOk()
        ->assertSee('zz-routed-modal-page');
});

it('renders the plain full page without the modal flag', function (): void {
    $tenant = Tenant::factory()->create();

    $this->session([RememberPageUrl::SESSION_KEY => url('/setup')])
        ->get(route('zz.routed.page', ['modelId' => $tenant->id]))
        ->assertOk();
});

it('drops route parameters the component does not bind from the reopen instruction', function (): void {
    registerTestLivewireRoute('zz-routed-extra/{modelId}/{unboundParam}', 'zz-routed-modal-page', 'zz.routed.extra');

    $tenant = Tenant::factory()->create();

    $this->session([RememberPageUrl::SESSION_KEY => url('/setup')])
        ->get(route('zz.routed.extra', ['modelId' => $tenant->id, 'unboundParam' => 'x', 'modal' => true]))
        ->assertRedirect(url('/setup'));

    $arguments = session('noerd-modal.open')['arguments'];
    expect($arguments)->toHaveKey('modelId')
        ->not->toHaveKey('unboundParam');
});

it('maps the new sentinel back to a null modelId in the reopen instruction', function (): void {
    $response = $this->session([RememberPageUrl::SESSION_KEY => url('/setup')])
        ->get(route('zz.routed.page', ['modelId' => 'new', 'modal' => true]));

    $response->assertRedirect(url('/setup'));

    expect(session('noerd-modal.open')['arguments']['modelId'])->toBeNull();
});

it('returns to the last page, not to a pdf opened in another tab in between', function (): void {
    $tenant = Tenant::factory()->create();

    Route::middleware('noerd')->group(function (): void {
        Route::get('zz-routed-list', fn() => response('<html><body>list</body></html>'));
        Route::get('zz-routed-pdf', fn() => response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']));
    });

    $this->get('zz-routed-list')->assertOk();
    $this->get('zz-routed-pdf')->assertOk();

    $this->get(route('zz.routed.page', ['modelId' => $tenant->id, 'modal' => true]))
        ->assertRedirect(url('zz-routed-list'));
});
