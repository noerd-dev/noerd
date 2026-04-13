<?php

use Illuminate\Support\Facades\Route;
use Noerd\Controllers\DashboardController;

Route::group(['middleware' => ['auth', 'verified', 'setup', 'web']], function (): void {
    Route::livewire('setup', 'setup.noerd-users-list')->name('setup');
    Route::livewire('tenant-apps', 'setup.tenant-apps-list')->name('tenant-apps');
    Route::livewire('users', 'setup.noerd-users-list')->name('users');
    Route::livewire('noerd-user/{modelId}', 'noerd-user-detail')->name('noerd-user.detail');
    Route::livewire('user-roles', 'setup.user-roles-list')->name('user-roles');
    Route::livewire('user-role/{modelId}', 'user-role-detail')->name('user-role.detail');
    Route::livewire('tenant', 'setup.tenant-detail')->name('tenant');
    Route::livewire('create-tenant', 'setup.create-tenant')->name('create-tenant');
    Route::livewire('models', 'models-list')->name('models');
    Route::livewire('setup-collections', 'setup-collections-list')->name('setup-collections');
    Route::livewire('setup-collection/{modelId}', 'setup-collection-detail')->name('setup-collection.detail');
    Route::livewire('setup-collection-definitions', 'setup-collection-definitions-list')
        ->middleware('setup.collections.ui')
        ->name('setup-collection-definitions');
    Route::livewire('setup-collection-definition/{modelId}', 'setup-collection-definition-detail')
        ->middleware('setup.collections.ui')
        ->name('setup-collection-definition.detail');
    Route::livewire('setup-languages', 'setup-languages-list')->name('setup-languages');
    Route::livewire('setup-language/{modelId}', 'setup-language-detail')->name('setup-language.detail');
    Route::livewire('noerd-settings', 'setup.noerd-settings-detail')->name('noerd-settings');
});

Route::group(['middleware' => ['auth', 'web']], function (): void {
    Route::livewire('noerd-home', 'noerd-home')->name('noerd-home');
});

Route::group(['middleware' => ['auth', 'web']], function (): void {
    Route::livewire('no-tenant', 'no-tenant')->name('no-tenant');
});

Route::group(['middleware' => ['auth', 'verified', 'web']], function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::view('profile', 'noerd::profile')->name('profile');
});

Route::prefix('ui-library')->as('ui-library.')->middleware(['auth', 'verified', 'web', 'app-access:UI-LIBRARY'])->group(function (): void {
    Route::livewire('/dashboard', 'ui-library-dashboard')->name('dashboard');
    Route::livewire('/buttons', 'ui-library-buttons')->name('buttons');
    Route::livewire('/form-inputs', 'ui-library-form-inputs')->name('form-inputs');
    Route::livewire('/form-inputs-advanced', 'ui-library-form-inputs-advanced')->name('form-inputs-advanced');
    Route::livewire('/labels-errors', 'ui-library-labels-errors')->name('labels-errors');
    Route::livewire('/layout', 'ui-library-layout')->name('layout');
    Route::livewire('/actions', 'ui-library-actions')->name('actions');
    Route::livewire('/advanced', 'ui-library-advanced')->name('advanced');
    Route::livewire('/filters', 'ui-library-filters')->name('filters');
});

Route::middleware(['web', 'guest'])->group(function (): void {
    Route::livewire('login', 'auth.login')->name('login');
    Route::livewire('forgot-password', 'auth.forgot-password')->name('password.request');
});

// Password reset works for both guests and authenticated users
Route::middleware(['web'])->group(function (): void {
    Route::livewire('reset-password/{token}', 'auth.reset-password')->name('password.reset');
});
