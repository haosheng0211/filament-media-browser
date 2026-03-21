<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use MrJin\FilamentMediaBrowser\Livewire\MediaBrowserModal;

beforeEach(function () {
    Storage::fake('public');

    $this->actingAs(new class extends User
    {
        public $email = 'test@example.com';

        public function getAuthIdentifier()
        {
            return 1;
        }
    });
});

// --- Open / Close ---

it('opens the browser with default config', function () {
    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->assertSet('isOpen', true)
        ->assertSet('statePath', 'data.content')
        ->assertSet('mediaType', 'image')
        ->assertSet('disk', 'public')
        ->assertSet('directory', 'media')
        ->assertSet('currentPath', 'media');
});

it('opens the browser with custom disk and directory', function () {
    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'file', disk: 'public', directory: 'uploads')
        ->assertSet('isOpen', true)
        ->assertSet('disk', 'public')
        ->assertSet('directory', 'uploads')
        ->assertSet('currentPath', 'uploads');
});

it('closes the browser', function () {
    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content')
        ->call('closeBrowser')
        ->assertSet('isOpen', false);
});

it('normalizes invalid media type to image', function () {
    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'invalid')
        ->assertSet('mediaType', 'image');
});

// --- File Listing ---

it('lists files in the current directory', function () {
    Storage::disk('public')->put('media/photo.jpg', 'fake-image');
    Storage::disk('public')->put('media/doc.pdf', 'fake-pdf');

    $component = Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'file');

    $contents = $component->viewData('contents');

    expect($contents['files'])->toHaveCount(2);
});

it('filters files by image media type', function () {
    Storage::disk('public')->put('media/photo.jpg', 'fake-image');
    Storage::disk('public')->put('media/video.mp4', 'fake-video');

    $component = Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image');

    $contents = $component->viewData('contents');

    $file_names = array_column($contents['files'], 'name');
    expect($file_names)->toContain('photo.jpg')
        ->and($file_names)->not->toContain('video.mp4');
});

it('filters files by search term', function () {
    Storage::disk('public')->put('media/alpha.jpg', 'fake');
    Storage::disk('public')->put('media/beta.jpg', 'fake');

    $component = Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->set('search', 'alpha');

    $contents = $component->viewData('contents');

    expect($contents['files'])->toHaveCount(1)
        ->and($contents['files'][0]['name'])->toBe('alpha.jpg');
});

it('lists subdirectories', function () {
    Storage::disk('public')->makeDirectory('media/photos');
    Storage::disk('public')->makeDirectory('media/docs');

    $component = Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'file');

    $contents = $component->viewData('contents');

    expect($contents['folders'])->toContain('docs', 'photos');
});

// --- Folder Navigation ---

it('navigates into a subfolder', function () {
    Storage::disk('public')->makeDirectory('media/photos');
    Storage::disk('public')->put('media/photos/cat.jpg', 'fake');

    $component = Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->call('navigateToFolder', 'photos');

    expect($component->get('currentPath'))->toBe('media/photos');

    $contents = $component->viewData('contents');
    expect($contents['files'])->toHaveCount(1)
        ->and($contents['files'][0]['name'])->toBe('cat.jpg');
});

it('navigates up to parent folder', function () {
    Storage::disk('public')->makeDirectory('media/photos');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->call('navigateToFolder', 'photos')
        ->assertSet('currentPath', 'media/photos')
        ->call('navigateUp')
        ->assertSet('currentPath', 'media');
});

it('does not navigate above root directory', function () {
    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->call('navigateUp')
        ->assertSet('currentPath', 'media');
});

it('navigates via breadcrumb', function () {
    Storage::disk('public')->makeDirectory('media/a/b');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->call('navigateToFolder', 'a')
        ->call('navigateToFolder', 'b')
        ->assertSet('currentPath', 'media/a/b')
        ->call('navigateToBreadcrumb', 1)
        ->assertSet('currentPath', 'media/a');
});

it('prevents path traversal on navigateToFolder', function () {
    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->call('navigateToFolder', '../../etc')
        ->assertSet('currentPath', 'media');
});

it('navigates to path via sidebar', function () {
    Storage::disk('public')->makeDirectory('media/photos/vacation');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->call('navigateToPath', 'media/photos/vacation')
        ->assertSet('currentPath', 'media/photos/vacation');
});

it('prevents navigateToPath outside root directory', function () {
    Storage::disk('public')->makeDirectory('secret');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->call('navigateToPath', 'secret')
        ->assertSet('currentPath', 'media');
});

it('prevents navigateToPath with path traversal', function () {
    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->call('navigateToPath', 'media/../secret')
        ->assertSet('currentPath', 'media');
});

it('returns folder tree structure', function () {
    Storage::disk('public')->makeDirectory('media/images');
    Storage::disk('public')->makeDirectory('media/images/photos');
    Storage::disk('public')->makeDirectory('media/docs');

    $component = Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image');

    $tree = $component->viewData('folderTree');

    $names = array_column($tree, 'name');
    expect($names)->toContain('docs', 'images');

    $images_node = collect($tree)->firstWhere('name', 'images');
    $child_names = array_column($images_node['children'], 'name');
    expect($child_names)->toContain('photos');
});

it('returns directory stats', function () {
    Storage::disk('public')->makeDirectory('media/photos');
    Storage::disk('public')->put('media/file1.jpg', 'fake');
    Storage::disk('public')->put('media/file2.jpg', 'fake');

    $component = Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'file');

    $stats = $component->viewData('directoryStats');

    expect($stats['files'])->toBe(2)
        ->and($stats['folders'])->toBe(1);
});

// --- Create Folder ---

it('creates a new folder', function () {
    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->set('newFolderName', 'My Photos')
        ->call('createFolder');

    Storage::disk('public')->assertExists('media/my-photos');
});

it('ignores empty folder name', function () {
    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->set('newFolderName', '')
        ->call('createFolder');

    expect(Storage::disk('public')->directories('media'))->toBeEmpty();
});

// --- Upload ---

it('uploads a file to current directory', function () {
    $file = UploadedFile::fake()->image('test-photo.jpg');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->set('upload', [$file])
        ->call('uploadFile');

    $files = Storage::disk('public')->files('media');
    expect($files)->toHaveCount(1)
        ->and($files[0])->toContain('test-photo');
});

it('allows SVG mime type in image/* wildcard expansion', function () {
    $accepted = ['image/*'];

    $modal = new MediaBrowserModal;
    $rules = (new \ReflectionMethod($modal, 'buildMimeValidationRules'))->invoke($modal, $accepted);

    expect($rules)->toContain('image/svg+xml')
        ->and($rules)->toContain('image/svg');
});

it('uploads multiple files at once', function () {
    $file1 = UploadedFile::fake()->image('photo-a.jpg');
    $file2 = UploadedFile::fake()->image('photo-b.jpg');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->set('upload', [$file1, $file2])
        ->call('uploadFile');

    $files = Storage::disk('public')->files('media');
    expect($files)->toHaveCount(2);
});

it('appends random suffix when filename conflicts', function () {
    Storage::disk('public')->put('media/test-photo.jpg', 'existing');

    $file = UploadedFile::fake()->image('test-photo.jpg');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->set('upload', [$file])
        ->call('uploadFile');

    $files = Storage::disk('public')->files('media');
    expect($files)->toHaveCount(2);
});

it('rejects upload exceeding max file size', function () {
    config()->set('filament-media-browser.max_file_size', 1); // 1 KB

    $file = UploadedFile::fake()->create('big.jpg', 100, 'image/jpeg');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->set('upload', [$file])
        ->call('uploadFile')
        ->assertHasErrors('upload.*');
});

// --- Select File ---

it('dispatches media-selected event when selecting a file', function () {
    Storage::disk('public')->put('media/photo.jpg', 'fake');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->call('selectFile', 'media/photo.jpg')
        ->assertDispatched('media-selected', fn (string $name, array $params) => $params['statePath'] === 'data.content'
            && str_contains($params['url'], 'photo.jpg')
            && $params['alt'] === 'photo'
        )
        ->assertSet('isOpen', false);
});

it('closes browser after single select', function () {
    Storage::disk('public')->put('media/photo.jpg', 'fake');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image', multiple: false)
        ->call('selectFile', 'media/photo.jpg')
        ->assertSet('isOpen', false);
});

it('toggles file selection in multiple mode', function () {
    Storage::disk('public')->put('media/photo.jpg', 'fake');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.gallery', mediaType: 'image', multiple: true)
        ->call('selectFile', 'media/photo.jpg')
        ->assertSet('selectedFiles', ['media/photo.jpg'])
        ->assertNotDispatched('media-selected')
        ->assertSet('isOpen', true)
        // Toggle off
        ->call('selectFile', 'media/photo.jpg')
        ->assertSet('selectedFiles', []);
});

it('dispatches events on confirmSelection in multiple mode', function () {
    Storage::disk('public')->put('media/a.jpg', 'fake');
    Storage::disk('public')->put('media/b.jpg', 'fake');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.gallery', mediaType: 'image', multiple: true)
        ->call('selectFile', 'media/a.jpg')
        ->call('selectFile', 'media/b.jpg')
        ->assertSet('selectedFiles', ['media/a.jpg', 'media/b.jpg'])
        ->call('confirmSelection')
        ->assertDispatched('media-selected')
        ->assertSet('selectedFiles', [])
        ->assertSet('isOpen', false);
});

it('resets selectedFiles on open', function () {
    Storage::disk('public')->put('media/photo.jpg', 'fake');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.gallery', mediaType: 'image', multiple: true)
        ->call('selectFile', 'media/photo.jpg')
        ->assertSet('selectedFiles', ['media/photo.jpg'])
        ->dispatch('open-media-browser', statePath: 'data.gallery', mediaType: 'image', multiple: true)
        ->assertSet('selectedFiles', []);
});

it('dispatches url by default when storeAsUrl is true', function () {
    Storage::disk('public')->put('media/photo.jpg', 'fake');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image', storeAsUrl: true)
        ->call('selectFile', 'media/photo.jpg')
        ->assertDispatched('media-selected', fn (string $name, array $params) => str_starts_with($params['url'], 'http') || str_starts_with($params['url'], '/')
        );
});

it('dispatches raw path when storeAsUrl is false', function () {
    Storage::disk('public')->put('media/photo.jpg', 'fake');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image', storeAsUrl: false)
        ->call('selectFile', 'media/photo.jpg')
        ->assertDispatched('media-selected', fn (string $name, array $params) => $params['url'] === 'media/photo.jpg'
        );
});

it('defaults storeAsUrl to true when not provided', function () {
    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->assertSet('storeAsUrl', true);
});

it('reads storeAsUrl default from config when not provided', function () {
    config()->set('filament-media-browser.store_as_url', false);

    Storage::disk('public')->put('media/photo.jpg', 'fake');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->assertSet('storeAsUrl', false)
        ->call('selectFile', 'media/photo.jpg')
        ->assertDispatched('media-selected', fn (string $name, array $params) => $params['url'] === 'media/photo.jpg'
        );
});

it('per-field storeAsUrl overrides config default', function () {
    config()->set('filament-media-browser.store_as_url', false);

    Storage::disk('public')->put('media/photo.jpg', 'fake');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image', storeAsUrl: true)
        ->assertSet('storeAsUrl', true)
        ->call('selectFile', 'media/photo.jpg')
        ->assertDispatched('media-selected', fn (string $name, array $params) => str_starts_with($params['url'], 'http') || str_starts_with($params['url'], '/')
        );
});

it('prevents selecting files outside root directory', function () {
    Storage::disk('public')->put('secret/data.txt', 'secret');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'file')
        ->call('selectFile', 'secret/data.txt')
        ->assertNotDispatched('media-selected');
});

// --- Delete ---

it('deletes a file', function () {
    Storage::disk('public')->put('media/to-delete.jpg', 'fake');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->call('deleteFile', 'media/to-delete.jpg');

    Storage::disk('public')->assertMissing('media/to-delete.jpg');
});

it('prevents deleting files outside root directory', function () {
    Storage::disk('public')->put('secret/important.txt', 'secret');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'file')
        ->call('deleteFile', 'secret/important.txt');

    Storage::disk('public')->assertExists('secret/important.txt');
});

it('deletes a folder and its contents', function () {
    Storage::disk('public')->put('media/old-folder/file.jpg', 'fake');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->call('deleteFolder', 'old-folder');

    Storage::disk('public')->assertMissing('media/old-folder/file.jpg');
    expect(Storage::disk('public')->directories('media'))->not->toContain('media/old-folder');
});

it('prevents deleting folders outside root directory', function () {
    Storage::disk('public')->makeDirectory('other/secret');
    Storage::disk('public')->makeDirectory('media');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'file', directory: 'media')
        ->call('deleteFolder', '../other/secret');

    // The folder name contains ".." so it should be rejected by path check
    Storage::disk('public')->assertExists('other/secret');
});

it('deletes a folder by path from sidebar', function () {
    Storage::disk('public')->put('media/photos/vacation/img.jpg', 'fake');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->call('deleteFolderByPath', 'media/photos/vacation');

    Storage::disk('public')->assertMissing('media/photos/vacation/img.jpg');
});

it('navigates to parent when deleting current folder from sidebar', function () {
    Storage::disk('public')->makeDirectory('media/photos');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->call('navigateToPath', 'media/photos')
        ->assertSet('currentPath', 'media/photos')
        ->call('deleteFolderByPath', 'media/photos')
        ->assertSet('currentPath', 'media');
});

it('prevents deleting root directory from sidebar', function () {
    Storage::disk('public')->makeDirectory('media');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->call('deleteFolderByPath', 'media');

    Storage::disk('public')->assertExists('media');
});

it('prevents deleteFolderByPath outside root directory', function () {
    Storage::disk('public')->makeDirectory('secret');

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->call('deleteFolderByPath', 'secret');

    Storage::disk('public')->assertExists('secret');
});

// --- Disk & Directory Validation ---

it('falls back to default disk when invalid disk is provided', function () {
    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image', disk: 'nonexistent-disk')
        ->assertSet('disk', 'public');
});

it('falls back to default directory when empty directory is provided', function () {
    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image', directory: '')
        ->assertSet('directory', 'media');
});

it('falls back to default directory when traversal directory is provided', function () {
    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image', directory: '../etc')
        ->assertSet('directory', 'media');
});

it('falls back to default directory when absolute path directory is provided', function () {
    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image', directory: '/etc/passwd')
        ->assertSet('directory', 'media');
});

it('blocks direct frontend modification of disk property', function () {
    expect(fn () => Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->set('disk', 'local')
    )->toThrow(\RuntimeException::class, 'Direct modification of disk is not allowed.');
});

it('blocks direct frontend modification of directory property', function () {
    expect(fn () => Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content', mediaType: 'image')
        ->set('directory', '')
    )->toThrow(\RuntimeException::class, 'Direct modification of directory is not allowed.');
});

// --- Authentication ---

it('blocks unauthenticated access on open', function () {
    auth()->logout();

    Livewire::test(MediaBrowserModal::class)
        ->dispatch('open-media-browser', statePath: 'data.content')
        ->assertForbidden();
});

it('blocks unauthenticated upload', function () {
    auth()->logout();

    Livewire::test(MediaBrowserModal::class)
        ->call('uploadFile')
        ->assertForbidden();
});

it('blocks unauthenticated delete', function () {
    auth()->logout();

    Livewire::test(MediaBrowserModal::class)
        ->call('deleteFile', 'media/photo.jpg')
        ->assertForbidden();
});
