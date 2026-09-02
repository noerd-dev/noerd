<?php

declare(strict_types=1);

use Livewire\Livewire;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('shows blue Local banner when env is local', function (): void {
    app()->detectEnvironment(fn() => 'local');

    Livewire::test('noerd::layout.environment-banner')
        ->assertSet('environment', 'local')
        ->assertSet('label', 'Local')
        ->assertSee('Local');
});

it('shows green Development banner when env is development', function (): void {
    app()->detectEnvironment(fn() => 'development');

    Livewire::test('noerd::layout.environment-banner')
        ->assertSet('environment', 'development')
        ->assertSet('label', 'Development')
        ->assertSee('Development');
});

it('shows orange Staging banner when env is staging', function (): void {
    app()->detectEnvironment(fn() => 'staging');

    Livewire::test('noerd::layout.environment-banner')
        ->assertSet('environment', 'staging')
        ->assertSet('label', 'Staging')
        ->assertSee('Staging');
});

it('hides banner in production', function (): void {
    app()->detectEnvironment(fn() => 'production');

    Livewire::test('noerd::layout.environment-banner')
        ->assertSet('environment', null)
        ->assertDontSee('Local')
        ->assertDontSee('Development')
        ->assertDontSee('Staging');
});

it('hides banner for unknown custom environments', function (): void {
    app()->detectEnvironment(fn() => 'qa');

    Livewire::test('noerd::layout.environment-banner')
        ->assertSet('environment', null);
});
