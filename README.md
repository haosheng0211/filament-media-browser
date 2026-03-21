# Filament Media Browser

[![Latest Version on Packagist](https://img.shields.io/packagist/v/haosheng0211/filament-media-browser.svg?style=flat-square)](https://packagist.org/packages/haosheng0211/filament-media-browser)
[![Total Downloads](https://img.shields.io/packagist/dt/haosheng0211/filament-media-browser.svg?style=flat-square)](https://packagist.org/packages/haosheng0211/filament-media-browser)

A standalone Filament v3 media browser powered by Laravel Storage — no database required. Browse, upload, and manage files directly from the filesystem with folder navigation support.

## Features

- Browse files and folders on any Laravel Storage disk
- Upload, delete files and create/delete folders
- Breadcrumb navigation with subfolder support
- Filter by media type (image, video/audio, or all files)
- Search files by name
- Filename sanitization and collision handling
- Path traversal protection
- Authentication guard on all operations
- Dark mode support
- Works with any Filament v3 panel or Livewire component

## Installation

```bash
composer require haosheng0211/filament-media-browser
```

Publish the config file:

```bash
php artisan vendor:publish --tag="filament-media-browser-config"
```

Add the package views to your `tailwind.config.js` content array:

```js
// tailwind.config.js
content: [
    // ...existing paths
    './vendor/haosheng0211/filament-media-browser/resources/views/**/*.blade.php',
]
```

Then rebuild your assets:

```bash
npm run build
```

Make sure the storage disk is linked (for the `public` disk):

```bash
php artisan storage:link
```

## Configuration

```php
// config/filament-media-browser.php

return [
    // Laravel filesystem disk
    'disk' => 'public',

    // Root directory for browsing
    'directory' => 'media',

    // Allowed upload MIME types
    'accepted_file_types' => ['image/*', 'video/*', 'audio/*'],

    // Max upload size in KB
    'max_file_size' => 10240,
];
```

## Usage

### MediaPicker Field

A dedicated form field with image preview, replace and remove buttons. Stores the selected file's URL as a string (single) or array of strings (multiple) by default.

```php
use MrJin\FilamentMediaBrowser\Forms\Components\MediaPicker;

// Basic usage — single image picker with preview
MediaPicker::make('featured_image');

// Multiple images (gallery)
MediaPicker::make('gallery')
    ->multiple()
    ->maxItems(10)
    ->reorderable(); // drag-to-reorder, enabled by default

// File picker (no image preview, shows path instead)
MediaPicker::make('attachment')
    ->mediaType('file');

// Custom disk and directory
MediaPicker::make('hero_image')
    ->mediaDisk('s3')
    ->mediaDirectory('uploads/heroes');

// Store relative path instead of URL
MediaPicker::make('document')
    ->storePath();
```

**Stored value:**
- Single mode: string (e.g. `/storage/media/photo.jpg`) — use a `string` column
- Multiple mode: array of strings — use a `json` column with `->cast('array')` on the model
- Default output is `Storage::url()` (full URL). Use `->storePath()` to store the relative path (e.g. `media/photo.jpg`) instead.

#### Available Methods

| Method | Description |
|---|---|
| `->multiple(bool)` | Enable multi-select mode |
| `->maxItems(int)` | Maximum number of files (multiple mode) |
| `->reorderable(bool)` | Enable drag-to-reorder (multiple mode, default: true) |
| `->mediaType(string)` | `'image'` (default), `'media'`, or `'file'` |
| `->mediaDisk(string)` | Override config disk |
| `->mediaDirectory(string)` | Override config directory |
| `->storeAsUrl(bool)` | Store as `Storage::url()` output (default: true) |
| `->storePath(bool)` | Store as relative path (e.g. `media/photo.jpg`) |

### With Filament Forms TinyMCE

This package works seamlessly with [filament-forms-tinymce](https://github.com/haosheng0211/filament-forms-tinymce). Once both packages are installed, TinyMCE's file picker will automatically use the media browser.

```php
use MrJin\FilamentFormsTinymce\TinyMceEditor;

TinyMceEditor::make('content');

// With custom disk/directory per field
TinyMceEditor::make('content')
    ->mediaDisk('s3')
    ->mediaDirectory('uploads/posts');

// Disable the file browser
TinyMceEditor::make('content')
    ->fileBrowser(false);
```

### Standalone Usage via Events

The media browser communicates via Livewire events, so you can integrate it with any component.

**Open the browser** by dispatching the `open-media-browser` event:

```php
// From Livewire — single file
$this->dispatch('open-media-browser', statePath: 'data.image', mediaType: 'image');

// Multiple files with custom disk/directory
$this->dispatch('open-media-browser',
    statePath: 'data.files',
    mediaType: 'file',
    multiple: true,
    disk: 's3',
    directory: 'uploads',
);

// Store relative path instead of URL
$this->dispatch('open-media-browser',
    statePath: 'data.document',
    mediaType: 'file',
    storeAsUrl: false,
);
```

```html
<!-- From Alpine.js -->
<button x-on:click="Livewire.dispatch('open-media-browser', {
    statePath: 'data.image',
    mediaType: 'image',
})">
    Browse Media
</button>
```

**Listen for file selection** via the `media-selected` event:

```php
// Livewire
#[On('media-selected')]
public function onMediaSelected(
    string $statePath,
    string $url,
    string $alt,
    string $title,
    string $filename,
    string $extension,
    int $size,
    string $mime,
): void {
    // Use the selected media URL and metadata
}
```

```html
<!-- Alpine.js -->
<div x-on:media-selected.window="handleMedia($event.detail)">
```

### Event Reference

| Event | Direction | Parameters |
|---|---|---|
| `open-media-browser` | You → Browser | `statePath`, `mediaType` (`image` \| `media` \| `file`), `multiple?`, `disk?`, `directory?`, `storeAsUrl?` |
| `media-selected` | Browser → You | `statePath`, `url`, `alt`, `title`, `filename`, `extension`, `size`, `mime` |

### Media Types

| Value | Filters |
|---|---|
| `image` | `image/*` only |
| `media` | `video/*` and `audio/*` |
| `file` | All files (no filter) |

## Customizing Views

```bash
php artisan vendor:publish --tag="filament-media-browser-views"
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
