<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('exposes the full heroicon list', function (): void {
    $user = NoerdUser::factory()->withExampleTenant()->create();

    Livewire::actingAs($user)
        ->test('noerd::icon-picker', ['context' => 'detailData.icon'])
        ->assertViewHas('icons', fn ($icons): bool => count($icons) > 300);
});

it('writes the chosen icon to the parent detail and closes the modal', function (): void {
    $user = NoerdUser::factory()->withExampleTenant()->create();

    Livewire::actingAs($user)
        ->test('noerd::icon-picker', ['context' => 'detailData.icon'])
        ->call('selectIcon', 'trophy')
        ->assertDispatched('setFieldValue', field: 'detailData.icon', value: 'trophy')
        ->assertDispatched('closeTopModal');
});
