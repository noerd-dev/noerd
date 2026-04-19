<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\NoerdUser;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('Image Form Component', function (): void {

    beforeEach(function (): void {
        $this->admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
        $this->actingAs($this->admin);
    });

    it('renders without error when no image is set', function (): void {
        Livewire::test('noerd::image-field-test', [
            'initialModel' => [],
        ])
            ->assertSuccessful();
    });

    it('renders without error when image field has a string URL value', function (): void {
        Livewire::test('noerd::image-field-test', [
            'initialModel' => ['image' => 'https://example.com/photo.jpg'],
        ])
            ->assertSuccessful();
    });

    it('shows choose image from media button', function (): void {
        $resolver = Mockery::mock(\Noerd\Contracts\MediaResolverContract::class);
        $resolver->shouldReceive('isAvailable')->andReturn(true);
        $resolver->shouldReceive('getPreviewUrl')->andReturn(null);
        app()->instance(\Noerd\Contracts\MediaResolverContract::class, $resolver);

        Livewire::test('noerd::image-field-test', [
            'initialModel' => [],
        ])
            ->assertSee(__('Choose image from media'));
    });
});
