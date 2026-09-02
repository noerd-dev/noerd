<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());
});

describe('spacer field', function (): void {

    it('renders a spacer as an empty grid column without rendering an input', function (): void {
        Livewire::test('noerd-test::block-spacer-test')
            ->assertSuccessful()
            ->assertSeeHtml('for="model.a"')
            ->assertSeeHtml('for="model.b"')
            ->assertSeeHtml('sm:col-span-6')
            ->assertDontSeeHtml('id=""'); // a fallback input (unregistered type) would have an empty id
    });
});

describe('phone and email fields', function (): void {

    it('renders phone and email inputs with tel and mailto action links', function (): void {
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
        Livewire::test('noerd-test::block-phone-email-test')
            ->assertSuccessful()
            ->assertSeeHtml('wire:model="model.roPhone"')
            ->assertSeeHtml("\$wire.entangle('model.roPhone')");
    });
});
