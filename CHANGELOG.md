# Changelog

All notable changes to `filament-media-browser` will be documented in this file.

## v1.1.0 - 2026-03-21

### Added
- `storeAsUrl()` / `storePath()` methods on MediaPicker to choose between storing `Storage::url()` output or the raw relative path
- Disk validation — rejects unconfigured disk names, falls back to config default
- Directory validation — rejects empty strings, path traversal (`..`), and absolute paths
- Frontend tampering protection — blocks direct `$wire.set()` modification of `disk` and `directory` properties

## v1.0.0 - 2026-03-15

Initial release.

- MediaPicker form field with single/multiple file selection
- Drag-to-reorder support for multiple mode
- MediaBrowserModal with full file browsing UI
- Folder navigation with breadcrumbs and sidebar tree
- File upload with MIME type validation
- Create and delete folders
- Search files by name
- Filter by media type (image, media, file)
- Per-field disk and directory override
- Path traversal protection and authentication guards
- Filename sanitization with collision handling
- Dark mode support
- Compatible with filament-forms-tinymce
