<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Noerd\Models\NoerdUser;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('registers the generic component-page route', function (): void {
    expect(Route::has('component-page'))->toBeTrue();
});

it('aborts with 404 when the component name has no module namespace', function (): void {
    $user = NoerdUser::factory()->withExampleTenant()->create();

    $this->actingAs($user)
        ->get(route('component-page', ['componentName' => 'some-local-component']))
        ->assertNotFound();
});

it('requires authentication', function (): void {
    $this->get(route('component-page', ['componentName' => 'noerd::dashboard']))
        ->assertRedirect(route('login'));
});
