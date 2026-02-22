<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\User;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('Image Form Component', function (): void {

    beforeEach(function (): void {
        $this->admin = User::factory()->adminUser()->withSelectedApp('setup')->create();
        $this->actingAs($this->admin);
    });

    it('renders without error when no image is set', function (): void {
        Livewire::test('image-field-test', [
            'initialModel' => [],
        ])
            ->assertSuccessful();
    });

    it('renders without error when image field has a string URL value', function (): void {
        Livewire::test('image-field-test', [
            'initialModel' => ['image' => 'https://example.com/photo.jpg'],
        ])
            ->assertSuccessful();
    });

    it('shows choose image from media button', function (): void {
        Livewire::test('image-field-test', [
            'initialModel' => [],
        ])
            ->assertSee(__('Choose image from media'));
    });
});
