<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\NoerdUser;
use Noerd\Services\FieldTypeRegistry;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('registers the spacer field type as an empty include', function (): void {
    $registry = app(FieldTypeRegistry::class);

    expect($registry->has('spacer'))->toBeTrue();

    $definition = $registry->resolve('spacer');
    expect($definition?->kind)->toBe('include');
    expect($definition?->target)->toBe('noerd::components.forms.spacer');
});

it('renders a spacer as an empty grid column without rendering an input', function (): void {
    $admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $this->actingAs($admin);

    Livewire::test('noerd::block-spacer-test')
        ->assertSuccessful()
        ->assertSeeHtml('for="model.a"')
        ->assertSeeHtml('for="model.b"')
        ->assertSeeHtml('sm:col-span-6')
        ->assertDontSeeHtml('id=""'); // a fallback input (unregistered type) would have an empty id
});
