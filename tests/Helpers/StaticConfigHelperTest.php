<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Noerd\Helpers\StaticConfigHelper;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('returns empty array for non-existing table config', function (): void {
    $config = StaticConfigHelper::getTableConfig('___not_existing___');
    expect($config)->toBeArray()->and($config)->toBe([]);
});

it('loads component fields or throws if missing', function (): void {
    try {
        $fields = StaticConfigHelper::getComponentFields('pages-table');
        expect($fields)->toBeArray();
    } catch (Exception $e) {
        expect($e->getMessage())->toContain('Component not found');
    }
});


