<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;

uses(TestCase::class, RefreshDatabase::class);

/*
 | Search and sort mechanics of NoerdList::listQuery(). The column filters have
 | their own suite (NoerdListColumnFilterTest) — asserted here is only what no
 | other file proves: the searchableColumns whitelist, LIKE-wildcard escaping,
 | the silent skip of unknown search columns, and the fail-closed sort fallback
 | for a client-written sortField ($sortField is deliberately client-writable;
 | only sortBy() runs the isSortableColumn() guard).
 */

function queryListIds(mixed $component): array
{
    return \Livewire\invade($component->instance())->visibleRowIds();
}

it('searches only the declared searchableColumns', function (): void {
    $nameMatch = NoerdUser::factory()->create(['name' => 'Findme Alpha', 'email' => 'alpha@example.test']);
    NoerdUser::factory()->create(['name' => 'Other', 'email' => 'findme@example.test']);

    ZzListQueryComponent::$searchable = ['name'];

    $component = Livewire::test(ZzListQueryComponent::class)
        ->set('search', 'findme');

    // email IS a listed column but not searchable — the email match must not appear.
    expect(queryListIds($component))->toBe([$nameMatch->id]);
});

it('matches LIKE wildcards in the search input literally', function (): void {
    $literal = NoerdUser::factory()->create(['name' => '100%']);
    NoerdUser::factory()->create(['name' => '100x']);

    ZzListQueryComponent::$searchable = ['name'];

    $component = Livewire::test(ZzListQueryComponent::class)
        ->set('search', '100%');

    expect(queryListIds($component))->toBe([$literal->id]);
});

it('ignores searchable columns the table does not have', function (): void {
    $match = NoerdUser::factory()->create(['name' => 'Findme Beta']);
    NoerdUser::factory()->create(['name' => 'Other']);

    ZzListQueryComponent::$searchable = ['ghost_column', 'name'];

    $component = Livewire::test(ZzListQueryComponent::class)
        ->set('search', 'findme');

    expect(queryListIds($component))->toBe([$match->id]);
});

it('falls back to id ordering when the client writes a hidden or unknown sortField', function (string $rawSortField): void {
    $first = NoerdUser::factory()->create(['name' => 'Zeta']);
    $second = NoerdUser::factory()->create(['name' => 'Alpha']);

    ZzListQueryComponent::$searchable = [];

    // Raw property write — bypasses the sortBy() guard on purpose.
    $component = Livewire::test(ZzListQueryComponent::class)
        ->set('sortAsc', true)
        ->set('sortField', $rawSortField);

    expect(queryListIds($component))->toBe([$first->id, $second->id]);
})->with([
    'hidden column' => ['password'],
    'unknown column' => ['ghost_column'],
]);

it('refuses sortBy for a non-sortable column and keeps the current sort', function (): void {
    ZzListQueryComponent::$searchable = [];

    $component = Livewire::test(ZzListQueryComponent::class)
        ->call('sortBy', 'name');

    expect($component->get('sortField'))->toBe('name');

    $component->call('sortBy', 'action')
        ->call('sortBy', 'email.domain');

    expect($component->get('sortField'))->toBe('name');
});

/**
 * List component with an inline YAML config over the noerd_users table; the
 * searchable columns vary per test through the static property.
 */
class ZzListQueryComponent extends Component
{
    use NoerdList;

    /** @var array<int, string> */
    public static array $searchable = [];

    public function listData(): array
    {
        return $this->buildList(
            $this->listQuery(NoerdUser::class)->paginate($this->perPage),
        );
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-list-query';
    }

    protected function getListConfig(?string $customName = null): array
    {
        return [
            'title' => 'Zz List Query',
            'searchableColumns' => self::$searchable,
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
                ['field' => 'email', 'label' => 'Email'],
                ['field' => 'email.domain', 'label' => 'Domain'],
                ['field' => 'id', 'label' => 'Id'],
            ],
        ];
    }
}

/*
 | The declared list properties: eager loads and counts for every page, the
 | parent scope of an embedded list and the clamped page size — what a custom
 | listData() used to do by hand.
 */
describe('declared query properties', function (): void {
    beforeEach(function (): void {
        ZzDeclaredListComponent::$zzEmail = null;
    });

    it('eager-loads $listWith and counts $listWithCount on every row', function (): void {
        $user = NoerdUser::factory()->withExampleTenant()->create();

        $rows = \Livewire\invade(Livewire::test(ZzDeclaredListComponent::class)->instance())->builtListConfig()['rows'];
        $row = $rows->getCollection()->firstWhere('id', $user->id);

        expect($row->relationLoaded('tenants'))->toBeTrue()
            ->and((int) $row->tenants_count)->toBe(1);
    });

    it('narrows the query by a $listScopes property while it holds a value', function (): void {
        $alpha = NoerdUser::factory()->create(['email' => 'alpha@example.com']);
        NoerdUser::factory()->create(['email' => 'beta@example.com']);

        expect(queryListIds(Livewire::test(ZzDeclaredListComponent::class)))->toHaveCount(NoerdUser::query()->count());

        ZzDeclaredListComponent::$zzEmail = 'alpha@example.com';

        expect(queryListIds(Livewire::test(ZzDeclaredListComponent::class)))->toBe([$alpha->id]);
    });

    it('clamps the client-controlled page size through paginateList()', function (): void {
        $instance = Livewire::test(ZzDeclaredListComponent::class)->instance();
        // A client payload may carry any $perPage; the query never pages larger
        // than NoerdList::MAX_PER_PAGE (the bound of the per-page select).
        $instance->perPage = 100000;

        $rows = \Livewire\invade($instance)->paginateList(NoerdUser::query());

        expect($rows->perPage())->toBe(200);
    });
});

/** A slim list whose query shape comes from the declared properties only. */
class ZzDeclaredListComponent extends Component
{
    use NoerdList;

    public static ?string $zzEmail = null;

    public $listModel = NoerdUser::class;

    public ?string $scopedEmail = null;

    protected array $listWith = ['tenants'];

    protected array $listWithCount = ['tenants'];

    protected array $listScopes = ['email' => 'scopedEmail'];

    public function mount(): void
    {
        $this->scopedEmail = self::$zzEmail;
        $this->mountList();
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-declared-list';
    }

    protected function getListConfig(?string $customName = null): array
    {
        return [
            'title' => 'Zz Declared',
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
                ['field' => 'email', 'label' => 'Email'],
            ],
        ];
    }
}
