<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $user = NoerdUser::factory()->withExampleTenant()->withSelectedApp('setup')->create();

    $this->actingAs($user);
});

it('rejects a model class that is not auditable', function (): void {
    Livewire::test('noerd::audit-modal', ['modelClass' => Tenant::class, 'modelId' => 1])
        ->assertStatus(404);
});

it('rejects a class that is not an eloquent model', function (): void {
    Livewire::test('noerd::audit-modal', ['modelClass' => Illuminate\Support\Str::class, 'modelId' => 1])
        ->assertStatus(404);
});
