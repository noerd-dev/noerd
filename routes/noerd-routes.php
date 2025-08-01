<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Noerd\Noerd\Controllers\Auth\VerifyEmailController;
use Noerd\Noerd\Controllers\DashboardController;

Route::group(['middleware' => ['auth', 'verified', 'setup', 'web']], function (): void {
    Volt::route('setup', 'setup.users-table')->name('setup');
    Volt::route('users', 'setup.users-table')->name('users');
    Volt::route('user-roles', 'setup.user-roles-table')->name('user-roles');
    Volt::route('tenant', 'setup.tenant-component')->name('tenant');
    Volt::route('noerd-home', 'noerd-home')->name('noerd-home');
});

Route::group(['middleware' => ['auth', 'verified', 'web']], function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::view('profile', 'noerd::profile')->name('profile');
});

Route::group(['middleware' => ['web']], function (): void {

    Route::middleware('guest')->group(function () {
        Volt::route('login', 'auth.login')
            ->name('login');

        Volt::route('register', 'auth.register')
            ->name('register');

        Volt::route('forgot-password', 'auth.forgot-password')
            ->name('password.request');

        Volt::route('reset-password/{token}', 'auth.reset-password')
            ->name('password.reset');
    });
});

Route::group(['middleware' => ['web']], function (): void {
    Route::middleware('auth')->group(function () {
        Volt::route('verify-email', 'auth.verify-email')
            ->name('verification.notice');

        Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Volt::route('confirm-password', 'auth.confirm-password')
            ->name('password.confirm');
    });

    Route::post('logout', Noerd\Noerd\Livewire\Actions\Logout::class)
        ->name('logout');

    Route::middleware(['auth'])->group(function () {
        Route::redirect('settings', 'settings/profile');

        Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
        Volt::route('settings/password', 'settings.password')->name('settings.password');
        Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    });
});
