<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Contracts\MediaResolverContract;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Image Form Component', function (): void {

    beforeEach(function (): void {
        $this->admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
        $this->actingAs($this->admin);

        // A media module is optional — the field talks to whatever resolver is
        // bound, so the tests bind an available one and steer only the preview.
        $this->resolver = Mockery::mock(MediaResolverContract::class);
        $this->resolver->shouldReceive('isAvailable')->andReturn(true);
        app()->instance(MediaResolverContract::class, $this->resolver);
    });

    it('shows choose image from media button', function (): void {
        $this->resolver->shouldReceive('getPreviewUrl')->andReturn(null);

        Livewire::test('noerd-test::image-field-test', [
            'initialModel' => [],
        ])
            ->assertSee(__('Choose image from media'));
    });

    it('reveals hover-only actions with a non-destructive confirmation when an image is set', function (): void {
        $this->resolver->shouldReceive('getPreviewUrl')->andReturn('https://example.com/photo.jpg');

        Livewire::test('noerd-test::image-field-test', [
            'initialModel' => ['image' => 5],
        ])
            ->assertSee(__('Remove this image? The original file stays in the media library.'))
            ->assertDontSee(__('Really delete image?'))
            ->assertSee(__('Choose image from media'));
    });
});
