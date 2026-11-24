<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());
});

it('offers the placeholders of the component method in the editor toolbar', function (): void {
    Livewire::test('noerd-test::rich-text-placeholders-test')
        ->assertSeeHtml('data-noerd-placeholder-menu')
        ->assertSeeHtml('data-noerd-placeholder="{{name}}"')
        ->assertSeeHtml('data-noerd-placeholder="{{city}}"')
        ->assertSee('Contact person');
});

it('offers no placeholder menu without a placeholders method', function (): void {
    Livewire::test('noerd-test::rich-text-placeholders-test', ['withPlaceholders' => false])
        ->assertDontSeeHtml('data-noerd-placeholder-menu');
});

it('offers no placeholder menu in a readonly editor', function (): void {
    Livewire::test('noerd-test::rich-text-placeholders-test', ['readonly' => true])
        ->assertDontSeeHtml('data-noerd-placeholder-menu');
});
