<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Noerd\Helpers\StaticConfigHelper;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('throws exception for non-existing table config', function (): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('accounting')->create();
    $this->actingAs($user);

    StaticConfigHelper::getTableConfig('___not_existing___');
})->throws(Exception::class, 'List config not found');

it('loads table config for existing list', function (): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('accounting')->create();
    $this->actingAs($user);

    $config = StaticConfigHelper::getTableConfig('customers-list');
    expect($config)->toBeArray()->and($config)->not->toBeEmpty();
});

it('throws exception for non-existing model config', function (): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('accounting')->create();
    $this->actingAs($user);

    StaticConfigHelper::getComponentFields('___not_existing___');
})->throws(Exception::class, 'Model config not found');

it('loads model config for existing component', function (): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('accounting')->create();
    $this->actingAs($user);

    $fields = StaticConfigHelper::getComponentFields('customer-detail');
    expect($fields)->toBeArray()->and($fields)->not->toBeEmpty();
});

it('loads navigation structure for app', function (): void {
    $user = User::factory()->withExampleTenant()->withSelectedApp('accounting')->create();
    $this->actingAs($user);

    $navigation = StaticConfigHelper::getNavigationStructure();
    expect($navigation)->toBeArray()->and($navigation)->not->toBeEmpty();
});

it('returns null for navigation when no app selected', function (): void {
    $user = User::factory()->withExampleTenant()->create();
    $this->actingAs($user);

    $navigation = StaticConfigHelper::getNavigationStructure();
    expect($navigation)->toBeNull();
});
