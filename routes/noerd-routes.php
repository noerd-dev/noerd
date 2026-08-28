<?php

use Illuminate\Support\Facades\Route;

// Every setup page lives under /setup. Route NAMES are the public contract
// (navigation.yml `route:` keys, tenant_apps.route, route() calls) and are all
// namespaced under `noerd.` — a host application or another package may own
// generic names like `users` or `tenants`, and a collision would silently
// corrupt route:cache (last registration wins). URLs drop the redundant
// `setup-` in favour of the group prefix.
Route::group(['prefix' => 'setup', 'middleware' => ['noerd', 'setup']], function (): void {
    Route::livewire('/', 'noerd::noerd-users-list')->name('noerd.setup');
    Route::livewire('tenant-apps', 'noerd::tenant-apps-list')->name('noerd.tenant-apps');
    Route::livewire('users', 'noerd::noerd-users-list')->name('noerd.users');
    Route::livewire('noerd-user/{modelId}', 'noerd::noerd-user-detail')->name('noerd.user.detail');
    Route::livewire('tenants', 'noerd::tenants-list')->name('noerd.tenants');
    Route::livewire('tenant/{modelId}', 'noerd::tenant-detail')->name('noerd.tenant.detail');
    Route::livewire('create-tenant', 'noerd::create-tenant')->name('noerd.create-tenant');
    Route::livewire('collections', 'noerd::setup-collections-list')->name('noerd.setup-collections');
    Route::livewire('collection/{modelId}', 'noerd::setup-collection-detail')->name('noerd.setup-collection.detail');
    Route::livewire('collection-definitions', 'noerd::setup-collection-definitions-list')
        ->middleware('setup.collections.ui')
        ->name('noerd.setup-collection-definitions');
    Route::livewire('collection-definition/{modelId}', 'noerd::setup-collection-definition-detail')
        ->middleware('setup.collections.ui')
        ->name('noerd.setup-collection-definition.detail');
    Route::livewire('languages', 'noerd::setup-languages-list')->name('noerd.setup-languages');
    Route::livewire('language/{modelId}', 'noerd::setup-language-detail')->name('noerd.setup-language.detail');
    Route::livewire('system-settings', 'noerd::system-settings-page')->name('noerd.system-settings');
});

// Every other core screen lives under the configurable URL prefix (default
// /noerd) so a host application — e.g. one generated from a Laravel starter
// kit — keeps its own /login, /dashboard etc. Route names are namespaced
// (noerd.*) so the package never claims host route names (they would break
// route:cache).
$prefix = config('noerd.routes.prefix', 'noerd');

// The apps dashboard deliberately stays at its unprefixed URL — /noerd-apps
// is already namespaced and is the address users know from the installer.
Route::group(['middleware' => ['noerd']], function (): void {
    Route::livewire('noerd-apps', 'noerd::noerd-apps')->name('noerd.apps');
});

Route::group(['prefix' => $prefix, 'middleware' => ['noerd']], function (): void {
    Route::livewire('component-page/{componentName}', 'noerd::generic-component-page')->name('noerd.component-page');
    Route::redirect('home', '/noerd-apps');
    Route::livewire('no-tenant', 'noerd::no-tenant')->name('noerd.no-tenant');
    Route::view('user', 'noerd::profile')->name('noerd.profile');
});

Route::group(['prefix' => $prefix, 'middleware' => ['noerd-guest']], function (): void {
    Route::livewire('login', 'noerd::auth.login')->name('noerd.login');
    Route::livewire('forgot-password', 'noerd::auth.forgot-password')->name('noerd.password.request');
});

// Password reset works for both guests and authenticated users
Route::group(['prefix' => $prefix, 'middleware' => ['web']], function (): void {
    Route::livewire('reset-password/{token}', 'noerd::auth.reset-password')->name('noerd.password.reset');
});

// Convenience alias: /login keeps working on a plain noerd installation. The
// redirect is deliberately UNNAMED and registered before the host's routes —
// a starter kit that claims the /login URI (same method + URI, registered
// later) simply overrides it, and no name collision can break route:cache.
Route::middleware(['web'])->group(function () use ($prefix): void {
    Route::redirect('login', '/' . mb_trim($prefix, '/') . '/login');
});
