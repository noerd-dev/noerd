<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Setup Routes
|--------------------------------------------------------------------------
|
| These routes power the setup screens, where tenant administrators may
| manage the users, tenants, apps and system settings. The "setup"
| middleware keeps everyone else out and selects the setup app.
|
*/

Route::group(['prefix' => 'setup', 'middleware' => ['noerd', 'setup']], function (): void {
    // The setup landing page IS the users list; noerd.setup stays a second name
    // for the same screen because host modules and tests link to it.
    Route::livewire('/', 'noerd::noerd-users-list')->name('noerd.setup');
    Route::livewire('tenant-apps', 'noerd::tenant-apps-page')->name('noerd.tenant-apps');
    Route::livewire('users', 'noerd::noerd-users-list')->name('noerd.users');
    Route::livewire('noerd-user/{modelId}', 'noerd::noerd-user-page')->name('noerd.user.detail');
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

/*
|--------------------------------------------------------------------------
| Core Routes
|--------------------------------------------------------------------------
|
| The remaining core screens live beneath a configurable URL prefix,
| which you may change via the "noerd.routes.prefix" option. Only
| the apps overview and the "/login" shortcut stay at the root.
|
*/

$prefix = config('noerd.routes.prefix', 'noerd');

Route::group(['middleware' => ['noerd']], function (): void {
    Route::livewire('noerd-apps', 'noerd::noerd-apps')->name('noerd.apps');
});

Route::group(['prefix' => $prefix, 'middleware' => ['noerd']], function (): void {
    Route::livewire('component-page/{componentName}', 'noerd::generic-component-page')->name('noerd.component-page');
    Route::livewire('no-tenant', 'noerd::no-tenant')->name('noerd.no-tenant');
    Route::view('user', 'noerd::profile')->name('noerd.profile');
    Route::view('user/language', 'noerd::profile-language')->name('noerd.profile.language');
    Route::view('user/tenants', 'noerd::profile-tenants')->name('noerd.profile.tenants');
    Route::view('user/delete-account', 'noerd::profile-delete')->name('noerd.profile.delete');
});

Route::group(['prefix' => $prefix, 'middleware' => ['noerd-guest']], function (): void {
    Route::livewire('login', 'noerd::auth.login')->name('noerd.login');
    Route::livewire('forgot-password', 'noerd::auth.forgot-password')->name('noerd.password.request');
});

Route::group(['prefix' => $prefix, 'middleware' => ['web']], function (): void {
    Route::livewire('reset-password/{token}', 'noerd::auth.reset-password')->name('noerd.password.reset');
});

Route::middleware(['web'])->group(function () use ($prefix): void {
    Route::redirect('login', '/' . mb_trim($prefix, '/') . '/login');
});
