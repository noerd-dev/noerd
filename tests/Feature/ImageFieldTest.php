<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Contracts\MediaResolverContract;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Image Form Component', function (): void {

    beforeEach(function (): void {
        $this->admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
        $this->actingAs($this->admin);
    });

    it('shows choose image from media button', function (): void {
        $resolver = Mockery::mock(MediaResolverContract::class);
        $resolver->shouldReceive('isAvailable')->andReturn(true);
        $resolver->shouldReceive('getPreviewUrl')->andReturn(null);
        app()->instance(MediaResolverContract::class, $resolver);

        Livewire::test('noerd-test::image-field-test', [
            'initialModel' => [],
        ])
            ->assertSee(__('Choose image from media'));
    });

    it('reveals hover-only actions with a non-destructive confirmation when an image is set', function (): void {
        $resolver = Mockery::mock(MediaResolverContract::class);
        $resolver->shouldReceive('isAvailable')->andReturn(true);
        $resolver->shouldReceive('getPreviewUrl')->andReturn('https://example.com/photo.jpg');
        app()->instance(MediaResolverContract::class, $resolver);

        Livewire::test('noerd-test::image-field-test', [
            'initialModel' => ['image' => 5],
        ])
            ->assertSee(__('Remove this image? The original file stays in the media library.'))
            ->assertDontSee(__('Really delete image?'))
            ->assertSeeHtml('group-hover:opacity-100')
            ->assertSee(__('Choose image from media'));
    });
});
