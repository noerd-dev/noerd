<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'setup', 'middleware' => ['noerd', 'setup']], function (): void {
    Route::livewire('/', 'noerd::noerd-users-list')->name('noerd.setup');
    Route::livewire('tenant-apps', 'noerd::tenant-apps-list')->name('noerd.tenant-apps');
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

// Every other core screen lives under the configurable URL prefix
$prefix = config('noerd.routes.prefix', 'noerd');

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

Route::group(['prefix' => $prefix, 'middleware' => ['web']], function (): void {
    Route::livewire('reset-password/{token}', 'noerd::auth.reset-password')->name('noerd.password.reset');
});

Route::middleware(['web'])->group(function () use ($prefix): void {
    Route::redirect('login', '/' . mb_trim($prefix, '/') . '/login');
});
