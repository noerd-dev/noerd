<?php

use Livewire\Volt\Volt;
use Noerd\Noerd\Models\Language;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class);

$testSettings = [
    'listName' => 'setup.languages-list',
    'componentName' => 'setup.language-detail',
];

it('resolves setup languages route and renders table', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $this->actingAs($admin);

    // Route comes from noerd routes group
    $response = $this->get(route('languages'));
    $response->assertStatus(200);

    Volt::test($testSettings['listName'])
        ->assertViewIs('volt-livewire::setup.languages-list');
});

it('lists languages for tenant in table with sorting and search', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $this->actingAs($admin);

    // seed a few
    Language::create(['tenant_id' => $admin->selected_tenant_id, 'code' => 'de', 'name' => 'Deutsch', 'is_default' => true]);
    Language::create(['tenant_id' => $admin->selected_tenant_id, 'code' => 'en', 'name' => 'English', 'is_default' => false]);

    Volt::test($testSettings['listName'])
        ->set('search', 'Eng')
        ->call('with')
        ->assertSet('search', 'Eng');
});

it('opens language-component modal from table', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $this->actingAs($admin);

    Volt::test($testSettings['listName'])
        ->call('tableAction', 5)
        ->assertDispatched('noerdModal', component: 'language-detail');
});
