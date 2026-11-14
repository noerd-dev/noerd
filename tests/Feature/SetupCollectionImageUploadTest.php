<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Noerd\Helpers\AccessHelper;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The plain image upload of a setup collection entry (used when no media
 | library is installed) is client input: only the image fields of the
 | collection take a file, only a raster image is stored, and only a user who
 | may save the entry uploads at all. Proven with a runtime collection.
 */

beforeEach(function (): void {
    Storage::fake('public');

    $this->collectionPath = base_path('app-configs/setup/collections/zz-images.yml');
    File::put($this->collectionPath, implode("\n", [
        'title: Zz Image',
        'titleList: Zz Images',
        'key: ZZ-IMAGES',
        'fields:',
        '  - name: detailData.name',
        '    label: Name',
        '    type: text',
        '  - name: detailData.logo',
        '    label: Logo',
        '    type: image',
    ]));

    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());
});

afterEach(function (): void {
    File::delete($this->collectionPath);
});

it('stores a raster image of a declared image field', function (): void {
    Livewire::test('noerd::setup-collection-detail', ['collectionKey' => 'zz-images'])
        ->set('imageUploads.logo', UploadedFile::fake()->image('logo.png'))
        ->assertHasNoErrors()
        ->assertSet('detailData.logo', fn(?string $url): bool => is_string($url) && str_starts_with($url, '/storage/uploads/'));
});

it('refuses an upload for a key that is no image field', function (): void {
    Livewire::test('noerd::setup-collection-detail', ['collectionKey' => 'zz-images'])
        ->set('imageUploads.name', UploadedFile::fake()->image('name.png'))
        ->assertSet('detailData.name', null);

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('refuses a file that is not an image', function (): void {
    Livewire::test('noerd::setup-collection-detail', ['collectionKey' => 'zz-images'])
        ->set('imageUploads.logo', UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'))
        ->assertHasErrors('imageUploads.logo')
        ->assertSet('detailData.logo', null);
});

it('refuses the upload of a user who may not save the entry', function (): void {
    Gate::define(AccessHelper::OBJECT_WRITE_GATE, fn(): bool => false);
    Gate::define(AccessHelper::OBJECT_CREATE_GATE, fn(): bool => false);

    Livewire::test('noerd::setup-collection-detail', ['collectionKey' => 'zz-images'])
        ->set('imageUploads.logo', UploadedFile::fake()->image('logo.png'))
        ->assertSet('detailData.logo', null);

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('ignores a remove request for a key that is no image field', function (): void {
    Livewire::test('noerd::setup-collection-detail', ['collectionKey' => 'zz-images'])
        ->set('detailData.name', 'Kept')
        ->call('deleteImage', 'name')
        ->assertSet('detailData.name', 'Kept');
});

/*
 | With a media library the entry stores the MEDIA ID — a picked file and an
 | uploaded one alike — never a URL that breaks once the file is moved.
 */
function zzBindMediaLibrary(array $existingIds = [42]): void
{
    app()->instance(\Noerd\Contracts\MediaResolverContract::class, new class ($existingIds) implements \Noerd\Contracts\MediaResolverContract {
        public function __construct(private array $existingIds) {}

        public function getPreviewUrl(int $mediaId): ?string
        {
            return '/media/thumb/' . $mediaId;
        }

        public function exists(int $mediaId): bool
        {
            return in_array($mediaId, $this->existingIds, true);
        }

        public function getRelativeUrl(int $mediaId): ?string
        {
            return '/storage/media/' . $mediaId . '.png';
        }

        public function getImageUrl(int $mediaId, string $variant = 'web'): ?string
        {
            return null;
        }

        public function storeUploadedFile(mixed $uploadedFile): ?string
        {
            return '/storage/media/42.png';
        }

        public function storeUploadedFileReference(mixed $uploadedFile): int|string|null
        {
            return 42;
        }

        public function isAvailable(): bool
        {
            return true;
        }

        public function pickerComponent(): ?string
        {
            return 'zz-media-picker';
        }
    });
}

it('stores the media id of an uploaded image when a library exists', function (): void {
    zzBindMediaLibrary();

    Livewire::test('noerd::setup-collection-detail', ['collectionKey' => 'zz-images'])
        ->set('imageUploads.logo', UploadedFile::fake()->image('logo.png'))
        ->assertSet('detailData.logo', 42)
        ->assertSeeHtml('/media/thumb/42');
});

it('stores the media id of a picked image, only with the token of its own picker', function (): void {
    zzBindMediaLibrary([42, 43]);

    $detail = Livewire::test('noerd::setup-collection-detail', ['collectionKey' => 'zz-images'])
        ->dispatch('mediaSelected', mediaId: 43, fieldName: 'logo', token: null)
        ->assertSet('detailData.logo', null)
        ->call('openSelectMediaModal', 'logo');

    $token = $detail->get('mediaSelectToken');

    expect($token)->toBeString()
        ->and($detail->get('detailData'))->not->toHaveKey('__mediaToken');

    $detail->dispatch('mediaSelected', mediaId: 99, fieldName: 'logo', token: $token)
        ->assertSet('detailData.logo', null)
        ->dispatch('mediaSelected', mediaId: 43, fieldName: 'logo', token: $token)
        ->assertSet('detailData.logo', 43)
        ->assertSet('mediaSelectToken', null);
});

it('keeps the picker token out of client reach', function (): void {
    zzBindMediaLibrary();

    Livewire::test('noerd::setup-collection-detail', ['collectionKey' => 'zz-images'])
        ->set('mediaSelectToken', 'forged');
})->throws(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

it('saves only the fields the collection definition declares', function (): void {
    zzBindMediaLibrary();

    Livewire::test('noerd::setup-collection-detail', ['collectionKey' => 'zz-images'])
        ->set('detailData.name', 'Acme')
        ->call('openSelectMediaModal', 'logo')
        ->call('store')
        ->assertHasNoErrors();

    $entry = \Noerd\Models\SetupCollectionEntry::query()->latest('id')->first();

    expect($entry->data)->toHaveKey('name', 'Acme')
        ->and(array_keys($entry->data))->each->toBeIn(['name', 'logo']);
});
