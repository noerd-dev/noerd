<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns empty array and logs warning for non-existing table config', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn(string $message) => str_contains($message, 'lists/___not_existing___.yml'));

    $user = NoerdUser::factory()->withExampleTenant()->withSelectedApp('noerdApp')->create();
    $this->actingAs($user);

    $config = StaticConfigHelper::getListConfig('___not_existing___');
    expect($config)->toBeArray()->toBeEmpty();
});

it('loads table config for existing list', function (): void {
    $user = NoerdUser::factory()->withExampleTenant()->withSelectedApp('noerdApp')->create();
    $this->actingAs($user);

    $config = StaticConfigHelper::getListConfig('user-roles-list');
    expect($config)->toBeArray()->and($config)->not->toBeEmpty();
});

it('returns empty array and logs warning for non-existing model config', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn(string $message) => str_contains($message, 'details/___not_existing___.yml'));

    $user = NoerdUser::factory()->withExampleTenant()->withSelectedApp('noerdApp')->create();
    $this->actingAs($user);

    $fields = StaticConfigHelper::getComponentFields('___not_existing___');
    expect($fields)->toBeArray()->toBeEmpty();
});

it('loads model config for existing component', function (): void {
    $user = NoerdUser::factory()->withExampleTenant()->withSelectedApp('noerdApp')->create();
    $this->actingAs($user);

    $fields = StaticConfigHelper::getComponentFields('noerd-user-detail');
    expect($fields)->toBeArray()->and($fields)->not->toBeEmpty();
});

it('loads navigation structure for app', function (): void {
    $user = NoerdUser::factory()->withExampleTenant()->withSelectedApp('setup')->create();
    $this->actingAs($user);

    $navigation = StaticConfigHelper::getNavigationStructure();
    expect($navigation)->toBeArray()->and($navigation)->not->toBeEmpty();
});

it('returns null for navigation when no app selected', function (): void {
    $user = NoerdUser::factory()->withExampleTenant()->create();
    $this->actingAs($user);

    $navigation = StaticConfigHelper::getNavigationStructure();
    expect($navigation)->toBeNull();
});

/**
 * Which entries the installed setup navigation carries — and which of them are
 * gated by `feature:` or `superAdmin:` — is configuration. So these compare the
 * two resolutions against each other instead of naming any entry: gating may only
 * ever REMOVE entries, never add or reorder them.
 *
 * @return array<int, string>
 */
function setupNavigationTitles(): array
{
    StaticConfigHelper::flushRuntimeCaches();

    return collect(StaticConfigHelper::getNavigationStructure()[0]['block_menus'] ?? [])
        ->flatMap(fn(array $block): array => collect($block['navigations'] ?? [])->pluck('title')->all())
        ->all();
}

it('hides feature-gated navigation items when the config value is false', function (): void {
    $user = NoerdUser::factory()->withExampleTenant()->withSelectedApp('setup')->create();
    $this->actingAs($user);

    config()->set('noerd.features.roles', true);
    $enabled = setupNavigationTitles();

    config()->set('noerd.features.roles', false);
    $disabled = setupNavigationTitles();

    expect($enabled)->not->toBeEmpty()
        ->and(array_diff($disabled, $enabled))->toBeEmpty()
        ->and(array_diff($enabled, $disabled))->not->toBeEmpty();
});

it('hides superAdmin navigation items from non-super admins', function (): void {
    $tenant = NoerdUser::factory()->withExampleTenant()->withSelectedApp('setup');

    $this->actingAs($tenant->create(['super_admin' => true]));
    $superAdmin = setupNavigationTitles();

    $this->actingAs($tenant->create(['super_admin' => false]));
    $regular = setupNavigationTitles();

    expect($superAdmin)->not->toBeEmpty()
        ->and(array_diff($regular, $superAdmin))->toBeEmpty()
        ->and(array_diff($superAdmin, $regular))->not->toBeEmpty();
});
