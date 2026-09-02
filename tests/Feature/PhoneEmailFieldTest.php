<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\NoerdUser;
use Noerd\Services\FieldTypeRegistry;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('registers the phone and email field types as includes', function (): void {
    $registry = app(FieldTypeRegistry::class);

    expect($registry->has('phone'))->toBeTrue();
    expect($registry->has('email'))->toBeTrue();

    $phone = $registry->resolve('phone');
    expect($phone?->kind)->toBe('include');
    expect($phone?->target)->toBe('noerd::components.forms.phone');

    $email = $registry->resolve('email');
    expect($email?->kind)->toBe('include');
    expect($email?->target)->toBe('noerd::components.forms.email');
});

it('renders phone and email inputs with tel and mailto action links', function (): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $this->actingAs($admin);

    Livewire::test('noerd-test::block-phone-email-test')
        ->assertSuccessful()
        ->assertSeeHtml('type="tel"')
        ->assertSeeHtml('type="email"')
        ->assertSeeHtml('wire:model="model.phone"')
        ->assertSeeHtml('wire:model="model.email"')
        ->assertSeeHtml("'tel:' +")
        ->assertSeeHtml("'mailto:' +");
});

it('keeps the call link available on readonly phone fields', function (): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $this->actingAs($admin);

    Livewire::test('noerd-test::block-phone-email-test')
        ->assertSuccessful()
        ->assertSeeHtml('wire:model="model.roPhone"')
        ->assertSeeHtml("\$wire.entangle('model.roPhone')");
});
