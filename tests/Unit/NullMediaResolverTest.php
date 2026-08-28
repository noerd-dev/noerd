<?php

use Noerd\Services\NullMediaResolver;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('returns null for getPreviewUrl', function (): void {
    $resolver = new NullMediaResolver();

    expect($resolver->getPreviewUrl(1))->toBeNull();
});

it('returns false for exists', function (): void {
    $resolver = new NullMediaResolver();

    expect($resolver->exists(1))->toBeFalse();
});

it('returns null for getRelativeUrl', function (): void {
    $resolver = new NullMediaResolver();

    expect($resolver->getRelativeUrl(1))->toBeNull();
});

it('returns null for storeUploadedFile with null input', function (): void {
    $resolver = new NullMediaResolver();

    expect($resolver->storeUploadedFile(null))->toBeNull();
});

it('stores an allowed image under a safe generated name and returns its url', function (): void {
    $fakeFile = Mockery::mock();
    $fakeFile->shouldReceive('getMimeType')->andReturn('image/jpeg');
    $fakeFile->shouldReceive('getSize')->andReturn(1024);
    $fakeFile->shouldReceive('storeAs')
        ->with('uploads', Mockery::pattern('/^[0-9a-f-]+\.jpg$/'), 'public')
        ->once()
        ->andReturn('uploads/generated.jpg');

    $resolver = new NullMediaResolver();

    expect($resolver->storeUploadedFile($fakeFile))->toBe('/storage/uploads/generated.jpg');
});

it('rejects a non-image upload (e.g. SVG) without storing it', function (): void {
    $fakeFile = Mockery::mock();
    $fakeFile->shouldReceive('getMimeType')->andReturn('image/svg+xml');
    $fakeFile->shouldReceive('getSize')->andReturn(1024);
    $fakeFile->shouldNotReceive('storeAs');

    $resolver = new NullMediaResolver();

    expect($resolver->storeUploadedFile($fakeFile))->toBeNull();
});

it('rejects an oversized upload without storing it', function (): void {
    $fakeFile = Mockery::mock();
    $fakeFile->shouldReceive('getMimeType')->andReturn('image/png');
    $fakeFile->shouldReceive('getSize')->andReturn(20 * 1024 * 1024);
    $fakeFile->shouldNotReceive('storeAs');

    $resolver = new NullMediaResolver();

    expect($resolver->storeUploadedFile($fakeFile))->toBeNull();
});

it('returns false for isAvailable', function (): void {
    $resolver = new NullMediaResolver();

    expect($resolver->isAvailable())->toBeFalse();
});
