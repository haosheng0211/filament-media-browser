# Changelog

All notable changes to `filament-media-browser` will be documented in this file.

## v1.3.1 - 2026-03-26

### Fixed
- Removed info bar from multiple mode cards to match single mode — both now display pure square cards with hover-only action buttons

## v1.3.0 - 2026-03-23

### Added
- `gridColumns(int)` method on MediaPicker to customize the number of columns in the preview grid (default: 5, fewer columns = larger cards)

## v1.2.1 - 2026-03-22

### Changed
- Unified single and multiple mode MediaPicker to use the same square (1:1) card layout and grid system for consistent appearance

## v1.2.0 - 2026-03-21

### Added
- Global `store_as_url` config option — set default output format for all MediaPicker fields without per-field override
- Per-field `storeAsUrl()` / `storePath()` still takes precedence over config

### Fixed
- SVG files (`image/svg+xml`, `image/svg`) now included in `image/*` MIME type expansion, fixing upload validation failure for SVG files
- MediaPicker preview now works correctly when `store_as_url` is `false` — relative paths are resolved to proper URLs via `Storage::url()` for display

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
