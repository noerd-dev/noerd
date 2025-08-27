<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Noerd\Noerd\Helpers\StaticConfigHelper;

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
    it('collections method returns array with navigation items', function (): void {
        // Test the actual collections method
        $result = StaticConfigHelper::collections();

        expect($result)->toBeArray();

        // Should contain at least the existing collections
        foreach ($result as $item) {
            expect($item)->toHaveKeys(['title', 'link', 'icon'])
                ->and($item['link'])->toStartWith('/cms/collections?key=')
                ->and($item['icon'])->toBe('icons.list-bullet');
        }
    });

    it('processes dynamic navigation structure correctly with real collections', function (): void {
        // Get actual collections to test with
        $collectionsResult = StaticConfigHelper::collections();

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

        // Use reflection to test the private method
        $reflection = new ReflectionClass(StaticConfigHelper::class);
        $method = $reflection->getMethod('processDynamicNavigation');
        $method->setAccessible(true);

        $result = $method->invoke(null, $navigationStructure);

        expect($result[0]['block_menus'][0])->toHaveKey('navigations')
            ->and($result[0]['block_menus'][0])->not->toHaveKey('dynamic')
            ->and($result[0]['block_menus'][0]['navigations'])->toBeArray()
            ->and($result[0]['block_menus'][1])->toHaveKey('navigations') // Static menu unchanged
            ->and($result[0]['block_menus'][1])->not->toHaveKey('dynamic'); // No dynamic key on static menu
    });

    it('handles method_exists check for dynamic methods', function (): void {
        expect(method_exists(StaticConfigHelper::class, 'collections'))->toBeTrue();
        expect(method_exists(StaticConfigHelper::class, 'nonExistentMethod'))->toBeFalse();
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

        expect($result)->toEqual($navigationStructure); // Should be unchanged
    });
});
