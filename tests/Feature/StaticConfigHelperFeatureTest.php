<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Noerd\Contracts\DynamicNavigationProviderContract;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Tenant;
use Noerd\Services\DynamicNavigationRegistry;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    // Create test collections directory and files
    $collectionsPath = base_path('tests/fixtures/collections');
    File::ensureDirectoryExists($collectionsPath);

    // Create test collection files
    File::put($collectionsPath . '/test-projects.yml', "title: Project\ntitleList: Test Projekte\nkey: PROJECTS");
    File::put($collectionsPath . '/test-customers.yml', "title: Customer\ntitleList: Test Kunden\nkey: CUSTOMERS");
    File::put($collectionsPath . '/invalid.yml', 'invalid: yaml: content:');
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

describe('StaticConfigHelper runtime memoisation', function (): void {
    beforeEach(function (): void {
        $this->listDir = base_path('app-configs/setup/lists');
        File::ensureDirectoryExists($this->listDir);
        $this->memoFixture = $this->listDir . '/zz-memo-test-list.yml';
        File::put($this->memoFixture, 'title: Memo One');
    });

    afterEach(function (): void {
        File::delete($this->memoFixture);
        File::delete($this->listDir . '/zz-memo-late-list.yml');
    });

    it('does not repeat the tenant queries on subsequent config lookups', function (): void {
        $tenant = Tenant::factory()->create();
        TenantHelper::setSelectedTenantId($tenant->id);
        TenantHelper::setSelectedApp('SETUP');

        StaticConfigHelper::getListConfig('zz-memo-test-list');

        DB::enableQueryLog();
        StaticConfigHelper::getListConfig('zz-memo-test-list');
        $tenantQueries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn(string $sql): bool => str_contains($sql, 'tenant'));
        DB::disableQueryLog();

        expect($tenantQueries->all())->toBe([]);
    });

    it('re-parses a YAML file when its mtime changes', function (): void {
        expect(StaticConfigHelper::getListConfig('zz-memo-test-list')['title'])->toBe('Memo One');

        File::put($this->memoFixture, 'title: Memo Two');
        touch($this->memoFixture, time() + 2);
        clearstatcache(true, $this->memoFixture);

        expect(StaticConfigHelper::getListConfig('zz-memo-test-list')['title'])->toBe('Memo Two');
    });

    it('finds a config created after a failed lookup', function (): void {
        expect(StaticConfigHelper::getListConfig('zz-memo-late-list'))->toBe([]);

        File::put($this->listDir . '/zz-memo-late-list.yml', 'title: Late');

        expect(StaticConfigHelper::getListConfig('zz-memo-late-list')['title'])->toBe('Late');
    });
});
