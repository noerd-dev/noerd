<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates every noerd system table', function (string $table): void {
    expect(Schema::hasTable($table))->toBeTrue();
})->with([
    'tenant_apps',
    'tenants',
    'tenant_app',
    'noerd_users',
    'users_tenants',
    'noerd_user_settings',
    'noerd_settings',
    'setup_languages',
    'setup_collections',
    'setup_collection_entries',
    'setup_collection_definitions',
]);

it('includes the consolidated columns in the create migrations', function (string $table, string $column): void {
    expect(Schema::hasColumn($table, $column))->toBeTrue();
})->with([
    ['tenant_app', 'sort_order'],
    ['tenants', 'logo'],
    ['noerd_settings', 'detail_theme'],
    ['noerd_settings', 'detail_theme_enforced'],
    ['setup_languages', 'tenant_id'],
]);

it('no longer creates the tenant_invoices table', function (): void {
    expect(Schema::hasTable('tenant_invoices'))->toBeFalse();
});

it('no longer creates the noerd_profiles table (profiles are a fixed enum)', function (): void {
    expect(Schema::hasTable('noerd_profiles'))->toBeFalse()
        ->and(Schema::hasColumn('users_tenants', 'profile_key'))->toBeTrue();
});
