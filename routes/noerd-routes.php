<?php

use Illuminate\Support\Facades\Route;
use Noerd\Controllers\DashboardController;

Route::group(['middleware' => ['auth', 'verified', 'setup', 'web']], function (): void {
    Route::livewire('setup', 'setup.users-list')->name('setup');
    Route::livewire('tenant-apps', 'setup.tenant-apps-list')->name('tenant-apps');
    Route::livewire('users', 'setup.users-list')->name('users');
    Route::livewire('user/{modelId}', 'user-detail')->name('user.detail');
    Route::livewire('user-roles', 'setup.user-roles-list')->name('user-roles');
    Route::livewire('user-role/{modelId}', 'user-role-detail')->name('user-role.detail');
    Route::livewire('tenant', 'setup.tenant-detail')->name('tenant');
    Route::livewire('models', 'models-list')->name('models');
    Route::livewire('setup-collections', 'setup-collections-list')->name('setup-collections');
    Route::livewire('setup-collection/{modelId}', 'setup-collection-detail')->name('setup-collection.detail');
    Route::livewire('setup-languages', 'setup-languages-list')->name('setup-languages');
    Route::livewire('setup-language/{modelId}', 'setup-language-detail')->name('setup-language.detail');
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

Route::middleware(['web', 'guest'])->group(function (): void {
    Route::livewire('login', 'auth.login')->name('login');
    Route::livewire('forgot-password', 'auth.forgot-password')->name('password.request');
});

// Password reset works for both guests and authenticated users
Route::middleware(['web'])->group(function (): void {
    Route::livewire('reset-password/{token}', 'auth.reset-password')->name('password.reset');
});
