<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Models\NoerdUser;
use Noerd\Traits\NoerdList;

uses(Tests\TestCase::class, RefreshDatabase::class);

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
});

afterEach(function (): void {
    foreach ($this->fixtures as $fixture) {
        File::delete($fixture);
    }
});

it('discovers only the default view when no variants exist', function (): void {
    ($this->writeFixture)($this->fixtureDir, 'zz-single-test-list.yml', 'title: Single View');

    expect(StaticConfigHelper::getListViews('zz-single-test-list'))
        ->toBe(['default' => 'Single View']);
});

it('discovers all views with default first and variants alphabetical', function (): void {
    expect(StaticConfigHelper::getListViews('zz-view-test-list'))->toBe([
        'default' => 'Base View',
        'active' => 'Active View',
        'vip' => 'VIP View',
    ]);
});

it('shadows a module-source variant with the project variant of the same key', function (): void {
    ($this->writeFixture)($this->moduleFixtureDir, 'zz-view-test-list--vip.yml', 'title: Module VIP View');

    expect(StaticConfigHelper::getListViews('zz-view-test-list')['vip'])->toBe('VIP View');
});

it('discovers a module-source variant that has no project counterpart', function (): void {
    ($this->writeFixture)($this->moduleFixtureDir, 'zz-view-test-list--module.yml', 'title: Module Only View');

    expect(StaticConfigHelper::getListViews('zz-view-test-list')['module'])->toBe('Module Only View');
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

it('renders the view switcher only when multiple views exist', function (): void {
    $user = NoerdUser::factory()->adminUser()->withExampleTenant()->withSelectedApp('setup')->create();
    $this->actingAs($user);

    Livewire::test('noerd::setup.user-roles-list')
        ->assertDontSee('switchListView');

    ($this->writeFixture)($this->fixtureDir, 'user-roles-list--test.yml', 'title: Test View');

    Livewire::test('noerd::setup.user-roles-list')
        ->assertSee('switchListView')
        ->assertSee('Test View');
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
