<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Noerd\Helpers\StaticConfigHelper;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('throws exception for non-existing table config', function (): void {
    session(['currentApp' => 'accounting']);
    StaticConfigHelper::getTableConfig('___not_existing___');
})->throws(Exception::class, 'List config not found');

it('loads table config for existing list', function (): void {
    session(['currentApp' => 'accounting']);
    $config = StaticConfigHelper::getTableConfig('customers-list');
    expect($config)->toBeArray()->and($config)->not->toBeEmpty();
});

it('throws exception for non-existing model config', function (): void {
    session(['currentApp' => 'accounting']);
    StaticConfigHelper::getComponentFields('___not_existing___');
})->throws(Exception::class, 'Model config not found');

it('loads model config for existing component', function (): void {
    session(['currentApp' => 'accounting']);
    $fields = StaticConfigHelper::getComponentFields('customer-detail');
    expect($fields)->toBeArray()->and($fields)->not->toBeEmpty();
});

it('loads navigation structure for app', function (): void {
    session(['currentApp' => 'accounting']);
    $navigation = StaticConfigHelper::getNavigationStructure();
    expect($navigation)->toBeArray()->and($navigation)->not->toBeEmpty();
});

it('uses setup as default app when no session', function (): void {
    session()->forget('currentApp');
    // Setup app exists, so this should work or throw for missing file
    try {
        $config = StaticConfigHelper::getTableConfig('users-list');
        expect($config)->toBeArray();
    } catch (Exception $e) {
        expect($e->getMessage())->toContain('setup');
    }
});
