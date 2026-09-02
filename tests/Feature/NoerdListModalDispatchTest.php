<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;

// RefreshDatabase: mountList() resolves the list config (YAML defaultSort),
// and the config search roots read the active tenant apps from the database.
uses(TestCase::class, RefreshDatabase::class);

/*
 | Everything a list dispatches towards the modal stack: opening a row, the
 | deep-link params that open a record straight from the URL, the route/component
 | fallback pair and the locked surface a row click may reach.
 */

beforeEach(function (): void {
    ZzRowOpenListComponent::$renders = 0;
    ZzRowOpenCustomActionComponent::$renders = 0;
});

describe('row open', function (): void {
    it('opens a row by model id via the noerdModal event', function (): void {
        Livewire::test(ZzRowOpenListComponent::class)
            ->call('openListRow', 123)
            ->assertDispatched(
                'noerdModal',
                fn(string $event, array $params): bool => $params['modalComponent'] === 'zz-row-open-detail'
                    && ($params['arguments']['modelId'] ?? null) === 123,
            );
    });

    it('skips the re-render when opening a row through the trait listAction', function (): void {
        Livewire::test(ZzRowOpenListComponent::class)
            ->call('openListRow', 123);

        // Only the initial mount rendered — the open dispatches an event, nothing in the list changed.
        expect(ZzRowOpenListComponent::$renders)->toBe(1);
    });

    it('still re-renders when the component overrides listAction', function (): void {
        $component = Livewire::test(ZzRowOpenCustomActionComponent::class)
            ->call('openListRow', 5);

        expect($component->get('opened'))->toBe(5)
            ->and(ZzRowOpenCustomActionComponent::$renders)->toBe(2);
    });

    it('ticks the row instead of opening it in picker mode', function (): void {
        Livewire::test(ZzRowOpenListComponent::class, ['returnsSelection' => true])
            ->call('openListRow', 7)
            ->assertSet('selectedRecordIds', [7])
            ->assertNotDispatched('noerdModal');

        // Picker clicks change server-rendered checkbox state, so they must render.
        expect(ZzRowOpenListComponent::$renders)->toBe(2);
    });

    it('resolves the positional keyboard index to the model id', function (): void {
        Livewire::test(ZzRowOpenListComponent::class)
            ->call('findListAction', 1)
            ->assertDispatched(
                'noerdModal',
                fn(string $event, array $params): bool => ($params['arguments']['modelId'] ?? null) === 456,
            );
    });

    it('ignores a click on a row without an id', function (): void {
        Livewire::test(ZzRowOpenListComponent::class)
            ->call('openListRow', '')
            ->assertNotDispatched('noerdModal');
    });

    it('ignores a keyboard index that has no row', function (): void {
        Livewire::test(ZzRowOpenListComponent::class)
            ->call('findListAction', 99)
            ->assertNotDispatched('noerdModal');
    });
});

describe('deep link', function (): void {
    it('opens the detail when the derived deep link param is in the url', function (): void {
        Livewire::withQueryParams(['zzDeepLinkProductId' => 89224])
            ->test(ZzDeepLinkProductsListComponent::class)
            ->assertDispatched(
                'noerdModal',
                fn(string $event, array $params): bool => $params['modalComponent'] === 'zz-deep-link-product-page'
                    && ($params['arguments']['modelId'] ?? null) === 89224,
            );
    });

    it('does not open the detail without the deep link param', function (): void {
        Livewire::test(ZzDeepLinkProductsListComponent::class)
            ->assertNotDispatched('noerdModal');
    });

    it('ignores a non-numeric deep link param', function (): void {
        Livewire::withQueryParams(['zzDeepLinkProductId' => 'abc'])
            ->test(ZzDeepLinkProductsListComponent::class)
            ->assertNotDispatched('noerdModal');
    });

    it('opens the create detail via the create param', function (): void {
        Livewire::withQueryParams(['create' => 1])
            ->test(ZzDeepLinkProductsListComponent::class)
            ->assertDispatched(
                'noerdModal',
                fn(string $event, array $params): bool => $params['modalComponent'] === 'zz-deep-link-product-page'
                    && ($params['arguments']['modelId'] ?? null) === null,
            );
    });

    it('strips the livewire namespace prefix when deriving the deep link param', function (): void {
        Livewire::withQueryParams(['zzDeepLinkCustomerId' => 47677])
            ->test(ZzDeepLinkNamespacedListComponent::class)
            ->assertDispatched(
                'noerdModal',
                fn(string $event, array $params): bool => ($params['arguments']['modelId'] ?? null) === 47677,
            );
    });
});

describe('route fallback', function (): void {
    it('dispatches the route plus the component as fallback when a list declares both', function (): void {
        Livewire::test(ZzRouteFallbackListComponent::class)
            ->call('listAction', 123)
            ->assertDispatched(
                'noerdModal',
                fn(string $event, array $params): bool => ($params['route'] ?? null) === 'zz.fallback.detail'
                    && ($params['modalComponent'] ?? null) === 'zz-fallback-detail'
                    && ($params['arguments']['modelId'] ?? null) === 123,
            );
    });

    it('dispatches only the component for a list without a detailRoute', function (): void {
        Livewire::test(ZzComponentOnlyListComponent::class)
            ->call('listAction', 5)
            ->assertDispatched(
                'noerdModal',
                fn(string $event, array $params): bool => ($params['modalComponent'] ?? null) === 'zz-component-only-detail'
                    && ! array_key_exists('route', $params),
            );
    });

    it('passes relations through as a url-neutral argument', function (): void {
        Livewire::test(ZzRouteFallbackListComponent::class)
            ->call('listAction', null, ['accountId' => 9])
            ->assertDispatched(
                'noerdModal',
                fn(string $event, array $params): bool => ($params['arguments']['relations'] ?? null) === ['accountId' => 9],
            );
    });

    it('requests a url rewrite for a routed list row', function (): void {
        Livewire::test(ZzRouteFallbackListComponent::class)
            ->call('listAction', 1)
            ->assertDispatched(
                'noerdModal',
                fn(string $event, array $params): bool => ($params['rewriteUrl'] ?? null) === true,
            );
    });
});

describe('locked surface', function (): void {
    it('openListRow ignores a listActionMethod that names a non-public method', function (): void {
        $component = new class {
            use NoerdList;

            public bool $publicHit = false;

            public bool $protectedHit = false;

            public function listAction(mixed $modelId = null, array $relations = []): void
            {
                $this->publicHit = true;
            }

            public function skipRender(): void {}

            protected function internalReset(mixed $modelId = null): void
            {
                $this->protectedHit = true;
            }
        };

        $component->listActionMethod = 'internalReset';
        $component->openListRow(1);
        expect($component->protectedHit)->toBeFalse();

        $component->listActionMethod = 'listAction';
        $component->openListRow(1);
        expect($component->publicHit)->toBeTrue();
    });
});

/**
 * Shared skeleton of every dispatch fixture: a countable render and an inline
 * list config, so the subclasses only carry what the case under test needs.
 */
abstract class ZzListDispatchComponent extends Component
{
    use NoerdList;

    public static int $renders = 0;

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return ['listConfig' => ['rows' => $this->listRows()]];
    }

    public function render(): string
    {
        static::$renders++;

        return '<div></div>';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function listRows(): array
    {
        return [];
    }
}

class ZzRowOpenListComponent extends ZzListDispatchComponent
{
    public static int $renders = 0;

    public $detailComponent = 'zz-row-open-detail';

    protected function listRows(): array
    {
        return [['id' => 123], ['id' => 456]];
    }

    protected function componentName(): string
    {
        return 'zz-row-open-test-list';
    }
}

class ZzRowOpenCustomActionComponent extends ZzListDispatchComponent
{
    public static int $renders = 0;

    public ?int $opened = null;

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        $this->opened = (int) $modelId;
    }

    protected function componentName(): string
    {
        return 'zz-row-open-custom-test-list';
    }
}

class ZzDeepLinkProductsListComponent extends ZzListDispatchComponent
{
    public $detailComponent = 'zz-deep-link-product-page';

    protected function componentName(): string
    {
        return 'zz-deep-link-products-list';
    }
}

class ZzDeepLinkNamespacedListComponent extends ZzListDispatchComponent
{
    public $detailComponent = 'zz-deep-link-customer-detail';

    protected function componentName(): string
    {
        return 'zz-module::zz-deep-link-customers-list';
    }
}

class ZzRouteFallbackListComponent extends ZzListDispatchComponent
{
    public $detailComponent = 'zz-fallback-detail';

    public ?string $detailRoute = 'zz.fallback.detail';

    protected function componentName(): string
    {
        return 'zz-route-fallback-test-list';
    }
}

class ZzComponentOnlyListComponent extends ZzListDispatchComponent
{
    public $detailComponent = 'zz-component-only-detail';

    protected function componentName(): string
    {
        return 'zz-component-only-test-list';
    }
}
