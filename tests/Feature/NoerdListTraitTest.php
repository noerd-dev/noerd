<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Default sorting is list YAML configuration (`defaultSort:`), so the sort
 * tests write a uniquely named fixture YAML into app-configs/setup/lists/
 * and clean it up afterwards (same pattern as ListViewsTest).
 */
beforeEach(function (): void {
    $this->sortFixtureFile = base_path('app-configs/setup/lists/zz-default-sort-test-list.yml');

    $this->writeSortFixture = function (string $yaml): void {
        File::ensureDirectoryExists(dirname($this->sortFixtureFile));
        File::put($this->sortFixtureFile, $yaml);
    };
});

afterEach(function (): void {
    File::delete($this->sortFixtureFile);
});

it('applies the YAML defaultSort when the user has not sorted yet', function (): void {
    ($this->writeSortFixture)("title: Fixture\ndefaultSort:\n  field: name\n  direction: asc");

    $component = Livewire::test(TestableNoerdListYamlSortComponent::class);

    expect($component->get('sortField'))->toBe('name');
    expect($component->get('sortAsc'))->toBe(true);
});

it('defaults the YAML defaultSort direction to descending', function (): void {
    ($this->writeSortFixture)("title: Fixture\ndefaultSort:\n  field: created_at");

    $component = Livewire::test(TestableNoerdListYamlSortComponent::class);

    expect($component->get('sortField'))->toBe('created_at');
    expect($component->get('sortAsc'))->toBe(false);
});

it('lets a session-saved sort win over the YAML defaultSort', function (): void {
    ($this->writeSortFixture)("title: Fixture\ndefaultSort:\n  field: name\n  direction: asc");
    session(['listSort.zz-default-sort-test-list' => ['field' => 'email', 'asc' => false]]);

    $component = Livewire::test(TestableNoerdListYamlSortComponent::class);

    expect($component->get('sortField'))->toBe('email');
    expect($component->get('sortAsc'))->toBe(false);
});

it('ignores a defaultSort without a field', function (): void {
    ($this->writeSortFixture)("title: Fixture\ndefaultSort:\n  direction: asc");

    $component = Livewire::test(TestableNoerdListYamlSortComponent::class);

    expect($component->get('sortField'))->toBe('id');
    expect($component->get('sortAsc'))->toBe(false);
});

it('sorts by id descending without a defaultSort in the YAML', function (): void {
    $component = Livewire::test(TestableNoerdListDefaultComponent::class);

    expect($component->get('sortField'))->toBe('id');
    expect($component->get('sortAsc'))->toBe(false);
});

it('treats only undotted, non-action, allowed columns as sortable', function (): void {
    $component = new TestableNoerdListDefaultComponent();

    expect($component->isSortableColumn('name'))->toBeTrue()
        ->and($component->isSortableColumn('customer.name'))->toBeFalse()
        ->and($component->isSortableColumn('custom_attributes.color'))->toBeFalse()
        ->and($component->isSortableColumn('action'))->toBeFalse()
        ->and($component->isSortableColumn('city', ['city']))->toBeFalse()
        ->and($component->isSortableColumn('city', ['name']))->toBeTrue();
});

it('sets the sort direction without changing the sort field', function (): void {
    ($this->writeSortFixture)("title: Fixture\ndefaultSort:\n  field: created_at");

    $component = Livewire::test(TestableNoerdListYamlSortComponent::class)
        ->call('setSortDirection', true);

    expect($component->get('sortField'))->toBe('created_at')
        ->and($component->get('sortAsc'))->toBeTrue();

    $component->call('setSortDirection', true);
    expect($component->get('sortAsc'))->toBeTrue();

    $component->call('setSortDirection', false);
    expect($component->get('sortAsc'))->toBeFalse();
});

it('sets the sort direction for a field that is no sortable column', function (): void {
    // `id` is the technical default sort and never a YAML column — the direction entries
    // of the grid sort dropdown must still work there.
    $component = Livewire::test(TestableNoerdListDefaultComponent::class)
        ->call('setSortDirection', true);

    expect($component->get('sortField'))->toBe('id')
        ->and($component->get('sortAsc'))->toBeTrue();
});

it('persists the sort direction to the session', function (): void {
    ($this->writeSortFixture)("title: Fixture\ndefaultSort:\n  field: created_at");

    Livewire::test(TestableNoerdListYamlSortComponent::class)->call('setSortDirection', true);

    expect(session('listSort.zz-default-sort-test-list'))
        ->toBe(['field' => 'created_at', 'asc' => true]);
});

it('derives select event name from plain list component', function (): void {
    $component = new TestableSelectEventPlainComponent();
    $method = new ReflectionMethod($component, 'getSelectEvent');
    $method->setAccessible(true);

    expect($method->invoke($component))->toBe('customerSelected');
});

it('derives select event name from namespaced list component', function (): void {
    $component = new TestableSelectEventNamespacedComponent();
    $method = new ReflectionMethod($component, 'getSelectEvent');
    $method->setAccessible(true);

    expect($method->invoke($component))->toBe('customerSelected');
});

it('derives select event name from dotted namespaced list component', function (): void {
    $component = new TestableSelectEventDottedComponent();
    $method = new ReflectionMethod($component, 'getSelectEvent');
    $method->setAccessible(true);

    expect($method->invoke($component))->toBe('customerSelected');
});

/**
 * Test component whose list YAML fixture drives the default sort.
 */
class TestableNoerdListYamlSortComponent extends Component
{
    use NoerdList;

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-default-sort-test-list';
    }
}

/**
 * Test component without a list YAML.
 */
class TestableNoerdListDefaultComponent extends Component
{
    use NoerdList;

    public function render(): string
    {
        return '<div></div>';
    }
}

class TestableSelectEventPlainComponent extends Component
{
    use NoerdList;

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'customers-list';
    }
}

class TestableSelectEventNamespacedComponent extends Component
{
    use NoerdList;

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'booking-members::customers-list';
    }
}

class TestableSelectEventDottedComponent extends Component
{
    use NoerdList;

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'booking-members::customers.customers-list';
    }
}
