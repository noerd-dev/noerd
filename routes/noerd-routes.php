<?php

use Livewire\Volt\Volt;
use Noerd\Noerd\Controllers\DashboardController;

Route::group(['middleware' => ['auth', 'verified', 'setup', 'web']], function (): void {
    Volt::route('setup', 'setup.users-table')->name('setup');
    Volt::route('users', 'setup.users-table')->name('users');
    Volt::route('user-roles', 'setup.user-roles-table')->name('user-roles');
    Volt::route('tenant', 'setup.tenant-component')->name('tenant');
});

Route::group(['middleware' => ['auth', 'verified', 'web']], function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::view('profile', 'noerd::profile')->name('profile');
});

