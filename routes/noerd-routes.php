<?php

use Livewire\Volt\Volt;
use Noerd\Noerd\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Noerd\Noerd\Controllers\Auth\VerifyEmailController;

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

Route::middleware('guest')->group(function (): void {
    Volt::route('login', 'auth.login')
        ->name('login');

    Volt::route('forgot-password', 'auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'auth.reset-password')
        ->name('password.reset');

});

Route::middleware('auth')->group(function (): void {
    Volt::route('verify-email', 'auth.verify-email')
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Volt::route('confirm-password', 'auth.confirm-password')
        ->name('password.confirm');
});
