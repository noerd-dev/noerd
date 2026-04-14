<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Noerd\Contracts\DynamicNavigationProviderContract;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Services\DynamicNavigationRegistry;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    // Create test collections directory and files
    $collectionsPath = base_path('tests/fixtures/collections');
    File::ensureDirectoryExists($collectionsPath);

    // Create test collection files
    File::put($collectionsPath . '/test-projects.yml', "title: Project\ntitleList: Test Projekte\nkey: PROJECTS");
    File::put($collectionsPath . '/test-customers.yml', "title: Customer\ntitleList: Test Kunden\nkey: CUSTOMERS");
    File::put($collectionsPath . '/invalid.yml', "invalid: yaml: content:");
});

afterEach(function (): void {
    // Clean up test files
    if (File::exists(base_path('tests/fixtures'))) {
        File::deleteDirectory(base_path('tests/fixtures'));
    }
});

describe('StaticConfigHelper Dynamic Navigation', function (): void {
    it('processes dynamic navigation via registry providers', function (): void {
        // Register a test provider
        $registry = app(DynamicNavigationRegistry::class);
        $provider = new class () implements DynamicNavigationProviderContract {
            public function type(): string
            {
                return 'collections';
            }

            public function items(): array
            {
                return [
                    ['title' => 'Test Collection', 'link' => '/test/collections?key=test', 'icon' => 'icons.list-bullet'],
                ];
            }
        };
        $registry->register($provider);

        $navigationStructure = [
            [
                'title' => 'Cms',
                'block_menus' => [
                    [
                        'title' => 'Collections',
                        'dynamic' => 'collections',
                    ],
                    [
                        'title' => 'Static Menu',
                        'navigations' => [
                            ['title' => 'Static Item', 'route' => 'static.route'],
                        ],
                    ],
                ],
            ],
        ];

        $reflection = new ReflectionClass(StaticConfigHelper::class);
        $method = $reflection->getMethod('processDynamicNavigation');
        $method->setAccessible(true);

        $result = $method->invoke(null, $navigationStructure);

        expect($result[0]['block_menus'][0])->toHaveKey('navigations')
            ->and($result[0]['block_menus'][0])->not->toHaveKey('dynamic')
            ->and($result[0]['block_menus'][0]['navigations'])->toBeArray()
            ->and($result[0]['block_menus'][0]['navigations'][0]['title'])->toBe('Test Collection')
            ->and($result[0]['block_menus'][1])->toHaveKey('navigations')
            ->and($result[0]['block_menus'][1])->not->toHaveKey('dynamic');
    });

    it('returns empty navigations for unregistered dynamic type', function (): void {
        $navigationStructure = [
            [
                'title' => 'Test App',
                'block_menus' => [
                    [
                        'title' => 'Unknown Dynamic',
                        'dynamic' => 'nonexistent-type',
                    ],
                ],
            ],
        ];

        $reflection = new ReflectionClass(StaticConfigHelper::class);
        $method = $reflection->getMethod('processDynamicNavigation');
        $method->setAccessible(true);

        $result = $method->invoke(null, $navigationStructure);

        expect($result[0]['block_menus'][0])->not->toHaveKey('dynamic');
    });

    it('leaves non-dynamic navigation blocks unchanged', function (): void {
        $navigationStructure = [
            [
                'title' => 'Test App',
                'block_menus' => [
                    [
                        'title' => 'Static Block',
                        'navigations' => [
                            ['title' => 'Item 1', 'route' => 'route1'],
                            ['title' => 'Item 2', 'route' => 'route2'],
                        ],
                    ],
                ],
            ],
        ];

        $reflection = new ReflectionClass(StaticConfigHelper::class);
        $method = $reflection->getMethod('processDynamicNavigation');
        $method->setAccessible(true);

        $result = $method->invoke(null, $navigationStructure);

        expect($result)->toEqual($navigationStructure);
    });
});
