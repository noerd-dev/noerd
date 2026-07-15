<?php

use Illuminate\Support\Facades\Route;
use Noerd\Controllers\DashboardController;

// Every setup page lives under /setup. Route NAMES are the public contract
// (navigation.yml `route:` keys, tenant_apps.route, route() calls) and stay
// unprefixed — only the URLs carry the prefix, so the redundant `setup-` in
// paths like `setup-collections` is dropped in favour of the group prefix.
Route::group(['prefix' => 'setup', 'middleware' => ['auth', 'verified', 'setup', 'web']], function (): void {
    Route::livewire('/', 'noerd::noerd-users-list')->name('setup');
    Route::livewire('tenant-apps', 'noerd::tenant-apps-list')->name('tenant-apps');
    Route::livewire('users', 'noerd::noerd-users-list')->name('users');
    Route::livewire('noerd-user/{modelId}', 'noerd::noerd-user-detail')->name('noerd-user.detail');
    Route::livewire('user-roles', 'noerd::user-roles-list')->name('user-roles');
    Route::livewire('user-role/{modelId}', 'noerd::user-role-detail')->name('user-role.detail');
    Route::livewire('tenant', 'noerd::tenant-detail')->name('tenant');
    Route::livewire('create-tenant', 'noerd::create-tenant')->name('create-tenant');
    Route::livewire('models', 'noerd::models-list')->name('models');
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
    Route::livewire('system-settings', 'noerd::system-settings-detail')->name('system-settings');
});

Route::group(['middleware' => ['auth', 'verified', 'web']], function (): void {
    Route::livewire('/component-page/{componentName}', 'noerd::generic-component-page')->name('component-page');
});

Route::group(['middleware' => ['auth', 'web']], function (): void {
    Route::livewire('noerd-home', 'noerd::noerd-home')->name('noerd-home');
});

Route::group(['middleware' => ['auth', 'web']], function (): void {
    Route::livewire('no-tenant', 'noerd::no-tenant')->name('no-tenant');
});

Route::group(['middleware' => ['auth', 'verified', 'web']], function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::view('profile', 'noerd::profile')->name('profile');
});

Route::middleware(['web', 'guest'])->group(function (): void {
    Route::livewire('login', 'noerd::auth.login')->name('login');
    Route::livewire('forgot-password', 'noerd::auth.forgot-password')->name('password.request');
});

// Password reset works for both guests and authenticated users
Route::middleware(['web'])->group(function (): void {
    Route::livewire('reset-password/{token}', 'noerd::auth.reset-password')->name('password.reset');
});
