<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;

uses(TestCase::class, RefreshDatabase::class);

/*
 | detailLoaded() replaces the custom mount() that re-found the record only to
 | derive a value from it: the hook receives the loaded model (eager-loaded with
 | $detailWith) with $detailData already filled, and everything the trait does
 | afterwards — layout defaults, relation forms, custom attributes — still runs.
 */

class ZzDetailLoadedComponent extends Component
{
    use NoerdDetail;

    public $detailModel = NoerdUser::class;

    public ?string $detailPrimary = 'zzUserId';

    public ?bool $tenantsWereLoaded = null;

    public bool $loadedExisting = false;

    protected array $detailWith = ['tenants'];

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-detail-loaded-detail';
    }

    protected function detailLoaded(Model $model): void
    {
        $this->tenantsWereLoaded = $model->relationLoaded('tenants');
        $this->loadedExisting = $model->exists;
        $this->detailData['zz_tenant_count'] = $model->exists ? $model->tenants->count() : 0;
    }
}

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());
});

it('hands the eager-loaded record to detailLoaded() with the form already filled', function (): void {
    $user = NoerdUser::factory()->withExampleTenant()->create(['name' => 'Loaded User']);

    Livewire::test(ZzDetailLoadedComponent::class, ['modelId' => $user->id])
        ->assertSet('detailData.name', 'Loaded User')
        ->assertSet('detailData.zz_tenant_count', 1)
        ->assertSet('tenantsWereLoaded', true)
        ->assertSet('loadedExisting', true);
});

it('runs detailLoaded() for a new record with the fresh model', function (): void {
    Livewire::test(ZzDetailLoadedComponent::class)
        ->assertSet('detailData.zz_tenant_count', 0)
        ->assertSet('loadedExisting', false);
});
