<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Noerd\Models\NoerdUser;
use Noerd\Services\ProfileNavigationRegistry;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('renders each profile page with its forms inside the shared section chrome', function (string $route, array $forms): void {
    $user = NoerdUser::factory()->withExampleTenant()->create();

    $response = $this->actingAs($user)->get(route($route));

    $response->assertOk();

    foreach ($forms as $form) {
        $response->assertSeeLivewire($form);
    }

    // <x-noerd::profile-section> keeps the box chrome and the reading width in
    // one place — every form must land inside both.
    expect(mb_substr_count($response->getContent(), 'max-w-xl'))->toBeGreaterThanOrEqual(count($forms));
})->with([
    'profile and password' => ['noerd.profile', ['noerd::profile.update-profile-information-form', 'noerd::profile.update-password-form']],
    'language and locale' => ['noerd.profile.language', ['noerd::profile.update-language-form', 'noerd::profile.update-locale-form']],
    'tenant access' => ['noerd.profile.tenants', ['noerd::profile.tenant-access-display-form']],
    'delete account' => ['noerd.profile.delete', ['noerd::profile.delete-user-form']],
]);

it('links every profile page from the navigation and marks the open one', function (): void {
    $user = NoerdUser::factory()->withExampleTenant()->create();

    $response = $this->actingAs($user)->get(route('noerd.profile.language'));

    foreach (['noerd.profile', 'noerd.profile.language', 'noerd.profile.tenants', 'noerd.profile.delete'] as $route) {
        $response->assertSee('href="' . route($route) . '"', false);
    }

    expect(mb_substr_count($response->getContent(), 'aria-current="page"'))->toBe(1);
});

it('adds a page a module registers, in sort order, and skips one without a route', function (): void {
    Route::middleware('noerd')->get('/zz-profile-page', fn() => 'zz')->name('zz.profile-page');
    app('router')->getRoutes()->refreshNameLookups();

    $registry = app(ProfileNavigationRegistry::class);
    $registry->register('Zz Connections', 'zz.profile-page', 'link', 50);
    $registry->register('Zz Missing', 'zz.missing-route');

    $routes = array_column($registry->all(), 'route');

    expect($routes)->toContain('zz.profile-page')
        ->not->toContain('zz.missing-route')
        ->and(array_search('zz.profile-page', $routes, true))
        ->toBeGreaterThan(array_search('noerd.profile.tenants', $routes, true))
        ->toBeLessThan(array_search('noerd.profile.delete', $routes, true));

    $user = NoerdUser::factory()->withExampleTenant()->create();

    $this->actingAs($user)->get(route('noerd.profile'))->assertSee('Zz Connections');
});
