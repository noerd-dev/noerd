<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Contracts\LayoutOverrideResolver;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Traits\NoerdList;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * The discovery walks the real config search roots, so the tests write uniquely
 * named YAML fixtures into app-configs/setup/lists/ and clean them up afterwards.
 */
beforeEach(function (): void {
    $this->fixtureDir = base_path('app-configs/setup/lists');
    $this->moduleFixtureDir = base_path('app-modules/noerd/app-configs/setup/lists');
    $this->fixtures = [];

    $this->writeFixture = function (string $dir, string $file, string $yaml): void {
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/{$file}", $yaml);
        $this->fixtures[] = "{$dir}/{$file}";
    };

    ($this->writeFixture)($this->fixtureDir, 'zz-view-test-list.yml', "title: Base View\ncolumns:\n  - field: name\n    label: Name");
    ($this->writeFixture)($this->fixtureDir, 'zz-view-test-list--vip.yml', "title: VIP View\ncolumns:\n  - field: name\n    label: Name\n  - field: id\n    label: Id");
    ($this->writeFixture)($this->fixtureDir, 'zz-view-test-list--active.yml', "title: Active View\ncolumns:\n  - field: id\n    label: Id");

    // Cross-app fixtures: a second allowed app ('zzotherapp') whose folder holds
    // its own copy of the list plus a same-key variant.
    $this->setUpOtherApp = function (): void {
        $tenant = Tenant::factory()->create();
        TenantHelper::setSelectedTenantId($tenant->id);
        TenantHelper::setSelectedApp('SETUP');

        $tenantApp = TenantApp::create([
            'name' => 'ZZOTHERAPP',
            'title' => 'Other App',
            'icon' => 'zzotherapp::icons.app',
            'route' => 'zzotherapp',
            'is_active' => true,
        ]);
        $tenant->tenantApps()->attach($tenantApp->id);

        $otherDir = base_path('app-configs/zzotherapp/lists');
        ($this->writeFixture)($otherDir, 'zz-view-test-list.yml', "title: Other Base\ncolumns:\n  - field: name\n    label: Name");
        ($this->writeFixture)($otherDir, 'zz-view-test-list--vip.yml', "title: Other VIP\ncolumns:\n  - field: id\n    label: Id");
    };
});

afterEach(function (): void {
    foreach ($this->fixtures as $fixture) {
        File::delete($fixture);
    }
    File::deleteDirectory(base_path('app-configs/zzotherapp'));
});

/**
 * The structured view entry getListViews() yields for a setup-app view.
 */
function zzSetupViewEntry(string $key, string $title): array
{
    return ['key' => $key, 'app' => 'setup', 'appLabel' => __('Setup'), 'title' => $title];
}

it('discovers only the default view when no variants exist', function (): void {
    ($this->writeFixture)($this->fixtureDir, 'zz-single-test-list.yml', 'title: Single View');

    expect(StaticConfigHelper::getListViews('zz-single-test-list'))
        ->toBe(['default' => zzSetupViewEntry('default', 'Single View')]);
});

it('discovers all views with default first and variants alphabetical', function (): void {
    expect(StaticConfigHelper::getListViews('zz-view-test-list'))->toBe([
        'default' => zzSetupViewEntry('default', 'Base View'),
        'active' => zzSetupViewEntry('active', 'Active View'),
        'vip' => zzSetupViewEntry('vip', 'VIP View'),
    ]);
});

it('shadows a module-source variant with the project variant of the same key', function (): void {
    ($this->writeFixture)($this->moduleFixtureDir, 'zz-view-test-list--vip.yml', 'title: Module VIP View');

    expect(StaticConfigHelper::getListViews('zz-view-test-list')['vip']['title'])->toBe('VIP View');
});

it('discovers a module-source variant that has no project counterpart', function (): void {
    ($this->writeFixture)($this->moduleFixtureDir, 'zz-view-test-list--module.yml', 'title: Module Only View');

    expect(StaticConfigHelper::getListViews('zz-view-test-list')['module']['title'])->toBe('Module Only View');
});

it('switches the active view and persists it in the session', function (): void {
    $component = Livewire::test(TestableListViewComponent::class)
        ->call('switchListView', 'vip');

    expect($component->get('listView'))->toBe('vip')
        ->and(session('listView.zz-view-test-list'))->toBe('vip');

    $config = new ReflectionMethod($component->instance(), 'getListConfig');

    expect($config->invoke($component->instance())['title'])->toBe('VIP View');
});

it('restores the saved view from the session on mount', function (): void {
    session(['listView.zz-view-test-list' => 'vip']);

    $component = Livewire::test(TestableListViewComponent::class);

    expect($component->get('listView'))->toBe('vip');
});

it('falls back to the default view when the saved view no longer exists', function (): void {
    session(['listView.zz-view-test-list' => 'gone']);

    $component = Livewire::test(TestableListViewComponent::class);

    expect($component->get('listView'))->toBeNull();

    $config = new ReflectionMethod($component->instance(), 'getListConfig');

    expect($config->invoke($component->instance())['title'])->toBe('Base View');
});

it('ignores switching to an unknown view', function (): void {
    $component = Livewire::test(TestableListViewComponent::class)
        ->call('switchListView', 'nope');

    expect($component->get('listView'))->toBeNull()
        ->and(session('listView.zz-view-test-list'))->toBeNull();
});

it('switches back to the default view', function (): void {
    $component = Livewire::test(TestableListViewComponent::class)
        ->call('switchListView', 'vip')
        ->call('switchListView', 'default');

    expect($component->get('listView'))->toBeNull()
        ->and(session('listView.zz-view-test-list'))->toBe('default');
});

it('clears the selection when switching views', function (): void {
    $component = Livewire::test(TestableListViewComponent::class)
        ->set('selectedRecordIds', [1, 2])
        ->call('switchListView', 'vip');

    expect($component->get('selectedRecordIds'))->toBe([]);
});

it('keeps the paired detail component free of the view suffix', function (): void {
    $component = Livewire::test(TestableListViewComponent::class)
        ->call('switchListView', 'vip');

    $paired = new ReflectionMethod($component->instance(), 'pairedDetailComponent');

    expect($paired->invoke($component->instance()))->toBe('zz-view-test-detail');
});

it('merges resolver-defined views into the discovery, files winning on key collision', function (): void {
    app()->instance(LayoutOverrideResolver::class, new class implements LayoutOverrideResolver
    {
        public function apply(string $viewType, string $component, array $config, ?string $modelClass = null): array
        {
            return $config;
        }

        public function filterListViews(string $component, array $views): array
        {
            return $views;
        }

        public function listViews(string $component): array
        {
            return $component === 'zz-view-test-list'
                ? ['db' => 'DB View', 'vip' => 'Must Not Shadow The File', 'default' => 'Must Not Shadow Either']
                : [];
        }
    });

    expect(StaticConfigHelper::getListViews('zz-view-test-list'))->toBe([
        'default' => zzSetupViewEntry('default', 'Base View'),
        'active' => zzSetupViewEntry('active', 'Active View'),
        'db' => zzSetupViewEntry('db', 'DB View'),
        'vip' => zzSetupViewEntry('vip', 'VIP View'),
    ]);
});

it('materializes a resolver-defined view as the base config plus its override', function (): void {
    app()->instance(LayoutOverrideResolver::class, new class implements LayoutOverrideResolver
    {
        public function apply(string $viewType, string $component, array $config, ?string $modelClass = null): array
        {
            if ($component === 'zz-view-test-list--db') {
                $config['title'] = 'DB View';
                $config['columns'] = [['field' => 'id', 'label' => 'Id']];
            }

            return $config;
        }

        public function filterListViews(string $component, array $views): array
        {
            return $views;
        }

        public function listViews(string $component): array
        {
            return $component === 'zz-view-test-list' ? ['db' => 'DB View'] : [];
        }
    });

    $config = StaticConfigHelper::getListConfig('zz-view-test-list--db');

    // Base YAML keys survive, the override shaped title and columns.
    expect($config['title'])->toBe('DB View')
        ->and(array_column($config['columns'], 'field'))->toBe(['id']);

    // A missing base still yields an empty config.
    expect(StaticConfigHelper::getListConfig('zz-does-not-exist-list--db'))->toBe([]);
});

it('activates the first allowed view when the resolver hides the default', function (): void {
    app()->instance(LayoutOverrideResolver::class, new class implements LayoutOverrideResolver
    {
        public function apply(string $viewType, string $component, array $config, ?string $modelClass = null): array
        {
            return $config;
        }

        public function listViews(string $component): array
        {
            return [];
        }

        public function filterListViews(string $component, array $views): array
        {
            unset($views['default']);

            return $views;
        }
    });

    $component = Livewire::test(TestableListViewComponent::class);

    expect($component->get('listView'))->toBe('active')
        ->and($component->instance()->availableListViews)->not->toHaveKey('default');
});

it('keeps the base view when the resolver hides every view', function (): void {
    app()->instance(LayoutOverrideResolver::class, new class implements LayoutOverrideResolver
    {
        public function apply(string $viewType, string $component, array $config, ?string $modelClass = null): array
        {
            return $config;
        }

        public function listViews(string $component): array
        {
            return [];
        }

        public function filterListViews(string $component, array $views): array
        {
            return [];
        }
    });

    $component = Livewire::test(TestableListViewComponent::class);

    expect($component->get('listView'))->toBeNull();

    $config = new ReflectionMethod($component->instance(), 'getListConfig');

    expect($config->invoke($component->instance())['title'])->toBe('Base View');
});

it('renders the view switcher only when multiple views exist', function (): void {
    $user = NoerdUser::factory()->adminUser()->withExampleTenant()->withSelectedApp('setup')->create();
    $this->actingAs($user);

    Livewire::test('noerd::user-roles-list')
        ->assertDontSee('switchListView');

    ($this->writeFixture)($this->fixtureDir, 'user-roles-list--test.yml', 'title: Test View');

    Livewire::test('noerd::user-roles-list')
        ->assertSee('switchListView')
        ->assertSee('Test View');
});

it('discovers other allowed apps views with composite keys, app labels and current app first', function (): void {
    ($this->setUpOtherApp)();

    expect(StaticConfigHelper::getListViews('zz-view-test-list'))->toBe([
        'default' => zzSetupViewEntry('default', 'Base View'),
        'active' => zzSetupViewEntry('active', 'Active View'),
        'vip' => zzSetupViewEntry('vip', 'VIP View'),
        'zzotherapp::default' => ['key' => 'default', 'app' => 'zzotherapp', 'appLabel' => 'Other App', 'title' => 'Other Base'],
        'zzotherapp::vip' => ['key' => 'vip', 'app' => 'zzotherapp', 'appLabel' => 'Other App', 'title' => 'Other VIP'],
    ]);
});

it('switches to another apps view and loads its config without changing the session app', function (): void {
    ($this->setUpOtherApp)();

    $component = Livewire::test(TestableListViewComponent::class)
        ->call('switchListView', 'zzotherapp::vip');

    expect($component->get('listView'))->toBe('vip')
        ->and($component->get('listViewApp'))->toBe('zzotherapp')
        ->and(session('listView.zz-view-test-list'))->toBe('zzotherapp::vip')
        ->and(session('noerd.selected_app'))->toBe('SETUP');

    $config = new ReflectionMethod($component->instance(), 'getListConfig');

    expect($config->invoke($component->instance())['title'])->toBe('Other VIP');
});

it('switches to another apps base view', function (): void {
    ($this->setUpOtherApp)();

    $component = Livewire::test(TestableListViewComponent::class)
        ->call('switchListView', 'zzotherapp::default');

    expect($component->get('listView'))->toBeNull()
        ->and($component->get('listViewApp'))->toBe('zzotherapp');

    $config = new ReflectionMethod($component->instance(), 'getListConfig');

    expect($config->invoke($component->instance())['title'])->toBe('Other Base');
});

it('restores a composite saved view from the session on mount', function (): void {
    ($this->setUpOtherApp)();
    session(['listView.zz-view-test-list' => 'zzotherapp::vip']);

    $component = Livewire::test(TestableListViewComponent::class);

    expect($component->get('listView'))->toBe('vip')
        ->and($component->get('listViewApp'))->toBe('zzotherapp');
});

it('collapses a saved composite key of the current app to its plain form', function (): void {
    ($this->setUpOtherApp)();
    session(['listView.zz-view-test-list' => 'setup::vip']);

    $component = Livewire::test(TestableListViewComponent::class);

    expect($component->get('listView'))->toBe('vip')
        ->and($component->get('listViewApp'))->toBeNull();
});

it('materializes a foreign view without its own YAML from the foreign base config', function (): void {
    ($this->setUpOtherApp)();

    $component = Livewire::test(TestableListViewComponent::class)
        ->call('switchListView', 'zzotherapp::vip');

    File::delete(base_path('app-configs/zzotherapp/lists/zz-view-test-list--vip.yml'));

    $config = new ReflectionMethod($component->instance(), 'getListConfig');

    expect($config->invoke($component->instance())['title'])->toBe('Other Base');
});

it('falls back to the default view when the foreign app config disappears mid-session', function (): void {
    ($this->setUpOtherApp)();

    $component = Livewire::test(TestableListViewComponent::class)
        ->call('switchListView', 'zzotherapp::vip');

    File::deleteDirectory(base_path('app-configs/zzotherapp'));

    $config = new ReflectionMethod($component->instance(), 'getListConfig');

    expect($config->invoke($component->instance())['title'])->toBe('Base View')
        ->and($component->instance()->listView)->toBeNull()
        ->and($component->instance()->listViewApp)->toBeNull();
});

it('applies the view URL param on mount and persists it in the session', function (): void {
    $component = Livewire::withQueryParams(['view' => 'vip'])
        ->test(TestableListViewComponent::class);

    expect($component->get('listView'))->toBe('vip')
        ->and($component->get('listViewParam'))->toBe('vip')
        ->and(session('listView.zz-view-test-list'))->toBe('vip');
});

it('lets the view URL param win over a different session-saved view', function (): void {
    session(['listView.zz-view-test-list' => 'active']);

    $component = Livewire::withQueryParams(['view' => 'vip'])
        ->test(TestableListViewComponent::class);

    expect($component->get('listView'))->toBe('vip')
        ->and(session('listView.zz-view-test-list'))->toBe('vip');
});

it('resets a session-saved view when the URL param names the default view', function (): void {
    session(['listView.zz-view-test-list' => 'vip']);

    $component = Livewire::withQueryParams(['view' => 'default'])
        ->test(TestableListViewComponent::class);

    expect($component->get('listView'))->toBeNull()
        ->and($component->get('listViewParam'))->toBe('default')
        ->and(session('listView.zz-view-test-list'))->toBe('default');
});

it('sets the view URL param to default on mount when multiple views exist', function (): void {
    $component = Livewire::test(TestableListViewComponent::class);

    expect($component->get('listView'))->toBeNull()
        ->and($component->get('listViewParam'))->toBe('default');
});

it('keeps the view URL param empty on single-view lists', function (): void {
    ($this->writeFixture)($this->fixtureDir, 'zz-single-view-test-list.yml', 'title: Single View');

    $component = Livewire::test(TestableSingleViewComponent::class);

    expect($component->get('listViewParam'))->toBeNull();
});

it('falls back to the session-saved view when the URL param is unknown', function (): void {
    session(['listView.zz-view-test-list' => 'active']);

    $component = Livewire::withQueryParams(['view' => 'nope'])
        ->test(TestableListViewComponent::class);

    expect($component->get('listView'))->toBe('active')
        ->and($component->get('listViewParam'))->toBe('active')
        ->and(session('listView.zz-view-test-list'))->toBe('active');
});

it('applies a composite view URL param without changing the session app', function (): void {
    ($this->setUpOtherApp)();

    $component = Livewire::withQueryParams(['view' => 'zzotherapp--vip'])
        ->test(TestableListViewComponent::class);

    expect($component->get('listView'))->toBe('vip')
        ->and($component->get('listViewApp'))->toBe('zzotherapp')
        ->and($component->get('listViewParam'))->toBe('zzotherapp--vip')
        ->and(session('listView.zz-view-test-list'))->toBe('zzotherapp::vip')
        ->and(session('noerd.selected_app'))->toBe('SETUP');
});

it('still accepts a legacy composite view URL param with a double colon', function (): void {
    ($this->setUpOtherApp)();

    $component = Livewire::withQueryParams(['view' => 'zzotherapp::vip'])
        ->test(TestableListViewComponent::class);

    expect($component->get('listView'))->toBe('vip')
        ->and($component->get('listViewApp'))->toBe('zzotherapp')
        ->and($component->get('listViewParam'))->toBe('zzotherapp--vip');
});

it('collapses a composite view URL param of the current app to its plain form', function (): void {
    ($this->setUpOtherApp)();

    $component = Livewire::withQueryParams(['view' => 'setup--vip'])
        ->test(TestableListViewComponent::class);

    expect($component->get('listView'))->toBe('vip')
        ->and($component->get('listViewApp'))->toBeNull()
        ->and($component->get('listViewParam'))->toBe('vip');
});

it('syncs the view URL param when switching views', function (): void {
    $component = Livewire::test(TestableListViewComponent::class)
        ->call('switchListView', 'vip');

    expect($component->get('listViewParam'))->toBe('vip');

    $component->call('switchListView', 'default');

    expect($component->get('listViewParam'))->toBe('default');
});

it('writes a composite view URL param with a double dash when switching', function (): void {
    ($this->setUpOtherApp)();

    $component = Livewire::test(TestableListViewComponent::class)
        ->call('switchListView', 'zzotherapp::vip');

    expect($component->get('listViewParam'))->toBe('zzotherapp--vip');
});

it('resolves list configs flat even for nested component names', function (): void {
    expect(StaticConfigHelper::getListConfig('setup::zzsubfolder.zz-view-test-list')['title'])->toBe('Base View')
        ->and(StaticConfigHelper::getListViews('zzsubfolder.zz-view-test-list'))->toHaveKeys(['default', 'active', 'vip'])
        ->and(StaticConfigHelper::resolveConfigPath('setup', 'list', 'zzsubfolder.zz-view-test-list'))
        ->toBe("{$this->fixtureDir}/zz-view-test-list.yml");
});

it('ignores the view URL param on embedded compact lists', function (): void {
    $component = Livewire::withQueryParams(['view' => 'vip'])
        ->test(TestableListViewComponent::class, ['compact' => true]);

    expect($component->get('listView'))->toBeNull()
        ->and($component->get('listViewParam'))->toBeNull()
        ->and(session('listView.zz-view-test-list'))->toBeNull();
});

it('parses and composes list view keys', function (): void {
    expect(StaticConfigHelper::parseListViewKey('vip'))->toBe([null, 'vip'])
        ->and(StaticConfigHelper::parseListViewKey('default'))->toBe([null, 'default'])
        ->and(StaticConfigHelper::parseListViewKey('gastro::vip'))->toBe(['gastro', 'vip'])
        ->and(StaticConfigHelper::parseListViewKey('gastro::'))->toBe(['gastro', 'default'])
        ->and(StaticConfigHelper::composeListViewKey(null, 'vip'))->toBe('vip')
        ->and(StaticConfigHelper::composeListViewKey(null, null))->toBe('default')
        ->and(StaticConfigHelper::composeListViewKey('gastro', 'vip'))->toBe('gastro::vip')
        ->and(StaticConfigHelper::composeListViewKey('gastro', null))->toBe('gastro::default');
});

class TestableListViewComponent extends Component
{
    use NoerdList;

    public const COMPONENT = 'zz-view-test-list';

    public const DETAIL_COMPONENT = 'zz-view-test-list';

    public function render(): string
    {
        return '<div></div>';
    }
}

class TestableSingleViewComponent extends Component
{
    use NoerdList;

    public const COMPONENT = 'zz-single-view-test-list';

    public const DETAIL_COMPONENT = 'zz-single-view-test-list';

    public function render(): string
    {
        return '<div></div>';
    }
}
