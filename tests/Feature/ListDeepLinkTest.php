<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;

uses(TestCase::class, RefreshDatabase::class);

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

class ZzDeepLinkProductsListComponent extends Component
{
    use NoerdList;

    public $detailComponent = 'zz-deep-link-product-page';

    public function with(): array
    {
        return ['listConfig' => ['rows' => []]];
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-deep-link-products-list';
    }
}

class ZzDeepLinkNamespacedListComponent extends Component
{
    use NoerdList;

    public $detailComponent = 'zz-deep-link-customer-detail';

    public function with(): array
    {
        return ['listConfig' => ['rows' => []]];
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-module::zz-deep-link-customers-list';
    }
}
