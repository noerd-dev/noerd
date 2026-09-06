<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;
use Noerd\Traits\NoerdList;
use Noerd\Traits\NoerdPage;
use Noerd\Traits\ShowFromFilterTrait;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The naming hooks of the component traits (list config name, select event,
 | deep-link parameter, list entity, detail config name, paired list, and the
 | ShowFrom/ShowUntil date columns) resolve in a fixed order: a method override
 | wins over a configured property, the property wins over the derivation.
 | The traits declare none of the properties (a class redeclaring a trait
 | property with another default is a PHP fatal), so every hook must survive
 | the property being absent.
 */

function zzInvokeHook(object $component, string $method): mixed
{
    $reflection = new ReflectionMethod($component, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($component);
}

function zzListComponent(string $name): Component
{
    return new class ($name) extends Component {
        use NoerdList;

        public function __construct(private readonly string $fixtureName) {}

        public function render(): string
        {
            return '<div></div>';
        }

        protected function componentName(): string
        {
            return $this->fixtureName;
        }
    };
}

dataset('list naming hooks', [
    'listConfigComponent' => ['listConfigComponent', 'listConfigComponent', 'zz-gadgets-list', 'zz-other-list'],
    'selectEvent' => ['getSelectEvent', 'selectEvent', 'zzGadgetSelected', 'zzPartSelected'],
    'deepLinkParam' => ['getDeepLinkParam', 'deepLinkParam', 'zzGadgetId', 'zzPartId'],
    'listEntity' => ['getListEntity', 'listEntity', 'zz-gadget', 'zz-part'],
]);

it('derives the list naming hooks from the component name without a property', function (string $method, string $property, string $derived): void {
    $component = zzListComponent('zz-gadgets-list');

    expect(property_exists($component, $property))->toBeFalse()
        ->and(zzInvokeHook($component, $method))->toBe($derived);
})->with('list naming hooks');

it('lets a configured property win over the derivation of the list naming hooks', function (string $method, string $property, string $derived, string $configured): void {
    $component = new class extends Component {
        use NoerdList;

        protected string $listConfigComponent = 'zz-other-list';

        protected string $selectEvent = 'zzPartSelected';

        protected string $deepLinkParam = 'zzPartId';

        protected string $listEntity = 'zz-part';

        public function render(): string
        {
            return '<div></div>';
        }

        protected function componentName(): string
        {
            return 'zz-gadgets-list';
        }
    };

    expect(zzInvokeHook($component, $method))->toBe($configured);
})->with('list naming hooks');

it('lets a method override win over the configured property of the list naming hooks', function (string $method, string $property): void {
    $component = new class extends Component {
        use NoerdList;

        protected string $listConfigComponent = 'zz-ignored-list';

        protected string $selectEvent = 'zzIgnoredSelected';

        protected string $deepLinkParam = 'zzIgnoredId';

        protected string $listEntity = 'zz-ignored';

        public function render(): string
        {
            return '<div></div>';
        }

        protected function componentName(): string
        {
            return 'zz-gadgets-list';
        }

        protected function listConfigComponent(): string
        {
            return 'zz-method';
        }

        protected function getSelectEvent(): string
        {
            return 'zz-method';
        }

        protected function getDeepLinkParam(): string
        {
            return 'zz-method';
        }

        protected function getListEntity(): string
        {
            return 'zz-method';
        }
    };

    expect(zzInvokeHook($component, $method))->toBe('zz-method');
})->with('list naming hooks');

it('derives the select event and deep-link parameter from a configured list entity', function (): void {
    $component = new class extends Component {
        use NoerdList;

        protected string $listEntity = 'zz-widget';

        public function render(): string
        {
            return '<div></div>';
        }

        protected function componentName(): string
        {
            return 'zz-gadgets-list';
        }
    };

    expect(zzInvokeHook($component, 'getSelectEvent'))->toBe('zzWidgetSelected')
        ->and(zzInvokeHook($component, 'getDeepLinkParam'))->toBe('zzWidgetId');
});

dataset('page naming hooks', [
    'detailConfigComponent' => ['getDetailComponent', 'detailConfigComponent', 'zz-gadget-detail', 'zz-other-detail'],
    'listComponent' => ['getListComponent', 'listComponent', 'zz-gadgets-list', 'zz-other-list'],
]);

it('derives the page naming hooks from the component name without a property', function (string $method, string $property, string $derived): void {
    $component = new class extends Component {
        use NoerdDetail;

        public function render(): string
        {
            return '<div></div>';
        }

        protected function componentName(): string
        {
            return 'zz-gadget-detail';
        }
    };

    expect(property_exists($component, $property))->toBeFalse()
        ->and(zzInvokeHook($component, $method))->toBe($derived);
})->with('page naming hooks');

it('lets a configured property win over the derivation of the page naming hooks', function (string $method, string $property, string $derived, string $configured): void {
    $component = new class extends Component {
        use NoerdPage;

        protected string $detailConfigComponent = 'zz-other-detail';

        protected string $listComponent = 'zz-other-list';

        public function render(): string
        {
            return '<div></div>';
        }

        protected function componentName(): string
        {
            return 'zz-gadget-page';
        }
    };

    expect(zzInvokeHook($component, $method))->toBe($configured);
})->with('page naming hooks');

it('lets a method override win over the configured property of the page naming hooks', function (string $method): void {
    $component = new class extends Component {
        use NoerdDetail;

        protected string $detailConfigComponent = 'zz-ignored-detail';

        protected string $listComponent = 'zz-ignored-list';

        public function render(): string
        {
            return '<div></div>';
        }

        protected function componentName(): string
        {
            return 'zz-gadget-detail';
        }

        protected function getDetailComponent(): string
        {
            return 'zz-method';
        }

        protected function getListComponent(): string
        {
            return 'zz-method';
        }
    };

    expect(zzInvokeHook($component, $method))->toBe('zz-method');
})->with('page naming hooks');

it('refreshes the paired list a page configures when it closes', function (): void {
    $component = new class extends Component {
        use NoerdPage;

        protected string $listComponent = 'zz-configured-list';

        public function render(): string
        {
            return '<div></div>';
        }

        public function closePaired(): void
        {
            $this->closeModalProcess($this->getListComponent());
        }

        protected function componentName(): string
        {
            return 'zz-gadget-page';
        }
    };

    Livewire::test($component)
        ->call('closePaired')
        ->assertDispatched('refreshList-zz-configured-list')
        ->assertNotDispatched('refreshList-zz-gadgets-list');
});

dataset('show filter columns', [
    'from' => ['getShowFromDateColumn', 'showFromDateColumn'],
    'until' => ['getShowUntilDateColumn', 'showUntilDateColumn'],
]);

it('compares the ShowFrom/ShowUntil filters against created_at without a property', function (string $method, string $property): void {
    $component = new class extends Component {
        use NoerdList;
        use ShowFromFilterTrait;

        public function render(): string
        {
            return '<div></div>';
        }
    };

    expect(property_exists($component, $property))->toBeFalse()
        ->and(zzInvokeHook($component, $method))->toBe('created_at');
})->with('show filter columns');

it('compares the ShowFrom/ShowUntil filters against the configured columns', function (string $method): void {
    $component = new class extends Component {
        use NoerdList;
        use ShowFromFilterTrait;

        protected string $showFromDateColumn = 'valuta_date';

        protected string $showUntilDateColumn = 'valuta_date';

        public function render(): string
        {
            return '<div></div>';
        }
    };

    expect(zzInvokeHook($component, $method))->toBe('valuta_date');
})->with('show filter columns');

it('lets a method override win over the configured ShowFrom/ShowUntil columns', function (string $method): void {
    $component = new class extends Component {
        use NoerdList;
        use ShowFromFilterTrait;

        protected string $showFromDateColumn = 'ignored';

        protected string $showUntilDateColumn = 'ignored';

        public function render(): string
        {
            return '<div></div>';
        }

        protected function getShowFromDateColumn(): string
        {
            return 'booked_at';
        }

        protected function getShowUntilDateColumn(): string
        {
            return 'booked_at';
        }
    };

    expect(zzInvokeHook($component, $method))->toBe('booked_at');
})->with('show filter columns');

it('writes the configured ShowFrom/ShowUntil columns into the list query', function (): void {
    $component = new class extends Component {
        use NoerdList;
        use ShowFromFilterTrait;

        protected string $showFromDateColumn = 'valuta_date';

        protected string $showUntilDateColumn = 'booked_at';

        public function render(): string
        {
            return '<div></div>';
        }

        public function exposedApplyListFilters(mixed $query): void
        {
            $this->applyListFilters($query);
        }
    };

    $component->listFilters = ['show_from' => '2026-01-01', 'show_until' => '2026-03-31'];
    $query = \Noerd\Models\Tenant::query();
    $component->exposedApplyListFilters($query);

    $wheres = collect($query->getQuery()->wheres)->map(fn(array $where): string => "{$where['column']} {$where['operator']} {$where['value']}");

    expect($wheres->all())->toBe(['valuta_date >= 2026-01-01', 'booked_at <= 2026-03-31']);
});

it('keeps the naming-hook properties out of the client-writable surface', function (): void {
    $properties = ['listConfigComponent', 'detailConfigComponent', 'listComponent', 'listEntity', 'selectEvent', 'deepLinkParam', 'showFromDateColumn', 'showUntilDateColumn'];

    $hook = new \Noerd\Support\LockedPropertiesHook();
    $hook->setComponent(new class {
        use NoerdList;
    });

    foreach ($properties as $property) {
        expect(fn(): mixed => $hook->update($property, $property, 'tampered'))
            ->toThrow(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class, $property);
    }
});
