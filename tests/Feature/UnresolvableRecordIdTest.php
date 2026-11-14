<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\SetupLanguage;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;

uses(TestCase::class, RefreshDatabase::class);

/*
 | An id that does not resolve — deleted, mistyped, or a record of another tenant
 | hidden by the tenant scope — must never mount an empty "new" form that would
 | create a record on save. Opened as the page of its own route it is a 404;
 | inside the modal stack (a Livewire update request) the modal closes and the
 | mount stops.
 */

class ZzUnresolvableLanguageDetail extends Component
{
    use NoerdDetail;

    public $detailModel = SetupLanguage::class;

    public ?string $detailPrimary = 'setupLanguageId';

    public function render(): string
    {
        return '<div>zz-language {{ $detailData[\'code\'] ?? \'\' }}</div>';
    }

    protected function componentName(): string
    {
        return 'zz-unresolvable-language-detail';
    }
}

beforeEach(function (): void {
    $this->user = NoerdUser::factory()->adminUser()->create();
    $this->actingAs($this->user);

    Livewire::component('zz-unresolvable-language-detail', ZzUnresolvableLanguageDetail::class);
    registerTestLivewireRoute(
        'zz-unresolvable-language/{modelId}',
        'zz-unresolvable-language-detail',
        'zz.unresolvable-language.detail',
    );
});

it('answers 404 when a full page load names an id that does not exist', function (): void {
    $this->get('/zz-unresolvable-language/999999')->assertNotFound();
});

it('answers 404 when a full page load names a record of another tenant', function (): void {
    $foreign = SetupLanguage::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    $this->get('/zz-unresolvable-language/' . $foreign->id)->assertNotFound();
});

it('renders the record of the own tenant on a full page load', function (): void {
    $own = SetupLanguage::factory()->create([
        'tenant_id' => TenantHelper::getSelectedTenantId(),
        'code' => 'zq',
    ]);

    $this->get('/zz-unresolvable-language/' . $own->id)
        ->assertOk()
        ->assertSee('zz-language zq');
});

it('closes the modal and stops mounting inside the modal stack', function (): void {
    Livewire::test('zz-unresolvable-language-detail', ['modelId' => 999999])
        ->assertSet('modelId', null)
        ->assertSet('pageLayout', [])
        ->assertDispatched('closeTopModal');
});
