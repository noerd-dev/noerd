<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;
use Noerd\Traits\NoerdList;
use Noerd\Traits\NoerdPage;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The naming hooks of the component traits (list config name, select event,
 | deep-link parameter, list entity, detail config name, paired list) derive
 | their value from the component name; a method override replaces the
 | derivation.
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
    'listConfigComponent' => ['listConfigComponent', 'zz-gadgets-list'],
    'selectEvent' => ['getSelectEvent', 'zzGadgetSelected'],
    'deepLinkParam' => ['getDeepLinkParam', 'zzGadgetId'],
    'listEntity' => ['getListEntity', 'zz-gadget'],
]);

it('derives the list naming hooks from the component name', function (string $method, string $derived): void {
    expect(zzInvokeHook(zzListComponent('zz-gadgets-list'), $method))->toBe($derived);
})->with('list naming hooks');

it('lets a method override replace the derivation of the list naming hooks', function (string $method): void {
    $component = new class extends Component {
        use NoerdList;

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

it('derives the select event and deep-link parameter from an overridden list entity', function (): void {
    $component = new class extends Component {
        use NoerdList;

        public function render(): string
        {
            return '<div></div>';
        }

        protected function componentName(): string
        {
            return 'zz-gadgets-list';
        }

        protected function getListEntity(): string
        {
            return 'zz-widget';
        }
    };

    expect(zzInvokeHook($component, 'getSelectEvent'))->toBe('zzWidgetSelected')
        ->and(zzInvokeHook($component, 'getDeepLinkParam'))->toBe('zzWidgetId');
});

dataset('page naming hooks', [
    'detailComponent' => ['getDetailComponent', 'zz-gadget-detail'],
    'listComponent' => ['getListComponent', 'zz-gadgets-list'],
]);

it('derives the page naming hooks from the component name', function (string $method, string $derived): void {
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

    expect(zzInvokeHook($component, $method))->toBe($derived);
})->with('page naming hooks');

it('lets a method override replace the derivation of the page naming hooks', function (string $method): void {
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

it('refreshes the paired list a page overrides when it closes', function (): void {
    $component = new class extends Component {
        use NoerdPage;

        public function render(): string
        {
            return '<div></div>';
        }

        public function closePaired(): void
        {
            $this->closeModalProcess($this->getListComponent());
        }

        protected function getListComponent(): string
        {
            return 'zz-configured-list';
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
