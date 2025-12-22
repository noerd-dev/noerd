<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Noerd\Noerd\Controllers\DashboardController;
use Noerd\Noerd\Http\Controllers\TenantInvoiceController;

Route::group(['middleware' => ['auth', 'verified', 'setup', 'web']], function (): void {
    Volt::route('setup', 'setup.users-list')->name('setup');
    Volt::route('users', 'setup.users-list')->name('users');
    Volt::route('user-roles', 'setup.user-roles-list')->name('user-roles');
    Volt::route('tenant', 'setup.tenant-detail')->name('tenant');
    Volt::route('models', 'models-list')->name('models');
    Volt::route('tenant-invoices', 'tenant-invoices-list')->name('tenant-invoices');
    Route::get('tenant-invoice/{hash}', [TenantInvoiceController::class, 'show'])->name('tenant-invoice');
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
