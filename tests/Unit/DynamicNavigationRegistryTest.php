<?php

use Noerd\Contracts\DynamicNavigationProviderContract;
use Noerd\Services\DynamicNavigationRegistry;

it('registers and resolves a provider', function (): void {
    $registry = new DynamicNavigationRegistry;

    $provider = new class implements DynamicNavigationProviderContract
    {
        public function type(): string
        {
            return 'test-type';
        }

        public function items(): array
        {
            return [['title' => 'Test', 'link' => '/test']];
        }
    };

    $registry->register($provider);

    expect($registry->resolve('test-type'))->toBe($provider);
    expect($registry->resolve('test-type')->items())->toBe([['title' => 'Test', 'link' => '/test']]);
});

it('returns null for unregistered type', function (): void {
    $registry = new DynamicNavigationRegistry;

    expect($registry->resolve('nonexistent'))->toBeNull();
});

it('returns all registered providers', function (): void {
    $registry = new DynamicNavigationRegistry;

    $provider1 = new class implements DynamicNavigationProviderContract
    {
        public function type(): string
        {
            return 'type-a';
        }

        public function items(): array
        {
            return [];
        }
    };

    $provider2 = new class implements DynamicNavigationProviderContract
    {
        public function type(): string
        {
            return 'type-b';
        }

        public function items(): array
        {
            return [];
        }
    };

    $registry->register($provider1);
    $registry->register($provider2);

    expect($registry->all())->toHaveCount(2)
        ->toHaveKeys(['type-a', 'type-b']);
});
