<?php

use Illuminate\Support\Facades\Route;
use Noerd\Controllers\DashboardController;

Route::group(['middleware' => ['auth', 'verified', 'setup', 'web']], function (): void {
    Route::livewire('setup', 'noerd::setup.noerd-users-list')->name('setup');
    Route::livewire('tenant-apps', 'noerd::setup.tenant-apps-list')->name('tenant-apps');
    Route::livewire('users', 'noerd::setup.noerd-users-list')->name('users');
    Route::livewire('noerd-user/{modelId}', 'noerd::noerd-user-detail')->name('noerd-user.detail');
    Route::livewire('user-roles', 'noerd::setup.user-roles-list')->name('user-roles');
    Route::livewire('user-role/{modelId}', 'noerd::user-role-detail')->name('user-role.detail');
    Route::livewire('tenant', 'noerd::setup.tenant-detail')->name('tenant');
    Route::livewire('create-tenant', 'noerd::setup.create-tenant')->name('create-tenant');
    Route::livewire('models', 'noerd::models-list')->name('models');
    Route::livewire('setup-collections', 'noerd::setup-collections-list')->name('setup-collections');
    Route::livewire('setup-collection/{modelId}', 'noerd::setup-collection-detail')->name('setup-collection.detail');
    Route::livewire('setup-collection-definitions', 'noerd::setup-collection-definitions-list')
        ->middleware('setup.collections.ui')
        ->name('setup-collection-definitions');
    Route::livewire('setup-collection-definition/{modelId}', 'noerd::setup-collection-definition-detail')
        ->middleware('setup.collections.ui')
        ->name('setup-collection-definition.detail');
    Route::livewire('setup-languages', 'noerd::setup-languages-list')->name('setup-languages');
    Route::livewire('setup-language/{modelId}', 'noerd::setup-language-detail')->name('setup-language.detail');
    Route::livewire('system-settings', 'noerd::setup.system-settings-detail')->name('system-settings');
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

Route::prefix('ui-library')->as('ui-library.')->middleware(['auth', 'verified', 'web', 'app-access:UI-LIBRARY'])->group(function (): void {
    Route::livewire('/dashboard', 'noerd::ui-library-dashboard')->name('dashboard');
    Route::livewire('/buttons', 'noerd::ui-library-buttons')->name('buttons');
    Route::livewire('/form-inputs', 'noerd::ui-library-form-inputs')->name('form-inputs');
    Route::livewire('/form-inputs-advanced', 'noerd::ui-library-form-inputs-advanced')->name('form-inputs-advanced');
    Route::livewire('/labels-errors', 'noerd::ui-library-labels-errors')->name('labels-errors');
    Route::livewire('/layout', 'noerd::ui-library-layout')->name('layout');
    Route::livewire('/actions', 'noerd::ui-library-actions')->name('actions');
    Route::livewire('/advanced', 'noerd::ui-library-advanced')->name('advanced');
    Route::livewire('/filters', 'noerd::ui-library-filters')->name('filters');
});

Route::middleware(['web', 'guest'])->group(function (): void {
    Route::livewire('login', 'noerd::auth.login')->name('login');
    Route::livewire('forgot-password', 'noerd::auth.forgot-password')->name('password.request');
});

// Password reset works for both guests and authenticated users
Route::middleware(['web'])->group(function (): void {
    Route::livewire('reset-password/{token}', 'noerd::auth.reset-password')->name('password.reset');
});
