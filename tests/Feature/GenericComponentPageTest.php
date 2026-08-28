<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('registers the generic component-page route', function (): void {
    expect(Route::has('noerd.component-page'))->toBeTrue();
});

it('aborts with 404 when the component name has no module namespace', function (): void {
    $user = NoerdUser::factory()->withExampleTenant()->create();

    $this->actingAs($user)
        ->get(route('noerd.component-page', ['componentName' => 'some-local-component']))
        ->assertNotFound();
});

it('requires authentication', function (): void {
    $this->get(route('noerd.component-page', ['componentName' => 'noerd::dashboard']))
        ->assertRedirect(route('noerd.login'));
});
