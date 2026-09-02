<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Helpers\AccessHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Support\WriteGuardHook;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;
use Noerd\Traits\NoerdList;
use Noerd\Traits\NoerdPage;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The create/write split: persisting a NEW record is gated by the create
 | ability, updating an existing one by write (canSaveObject() picks the
 | ability from the pre-store $modelId). List header "New …" buttons are
 | create affordances, every other header action stays gated by write.
 */

class ZzCreateGateDetail extends Component
{
    use NoerdDetail;

    public $detailModel = Tenant::class;

    public ?string $detailPrimary = 'tenantId';

    public function render(): string
    {
        return '<div></div>';
    }
}

class ZzCreateGateList extends Component
{
    use NoerdList;

    public $listModel = Tenant::class;

    public function with(): array
    {
        return [
            'listConfig' => $this->buildList(
                $this->listQuery(Tenant::class)->paginate($this->perPage),
            ),
        ];
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function getListConfig(?string $customName = null): array
    {
        return [
            'title' => 'Zz Tenants',
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
            ],
            'actions' => [
                ['label' => 'New Tenant', 'action' => 'listAction'],
                ['label' => 'New Via Route', 'route' => 'zz.tenant.detail'],
                ['label' => 'Import', 'action' => 'openImportModal'],
            ],
        ];
    }
}

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    Livewire::component('zz-create-gate-detail', ZzCreateGateDetail::class);
    Livewire::component('zz-create-gate-list', ZzCreateGateList::class);
});

function listActionLabels($component): array
{
    $actions = $component->viewData('listConfig')['listSettings']['actions'] ?? [];

    return array_column($actions, 'label');
}

it('blocks creating but not updating when only the create gate denies', function (): void {
    Gate::define(AccessHelper::OBJECT_CREATE_GATE, fn(?NoerdUser $user, string $modelClass): bool => false);

    $countBefore = Tenant::query()->count();

    Livewire::test('zz-create-gate-detail')
        ->set('detailData', validDetailPayload(Tenant::class))
        ->set('detailData.name', 'Create Blocked')
        ->call('store')
        ->assertSet('showSuccessIndicator', false);

    expect(Tenant::query()->count())->toBe($countBefore);

    $tenant = Tenant::factory()->create(['name' => 'Before']);
    Livewire::test('zz-create-gate-detail', ['modelId' => $tenant->id])
        ->set('detailData.name', 'After')
        ->call('store')
        ->assertSet('showSuccessIndicator', true);

    expect($tenant->refresh()->name)->toBe('After');
});

it('blocks updating but not creating when only the write gate denies', function (): void {
    Gate::define(AccessHelper::OBJECT_WRITE_GATE, fn(?NoerdUser $user, string $modelClass): bool => false);

    Livewire::test('zz-create-gate-detail')
        ->set('detailData', validDetailPayload(Tenant::class))
        ->set('detailData.name', 'Created Fresh')
        ->call('store')
        ->assertSet('showSuccessIndicator', true);

    expect(Tenant::query()->where('name', 'Created Fresh')->exists())->toBeTrue();

    $tenant = Tenant::factory()->create(['name' => 'Untouchable']);
    Livewire::test('zz-create-gate-detail', ['modelId' => $tenant->id])
        ->set('detailData.name', 'Changed')
        ->call('store');

    expect($tenant->refresh()->name)->toBe('Untouchable');
});

it('strips the New buttons but keeps custom actions when create is denied', function (): void {
    Gate::define(AccessHelper::OBJECT_CREATE_GATE, fn(?NoerdUser $user, string $modelClass): bool => false);

    $component = Livewire::test('zz-create-gate-list');

    expect(listActionLabels($component))->toBe(['Import']);
});

it('strips custom actions but keeps the New buttons when write is denied', function (): void {
    Gate::define(AccessHelper::OBJECT_WRITE_GATE, fn(?NoerdUser $user, string $modelClass): bool => false);

    $component = Livewire::test('zz-create-gate-list');

    expect(listActionLabels($component))->toBe(['New Tenant', 'New Via Route']);
});

it('strips every action when reading is denied', function (): void {
    Gate::define(AccessHelper::OBJECT_READ_GATE, fn(?NoerdUser $user, string $modelClass): bool => false);

    $component = Livewire::test('zz-create-gate-list');

    expect(listActionLabels($component))->toBe([])
        ->and($component->viewData('listConfig')['objectAccessDenied'])->toBeTrue();
});

it('keeps every action when nothing is denied', function (): void {
    $component = Livewire::test('zz-create-gate-list');

    expect(listActionLabels($component))->toBe(['New Tenant', 'New Via Route', 'Import']);
});

/*
 | WriteGuardHook applies the same abilities to a component that overrides
 | store()/delete() itself — the guard lives in the hook, not in the trait tail.
 */

it('WriteGuardHook skips store/delete when the object permission denies it', function (): void {
    $denied = new class {
        use NoerdPage;

        public function canWriteObject(): bool
        {
            return false;
        }

        // store() on a component without a $modelId is a CREATE — the hook
        // derives the ability via canSaveObject(), so both must deny here.
        public function canCreateObject(): bool
        {
            return false;
        }

        public function canDeleteObject(): bool
        {
            return false;
        }
    };

    $hook = new WriteGuardHook();
    $hook->setComponent($denied);

    $storeSkipped = false;
    $hook->call('store', [], function () use (&$storeSkipped): void {
        $storeSkipped = true;
    }, [], null);
    expect($storeSkipped)->toBeTrue();

    $deleteSkipped = false;
    $hook->call('delete', [], function () use (&$deleteSkipped): void {
        $deleteSkipped = true;
    }, [], null);
    expect($deleteSkipped)->toBeTrue();
});

it('WriteGuardHook lets the action run when writing is allowed', function (): void {
    $allowed = new class {
        use NoerdPage;

        public function canWriteObject(): bool
        {
            return true;
        }
    };

    $hook = new WriteGuardHook();
    $hook->setComponent($allowed);

    $skipped = false;
    $hook->call('store', [], function () use (&$skipped): void {
        $skipped = true;
    }, [], null);
    expect($skipped)->toBeFalse();

    // An unrelated method is never touched.
    $hook->call('someOtherAction', [], function () use (&$skipped): void {
        $skipped = true;
    }, [], null);
    expect($skipped)->toBeFalse();
});
