<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Noerd\Helpers\AccessHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\SetupCollectionEntry;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;
use Noerd\Traits\NoerdList;
use Noerd\Traits\NoerdPage;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The client-callable surface of the list/detail/page traits: what the browser
 | can send (column filter keys, dispatched events, listener calls, mount
 | arguments, property updates) must not get around the permission checks and
 | must not reach the markup unescaped. Synthetic fixtures only.
 */

class ZzHardeningPosition extends Model
{
    protected $table = 'zz_hardening_positions';

    protected $guarded = [];
}

class ZzHardeningList extends Component
{
    use NoerdList;

    public function listData(): array
    {
        return $this->buildList(
            $this->listQuery(Tenant::class)->paginate($this->perPage),
        );
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-hardening-list';
    }

    protected function getListConfig(?string $customName = null): array
    {
        return [
            'title' => 'Zz Hardening',
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
            ],
        ];
    }

    /** @return array{0: Builder, 1: array, 2: string} */
    protected function prepareCsvExport(): array
    {
        return [Tenant::query(), [['field' => 'name', 'label' => 'Name']], 'tenants.csv'];
    }
}

/** A detail whose custom store() has no guard of its own — the hook must cover it. */
class ZzHardeningDetail extends Component
{
    use NoerdDetail;

    public $detailModel = Tenant::class;

    public ?string $detailPrimary = 'tenantId';

    public function store(): void
    {
        Tenant::query()->whereKey($this->modelId)->update(['name' => $this->detailData['name'] ?? 'Stored']);
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-hardening-detail';
    }

    protected function additionalDetailFields(): array
    {
        return ['detailData.name'];
    }
}

class ZzHardeningPage extends Component
{
    use NoerdPage;

    public $detailModel = Tenant::class;

    /** Like a settings page's model map — only a declared property takes a mount argument. */
    public array $settingsModels = [];

    public function mount(): void
    {
        $this->initPage();
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-hardening-page';
    }
}

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    Livewire::component('zz-hardening-list', ZzHardeningList::class);
    Livewire::component('zz-hardening-detail', ZzHardeningDetail::class);
    Livewire::component('zz-hardening-page', ZzHardeningPage::class);
});

describe('column filter keys', function (): void {
    it('turns only columns of the list into filter chips', function (): void {
        $component = Livewire::withQueryParams(['cf' => ["x'),alert(1),('" => '1', 'name' => 'Acme']])
            ->test('zz-hardening-list');

        $chips = $component->instance()->activeColumnFilterChips();

        expect(array_column($chips, 'field'))->toBe(['name']);
    });

    it('ignores a filter for a field the list does not show', function (): void {
        Livewire::test('zz-hardening-list')
            ->call('setColumnFilter', 'secret_column', 'x')
            ->call('setColumnFilter', 'name', 'Acme')
            ->assertSet('listColumnFilters', ['name' => 'Acme']);
    });

    it('renders the chip argument as a JavaScript literal', function (): void {
        $html = view('noerd::components.table.list-filters', [
            'tableFilters' => [],
            'listFilters' => [],
            'chips' => [['field' => "x'),alert(1),('", 'label' => 'X', 'value' => '1']],
            'hasClearAll' => false,
        ])->render();

        expect($html)->not->toContain("clearColumnFilter('x')")
            ->and($html)->toContain("clearColumnFilter('x" . '\\' . "u0027),alert(1),(" . '\\' . "u0027')");
    });
});

describe('write guard', function (): void {
    it('aborts a dispatched store event when the user may not write', function (): void {
        $tenant = Tenant::factory()->create(['name' => 'Original']);
        Gate::define(AccessHelper::OBJECT_WRITE_GATE, fn(?NoerdUser $user, string $modelClass): bool => false);

        Livewire::test('zz-hardening-detail', ['modelId' => $tenant->id])
            ->set('detailData.name', 'Injected')
            ->dispatch('storeDetail-zz-hardening-detail')
            ->assertForbidden();

        expect($tenant->refresh()->name)->toBe('Original');
    });

    it('still skips a direct store call silently', function (): void {
        $tenant = Tenant::factory()->create(['name' => 'Original']);
        Gate::define(AccessHelper::OBJECT_WRITE_GATE, fn(?NoerdUser $user, string $modelClass): bool => false);

        Livewire::test('zz-hardening-detail', ['modelId' => $tenant->id])
            ->set('detailData.name', 'Injected')
            ->call('store')
            ->assertOk();

        expect($tenant->refresh()->name)->toBe('Original');
    });

    it('lets a dispatched store event through when writing is allowed', function (): void {
        $tenant = Tenant::factory()->create(['name' => 'Original']);

        Livewire::test('zz-hardening-detail', ['modelId' => $tenant->id])
            ->set('detailData.name', 'Renamed')
            ->dispatch('storeDetail-zz-hardening-detail');

        expect($tenant->refresh()->name)->toBe('Renamed');
    });

    it('does not let a read-only user delete a position row', function (): void {
        Schema::create('zz_hardening_positions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
        $position = ZzHardeningPosition::query()->create(['name' => 'Pizza']);
        Gate::define(AccessHelper::OBJECT_WRITE_GATE, fn(?NoerdUser $user, string $modelClass): bool => false);

        Livewire::test('noerd-test::position-columns-row-test', [
            'modelClass' => ZzHardeningPosition::class,
            'positionId' => $position->id,
            'columns' => [],
        ])->call('delete');

        expect(ZzHardeningPosition::query()->whereKey($position->id)->exists())->toBeTrue();
    });
});

describe('embedded detail listener', function (): void {
    it('never repoints a page that already shows a record', function (): void {
        $shown = Tenant::factory()->create();
        $other = Tenant::factory()->create();

        Livewire::test('zz-hardening-page', ['modelId' => $shown->id])
            ->call('embeddedDetailStored', $other->id)
            ->assertSet('modelId', $shown->id);
    });

    it('adopts no record when the user may not save', function (): void {
        $tenant = Tenant::factory()->create();
        Gate::define(AccessHelper::OBJECT_CREATE_GATE, fn(?NoerdUser $user, string $modelClass): bool => false);

        Livewire::test('zz-hardening-page')
            ->call('embeddedDetailStored', $tenant->id)
            ->assertSet('modelId', null);
    });

    it('adopts the record a new page created through its detail', function (): void {
        $tenant = Tenant::factory()->create();

        Livewire::test('zz-hardening-page')
            ->call('embeddedDetailStored', $tenant->id)
            ->assertSet('modelId', $tenant->id);
    });
});

describe('locked surface', function (): void {
    it('rejects mount arguments that pick the model, layout or list view', function (string $argument, mixed $value): void {
        $component = $argument === 'listView' ? 'zz-hardening-list' : 'zz-hardening-page';

        // Livewire wraps the refusal in a ViewException — assert on the cause.
        try {
            Livewire::test($component, [$argument => $value]);
            $thrown = null;
        } catch (Throwable $e) {
            $thrown = $e->getPrevious() ?? $e;
        }

        expect($thrown)->toBeInstanceOf(CannotUpdateLockedPropertyException::class);
    })->with([
        'pageLayout' => ['pageLayout', ['fields' => []]],
        'settingsModels' => ['settingsModels', ['detailData' => NoerdUser::class]],
        'objectReadBlocked' => ['objectReadBlocked', false],
        'listView' => ['listView', 'secret'],
    ]);

    it('rejects client updates of the read guard and the list switches', function (string $component, string $property, mixed $value): void {
        expect(fn() => Livewire::test($component)->set($property, $value))
            ->toThrow(CannotUpdateLockedPropertyException::class);
    })->with([
        'objectReadBlocked' => ['zz-hardening-page', 'objectReadBlocked', false],
        'enableCsvExport' => ['zz-hardening-list', 'enableCsvExport', true],
        'minimal' => ['zz-hardening-list', 'minimal', true],
        'minimalLimit' => ['zz-hardening-list', 'minimalLimit', 100000],
    ]);

    it('refuses the CSV export of a list that did not enable it', function (): void {
        Livewire::test('zz-hardening-list')
            ->call('exportCsv')
            ->assertForbidden();
    });

    it('caps the row limit of a widget list', function (): void {
        Livewire::test('zz-hardening-list', ['minimal' => true, 'minimalLimit' => 100000])
            ->assertSet('perPage', 200);
    });
});

describe('core details', function (): void {
    it('creates no account under an id that does not resolve', function (): void {
        $this->actingAs(NoerdUser::factory()->adminUser()->superAdmin()->withSelectedApp('setup')->create());
        $countBefore = NoerdUser::query()->count();

        $component = Livewire::test('noerd::noerd-user-detail');
        $tenantId = array_key_first($component->get('possibleTenants'));

        expect($tenantId)->not->toBeNull();

        // $modelId is URL-bound and client-writable: repoint it after mounting.
        $component->set('modelId', 987654)
            ->set('detailData.name', 'Ghost')
            ->set('detailData.email', 'ghost@example.test')
            ->set("possibleTenants.{$tenantId}.hasAccess", true)
            ->call('store')
            ->assertHasNoErrors();

        expect(NoerdUser::query()->count())->toBe($countBefore);
    });

    it('creates no setup collection entry under an id that does not resolve', function (): void {
        $countBefore = SetupCollectionEntry::query()->count();

        Livewire::test('noerd::setup-collection-detail', ['collectionKey' => 'units'])
            ->set('modelId', 987654)
            ->set('detailData.name', 'Ghost')
            ->call('store');

        expect(SetupCollectionEntry::query()->count())->toBe($countBefore);
    });
});
