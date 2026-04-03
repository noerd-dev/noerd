<?php

use Noerd\Services\NullMediaResolver;

it('returns null for getPreviewUrl', function (): void {
    $resolver = new NullMediaResolver;

    expect($resolver->getPreviewUrl(1))->toBeNull();
});

it('returns false for exists', function (): void {
    $resolver = new NullMediaResolver;

    expect($resolver->exists(1))->toBeFalse();
});

it('returns null for getRelativeUrl', function (): void {
    $resolver = new NullMediaResolver;

    expect($resolver->getRelativeUrl(1))->toBeNull();
});

it('returns null for storeUploadedFile with null input', function (): void {
    $resolver = new NullMediaResolver;

    expect($resolver->storeUploadedFile(null))->toBeNull();
});

it('stores uploaded file and returns url', function (): void {
    $fakeFile = Mockery::mock();
    $fakeFile->shouldReceive('store')
        ->with('uploads', 'public')
        ->once()
        ->andReturn('uploads/photo.jpg');

    $resolver = new NullMediaResolver;

    $result = $resolver->storeUploadedFile($fakeFile);

    expect($result)->toBe('/storage/uploads/photo.jpg');
});

it('returns false for isAvailable', function (): void {
    $resolver = new NullMediaResolver;

    expect($resolver->isAvailable())->toBeFalse();
});
