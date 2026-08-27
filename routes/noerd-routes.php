<?php

use Illuminate\Support\Facades\Route;

// Every setup page lives under /setup. Route NAMES are the public contract
// (navigation.yml `route:` keys, tenant_apps.route, route() calls) and stay
// unprefixed — only the URLs carry the prefix, so the redundant `setup-` in
// paths like `setup-collections` is dropped in favour of the group prefix.
Route::group(['prefix' => 'setup', 'middleware' => ['noerd', 'setup']], function (): void {
    Route::livewire('/', 'noerd::noerd-users-list')->name('setup');
    Route::livewire('tenant-apps', 'noerd::tenant-apps-list')->name('tenant-apps');
    Route::livewire('users', 'noerd::noerd-users-list')->name('users');
    Route::livewire('noerd-user/{modelId}', 'noerd::noerd-user-detail')->name('noerd-user.detail');
    Route::livewire('tenants', 'noerd::tenants-list')->name('tenants');
    Route::livewire('tenant/{modelId}', 'noerd::tenant-detail')->name('tenant.detail');
    Route::livewire('create-tenant', 'noerd::create-tenant')->name('create-tenant');
    Route::livewire('collections', 'noerd::setup-collections-list')->name('setup-collections');
    Route::livewire('collection/{modelId}', 'noerd::setup-collection-detail')->name('setup-collection.detail');
    Route::livewire('collection-definitions', 'noerd::setup-collection-definitions-list')
        ->middleware('setup.collections.ui')
        ->name('setup-collection-definitions');
    Route::livewire('collection-definition/{modelId}', 'noerd::setup-collection-definition-detail')
        ->middleware('setup.collections.ui')
        ->name('setup-collection-definition.detail');
    Route::livewire('languages', 'noerd::setup-languages-list')->name('setup-languages');
    Route::livewire('language/{modelId}', 'noerd::setup-language-detail')->name('setup-language.detail');
    Route::livewire('system-settings', 'noerd::system-settings-page')->name('system-settings');
});

// Every other core screen lives under the configurable URL prefix (default
// /noerd) so a host application — e.g. one generated from a Laravel starter
// kit — keeps its own /login, /dashboard etc. Route names stay stable; the
// auth routes are namespaced (noerd.login, noerd.password.*) so the package
// never claims the starter-kit route names (they would break route:cache).
$prefix = config('noerd.routes.prefix', 'noerd');

// The apps dashboard deliberately stays at its unprefixed URL — /noerd-apps
// is already namespaced and is the address users know from the installer.
Route::group(['middleware' => ['noerd']], function (): void {
    Route::livewire('noerd-apps', 'noerd::noerd-apps')->name('noerd-apps');
});

Route::group(['prefix' => $prefix, 'middleware' => ['noerd']], function (): void {
    Route::livewire('component-page/{componentName}', 'noerd::generic-component-page')->name('component-page');
    Route::redirect('home', '/noerd-apps');
    Route::livewire('no-tenant', 'noerd::no-tenant')->name('no-tenant');
    Route::view('user', 'noerd::profile')->name('noerd-user');
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
