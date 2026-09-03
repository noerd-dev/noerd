<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;

uses(TestCase::class, RefreshDatabase::class);

/*
 | $modelId is URL bound and therefore client-controlled, and
 | resolvePicklistOptions() takes a method NAME from the client. Both are
 | proven here against a synthetic fixture layout — never against a shipped YAML.
 */

class ZzStoreGuardDetail extends Component
{
    use NoerdDetail;

    public $detailModel = Tenant::class;

    public ?string $detailPrimary = 'tenantId';

    /** A genuine options provider: public, argument-free, returns an array. */
    public function zzStatusOptions(): array
    {
        return ['ok' => 'Ok'];
    }

    /** Public and array-returning, but it needs an argument. */
    public function zzOptionsNeedingAnArgument(string $scope): array
    {
        return [$scope => $scope];
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-store-guard-detail';
    }
}

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    Livewire::component('zz-store-guard-detail', ZzStoreGuardDetail::class);

    File::put(base_path('app-configs/setup/details/zz-store-guard-detail.yml'), implode("\n", [
        'title: Zz Store Guard',
        'fields:',
        '  - name: detailData.name',
        '    label: Name',
        '    type: text',
        '  - name: detailData.uuid',
        '    label: Uuid',
        '    type: text',
    ]));
});

afterEach(function (): void {
    File::delete(base_path('app-configs/setup/details/zz-store-guard-detail.yml'));
});

it('never inserts a record under a modelId the scoped query cannot resolve', function (): void {
    $unresolvable = 987654;

    $component = Livewire::test('zz-store-guard-detail');
    $component->set('modelId', $unresolvable)
        ->set('detailData', ['name' => 'Injected', 'uuid' => (string) Str::uuid()])
        ->call('store');

    expect(Tenant::query()->whereKey($unresolvable)->exists())->toBeFalse()
        ->and(Tenant::query()->where('name', 'Injected')->exists())->toBeFalse();
});

it('still creates a record when no modelId is given and updates an existing one', function (): void {
    Livewire::test('zz-store-guard-detail')
        ->set('detailData', ['name' => 'Created Tenant', 'uuid' => (string) Str::uuid()])
        ->call('store')
        ->assertHasNoErrors();

    $created = Tenant::query()->where('name', 'Created Tenant')->firstOrFail();

    Livewire::test('zz-store-guard-detail', ['modelId' => $created->id])
        ->set('detailData.name', 'Renamed Tenant')
        ->call('store')
        ->assertHasNoErrors();

    expect($created->fresh()->name)->toBe('Renamed Tenant')
        ->and(Tenant::query()->where('name', 'Created Tenant')->exists())->toBeFalse();
});

it('only resolves picklist options from a public, argument-free array provider', function (): void {
    $component = Livewire::test('zz-store-guard-detail');

    // A genuine provider still works.
    expect($component->instance()->resolvePicklistOptions('zzStatusOptions'))->toBe(['ok' => 'Ok']);

    // Protected trait internals are unreachable, even though they return arrays.
    expect($component->instance()->resolvePicklistOptions('syncPayload'))->toBe([])
        ->and($component->instance()->resolvePicklistOptions('writableKeysFromFields'))->toBe([]);

    // A provider needing an argument is skipped instead of blowing up.
    expect($component->instance()->resolvePicklistOptions('zzOptionsNeedingAnArgument'))->toBe([]);
});
