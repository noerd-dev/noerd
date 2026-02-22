<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\User;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('RichText Form Component', function (): void {

    beforeEach(function (): void {
        $this->admin = User::factory()->adminUser()->withSelectedApp('setup')->create();
        $this->actingAs($this->admin);
    });

    it('renders without error with empty content', function (): void {
        Livewire::test('rich-text-field-test', [
            'initialModel' => [],
        ])
            ->assertSuccessful();
    });

    it('renders without error with HTML content', function (): void {
        Livewire::test('rich-text-field-test', [
            'initialModel' => ['content' => '<p>Hello world</p>'],
        ])
            ->assertSuccessful();
    });

    it('shows the label', function (): void {
        Livewire::test('rich-text-field-test', [
            'initialModel' => [],
        ])
            ->assertSee('Content');
    });
});
