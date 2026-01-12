<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Noerd\Noerd\Controllers\DashboardController;

Route::group(['middleware' => ['auth', 'verified', 'setup', 'web']], function (): void {
    Volt::route('setup', 'setup.users-list')->name('setup');
    Volt::route('users', 'setup.users-list')->name('users');
    Volt::route('user/{model}', 'user-detail')->name('user.detail');
    Volt::route('user-roles', 'setup.user-roles-list')->name('user-roles');
    Volt::route('user-role/{model}', 'user-role-detail')->name('user-role.detail');
    Volt::route('tenant', 'setup.tenant-detail')->name('tenant');
    Volt::route('models', 'models-list')->name('models');
    Volt::route('setup-collections', 'setup-collections-list')->name('setup-collections');
    Volt::route('setup-collection/{model}', 'setup-collection-detail')->name('setup-collection.detail');
    Volt::route('setup-languages', 'setup-languages-list')->name('setup-languages');
    Volt::route('setup-language/{model}', 'setup-language-detail')->name('setup-language.detail');
    Volt::route('/detail/{component}/{id}', 'standalone-detail')->name('detail');
});

Route::group(['middleware' => ['auth', 'web']], function (): void {
    Volt::route('noerd-home', 'noerd-home')->name('noerd-home');
});

Route::group(['middleware' => ['auth', 'web']], function (): void {
    Volt::route('no-tenant', 'no-tenant')->name('no-tenant');
});

Route::group(['middleware' => ['auth', 'verified', 'web']], function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::view('profile', 'noerd::profile')->name('profile');
});

Route::middleware(['web', 'guest'])->group(function (): void {
    Volt::route('login', 'auth.login')->name('login');
    Volt::route('forgot-password', 'auth.forgot-password')->name('password.request');
    Volt::route('reset-password/{token}', 'auth.reset-password')->name('password.reset');
});
