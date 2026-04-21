<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Services\ListQueryContext;
use Noerd\Traits\NoerdList;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('sets default sort field and direction via setDefaultSort', function (): void {
    $component = Livewire::test(TestableNoerdListComponent::class);

    expect($component->get('sortField'))->toBe('created_at');
    expect($component->get('sortAsc'))->toBe(false);
});

it('sets ascending sort when specified', function (): void {
    $component = Livewire::test(TestableNoerdListAscComponent::class);

    expect($component->get('sortField'))->toBe('name');
    expect($component->get('sortAsc'))->toBe(true);
});

it('syncs sort state to ListQueryContext', function (): void {
    Livewire::test(TestableNoerdListComponent::class);

    $context = app(ListQueryContext::class);

    expect($context->getSortField())->toBe('created_at');
    expect($context->getSortAsc())->toBe(false);
});

it('uses default sort when no setDefaultSort is called', function (): void {
    $component = Livewire::test(TestableNoerdListDefaultComponent::class);

    expect($component->get('sortField'))->toBe('id');
    expect($component->get('sortAsc'))->toBe(false);
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
 * Test component with descending sort.
 */
class TestableNoerdListComponent extends Component
{
    use NoerdList;

    public function mount(): void
    {
        $this->mountList();
        $this->setDefaultSort('created_at', false);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

/**
 * Test component with ascending sort.
 */
class TestableNoerdListAscComponent extends Component
{
    use NoerdList;

    public function mount(): void
    {
        $this->mountList();
        $this->setDefaultSort('name', true);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

/**
 * Test component without setDefaultSort.
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

    public const COMPONENT = 'customers-list';

    public function render(): string
    {
        return '<div></div>';
    }
}

class TestableSelectEventNamespacedComponent extends Component
{
    use NoerdList;

    public const COMPONENT = 'booking-members::customers-list';

    public function render(): string
    {
        return '<div></div>';
    }
}

class TestableSelectEventDottedComponent extends Component
{
    use NoerdList;

    public const COMPONENT = 'booking-members::customers.customers-list';

    public function render(): string
    {
        return '<div></div>';
    }
}
