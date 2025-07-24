<?php

use Livewire\Volt\Volt;

Route::group(['middleware' => ['auth', 'verified', 'setup', 'web']], function (): void {
    Volt::route('setup', 'setup.users-table')->name('setup');
    Volt::route('users', 'setup.users-table')->name('users');
    Volt::route('user-roles', 'setup.user-roles-table')->name('user-roles');
    Volt::route('tenant', 'setup.tenant-component')->name('tenant');
});
