<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Helpers\AccessHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdPage;

uses(TestCase::class, RefreshDatabase::class);

/*
 | Hand-built page blades read the embedded detail name unguarded from the page
 | layout ($pageLayout['detail']) — and Blade evaluates that slot content even
 | when the page chrome discards it for the object-access-denied state. The page
 | layout therefore must be loaded even when the guarded init bails out
 | (read-denied object, stale record id) instead of crashing the render with
 | "Undefined array key 'detail'".
 */

class ZzReadBlockedPage extends Component
{
    use NoerdPage;

    public $detailModel = Tenant::class;

    public ?string $detailPrimary = 'tenantId';

    public function render(): string
    {
        // Mirrors the hand-built page blades: the detail name is read UNGUARDED.
        return '<div>{{ $pageLayout[\'detail\'] }}</div>';
    }
}

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    Livewire::component('zz-read-blocked-page', ZzReadBlockedPage::class);

    File::ensureDirectoryExists(base_path('app-configs/setup/pages'));
    File::put(
        base_path('app-configs/setup/pages/zz-read-blocked-page.yml'),
        "title: Fixture Page\ndetail: noerd::zz-read-blocked-detail\n",
    );
});

afterEach(function (): void {
    File::delete(base_path('app-configs/setup/pages/zz-read-blocked-page.yml'));
});

it('keeps the page layout loaded when the object read gate denies', function (): void {
    Gate::define(AccessHelper::OBJECT_READ_GATE, fn(?NoerdUser $user, string $modelClass): bool => false);

    Livewire::test('zz-read-blocked-page')
        ->assertSet('objectReadBlocked', true)
        ->assertSee('noerd::zz-read-blocked-detail');
});

it('keeps the page layout loaded when the record id no longer resolves', function (): void {
    Livewire::test('zz-read-blocked-page', ['modelId' => 999999])
        ->assertSet('modelId', null)
        ->assertSee('noerd::zz-read-blocked-detail');
});

it('loads the record and the page layout when reading is allowed', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Readable Tenant']);

    Livewire::test('zz-read-blocked-page', ['modelId' => $tenant->id])
        ->assertSet('objectReadBlocked', false)
        ->assertSet('detailData.name', 'Readable Tenant')
        ->assertSee('noerd::zz-read-blocked-detail');
});
