<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Models\NoerdUser;
use Noerd\Models\Profile;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdSettingsPage;

uses(TestCase::class, RefreshDatabase::class);

/*
 | Write guards of a settings page. $settingsModels decides WHICH model
 | persistSettings() writes and WHICH property supplies the payload — a client
 | able to repoint it could write an arbitrary tenant-scoped model with
 | unfiltered keys (e.g. flipping the own tenant profile to key=ADMIN, a full
 | privilege escalation). Two independent defenses are asserted here, so
 | neither can regress unnoticed:
 |   1. the property is locked against client updates, and
 |   2. persistSettings() fails CLOSED for a property the YAML does not bind.
 */

beforeEach(function (): void {
    $user = NoerdUser::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->tenants()->attach($tenant->id);
    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('ZZGUARDAPP');
    $this->actingAs($user);
    $this->tenantId = $tenant->id;

    File::ensureDirectoryExists(base_path('app-configs/zzguardapp/settings'));
    File::put(base_path('app-configs/zzguardapp/settings/zz-guard-settings-page.yml'), implode("\n", [
        'title: Guard Fixture',
        'fields:',
        '  - name: detailData.currency',
        '    label: Currency',
        '    type: text',
    ]));

    Livewire::component('zz-guard-settings-page', new class extends Component {
        use NoerdSettingsPage;

        public array $settingsModels = ['detailData' => NoerdSettings::class];

        public function render(): string
        {
            return '<div>zz-guard</div>';
        }
    });
});

afterEach(function (): void {
    File::deleteDirectory(base_path('app-configs/zzguardapp'));
});

it('rejects a client update to the settings model map', function (): void {
    expect(fn() => Livewire::test('zz-guard-settings-page')->set('settingsModels', ['detailData' => Profile::class]))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('writes nothing for a bound property the settings YAML does not declare', function (): void {
    // Second line of defense, asserted independently of the lock: even with a
    // model map naming an undeclared property, no unfiltered write may happen.
    $profile = Profile::create(['tenant_id' => $this->tenantId, 'key' => 'USER', 'name' => 'Member']);

    $component = Livewire::test('zz-guard-settings-page');
    // Server-side repoint (simulates any path that sets the map after mount).
    $component->instance()->settingsModels = ['relationTitles' => Profile::class];

    $component->set('relationTitles', ['key' => 'ADMIN', 'name' => 'pwned'])
        ->call('store');

    expect($profile->refresh()->key)->toBe('USER')
        ->and($profile->name)->toBe('Member')
        ->and(Profile::where('tenant_id', $this->tenantId)->count())->toBe(1);
});

it('still persists the declared keys of a declared property', function (): void {
    Livewire::test('zz-guard-settings-page')
        ->set('detailData.currency', 'CHF')
        ->call('store')
        ->assertHasNoErrors();

    expect(NoerdSettings::where('tenant_id', $this->tenantId)->first()->currency)->toBe('CHF');
});

it('never writes an undeclared column of the declared model', function (): void {
    Livewire::test('zz-guard-settings-page')
        ->set('detailData.currency', 'EUR')
        ->set('detailData.detail_theme', 'numbered')
        ->call('store');

    $settings = NoerdSettings::where('tenant_id', $this->tenantId)->first();

    expect($settings->currency)->toBe('EUR')
        ->and($settings->detail_theme)->not->toBe('numbered');
});
