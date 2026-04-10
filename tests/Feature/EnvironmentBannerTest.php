<?php

declare(strict_types=1);

use Livewire\Livewire;

uses(Tests\TestCase::class);

it('shows blue Local banner when env is local', function (): void {
    app()->detectEnvironment(fn () => 'local');

    Livewire::test('layout.environment-banner')
        ->assertSet('environment', 'local')
        ->assertSet('label', 'Local')
        ->assertSee('Local')
        ->assertSeeHtml('bg-blue-100');
});

it('shows green Development banner when env is development', function (): void {
    app()->detectEnvironment(fn () => 'development');

    Livewire::test('layout.environment-banner')
        ->assertSet('environment', 'development')
        ->assertSet('label', 'Development')
        ->assertSee('Development')
        ->assertSeeHtml('bg-emerald-100');
});

it('shows orange Staging banner when env is staging', function (): void {
    app()->detectEnvironment(fn () => 'staging');

    Livewire::test('layout.environment-banner')
        ->assertSet('environment', 'staging')
        ->assertSet('label', 'Staging')
        ->assertSee('Staging')
        ->assertSeeHtml('bg-orange-100');
});

it('hides banner in production', function (): void {
    app()->detectEnvironment(fn () => 'production');

    Livewire::test('layout.environment-banner')
        ->assertSet('environment', null)
        ->assertDontSee('Local')
        ->assertDontSee('Development')
        ->assertDontSee('Staging');
});

it('hides banner for unknown custom environments', function (): void {
    app()->detectEnvironment(fn () => 'qa');

    Livewire::test('layout.environment-banner')
        ->assertSet('environment', null);
});
