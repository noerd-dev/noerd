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

class ZzRouteFallbackListComponent extends Component
{
    use NoerdList;

    public const COMPONENT = 'zz-route-fallback-test-list';

    public $detailComponent = 'zz-fallback-detail';

    public ?string $detailRoute = 'zz.fallback.detail';

    public function render(): string
    {
        return '<div></div>';
    }
}

class ZzComponentOnlyListComponent extends Component
{
    use NoerdList;

    public const COMPONENT = 'zz-component-only-test-list';

    public $detailComponent = 'zz-component-only-detail';

    public function render(): string
    {
        return '<div></div>';
    }
}
