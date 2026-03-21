<?php

declare(strict_types=1);

namespace MrJin\FilamentMediaBrowser\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class MediaBrowserModal extends Component
{
    use WithFileUploads;

    public bool $isOpen = false;

    public string $statePath = '';

    public string $mediaType = 'image';

    public string $search = '';

    public string $currentPath = '';

    public string $newFolderName = '';

    public bool $multiple = false;

    /** @var array<string> */
    public array $selectedFiles = [];

    /** @var array<TemporaryUploadedFile>|null */
    public mixed $upload = null;

    /**
     * Disk name resolved on open.
     */
    public string $disk = '';

    /**
     * Root directory resolved on open.
     */
    public string $directory = '';

    /**
     * Whether to output Storage::url() or the raw path.
     */
    public bool $storeAsUrl = true;

    /**
     * Prevent direct frontend manipulation of the disk property.
     */
    public function updatingDisk(string $value): void
    {
        throw new \RuntimeException('Direct modification of disk is not allowed.');
    }

    /**
     * Prevent direct frontend manipulation of the directory property.
     */
    public function updatingDirectory(string $value): void
    {
        throw new \RuntimeException('Direct modification of directory is not allowed.');
    }

    #[On('open-media-browser')]
    public function openBrowser(
        string $statePath = '',
        string $mediaType = 'image',
        bool $multiple = false,
        ?string $disk = null,
        ?string $directory = null,
        bool $storeAsUrl = true,
    ): void {
        $this->ensureAuthenticated();

        $this->statePath = $statePath;
        $this->mediaType = in_array($mediaType, ['image', 'media', 'file'], true) ? $mediaType : 'image';
        $this->multiple = $multiple;
        $this->disk = $this->resolveDisk($disk);
        $this->directory = $this->resolveDirectory($directory);
        $this->storeAsUrl = $storeAsUrl;
        $this->currentPath = $this->directory;
        $this->search = '';
        $this->newFolderName = '';
        $this->selectedFiles = [];
        $this->isOpen = true;
    }

    /**
     * @return array{folders: array<string>, files: array<array{name: string, path: string, url: string, mime: string, size: int, lastModified: int}>}
     */
    public function getContents(): array
    {
        if (! $this->isOpen || $this->disk === '') {
            return ['folders' => [], 'files' => []];
        }

        $storage = Storage::disk($this->disk);

        // Folders
        $folders = collect($storage->directories($this->currentPath))
            ->map(fn (string $path): string => basename($path))
            ->sort()
            ->values()
            ->all();

        // Files
        $raw_files = $storage->files($this->currentPath);

        $files = collect($raw_files)
            ->map(function (string $path) use ($storage): ?array {
                $mime = $storage->mimeType($path);

                if ($mime === false) {
                    return null;
                }

                // Filter by media type
                if (! $this->matchesMediaType($mime)) {
                    return null;
                }

                $name = basename($path);

                // Search filter
                if ($this->search !== '' && ! str_contains(strtolower($name), strtolower($this->search))) {
                    return null;
                }

                return [
                    'name' => $name,
                    'path' => $path,
                    'url' => $storage->url($path),
                    'mime' => $mime,
                    'size' => $storage->size($path),
                    'lastModified' => $storage->lastModified($path),
                ];
            })
            ->filter()
            ->sortByDesc('lastModified')
            ->values()
            ->all();

        return ['folders' => $folders, 'files' => $files];
    }

    /**
     * Build a nested folder tree from the root directory.
     *
     * @return array<array{name: string, path: string, children: array}>
     */
    public function getFolderTree(): array
    {
        if (! $this->isOpen || $this->disk === '') {
            return [];
        }

        return $this->buildFolderTree($this->directory);
    }

    /**
     * @return array<array{name: string, path: string, children: array}>
     */
    protected function buildFolderTree(string $path): array
    {
        $storage = Storage::disk($this->disk);

        return collect($storage->directories($path))
            ->map(fn (string $dir): array => [
                'name' => basename($dir),
                'path' => $dir,
                'children' => $this->buildFolderTree($dir),
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Get stats for the current directory.
     *
     * @return array{files: int, folders: int}
     */
    public function getDirectoryStats(array $contents): array
    {
        return [
            'files' => count($contents['files']),
            'folders' => count($contents['folders']),
        ];
    }

    /**
     * Get metadata for the last selected file (single select or last toggled in multi).
     *
     * @return array{name: string, size: string, dimensions: string|null}|null
     */
    public function getSelectedFileInfo(): ?array
    {
        if ($this->selectedFiles === [] || $this->disk === '') {
            return null;
        }

        $path = end($this->selectedFiles);
        $storage = Storage::disk($this->disk);

        if (! $storage->exists($path)) {
            return null;
        }

        $size = $storage->size($path);
        $mime = $storage->mimeType($path);

        $info = [
            'name' => basename($path),
            'size' => $this->formatFileSize($size),
            'dimensions' => null,
        ];

        // Try to get image dimensions
        if (is_string($mime) && str_starts_with($mime, 'image/')) {
            try {
                $full_path = $storage->path($path);
                $image_size = @getimagesize($full_path);
                if ($image_size !== false) {
                    $info['dimensions'] = $image_size[0].'×'.$image_size[1];
                }
            } catch (\Throwable) {
                // Ignore errors (e.g. remote disks without path support)
            }
        }

        return $info;
    }

    protected function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }

    /**
     * Navigate to an absolute path (used by sidebar folder tree).
     */
    public function navigateToPath(string $path): void
    {
        // Prevent path traversal
        if (str_contains($path, '..')) {
            return;
        }

        // Ensure path is within root directory
        if (! str_starts_with($path, $this->directory)) {
            return;
        }

        $this->currentPath = $path;
    }

    public function navigateToFolder(string $folder): void
    {
        // Reject folder names containing path traversal characters
        if (str_contains($folder, '..') || str_contains($folder, '/')) {
            return;
        }

        $target = $this->currentPath.'/'.$folder;

        $this->currentPath = $target;
    }

    public function navigateUp(): void
    {
        if ($this->currentPath === $this->directory) {
            return;
        }

        $this->currentPath = dirname($this->currentPath);

        // Safety: never go above root directory
        if (! str_starts_with($this->currentPath, $this->directory)) {
            $this->currentPath = $this->directory;
        }
    }

    public function navigateToBreadcrumb(int $index): void
    {
        $parts = $this->getBreadcrumbs();
        $target = implode('/', array_slice($parts, 0, $index + 1));

        if (! str_starts_with($target, $this->directory)) {
            return;
        }

        $this->currentPath = $target;
    }

    /**
     * @return array<string>
     */
    public function getBreadcrumbs(): array
    {
        return explode('/', $this->currentPath);
    }

    public function createFolder(): void
    {
        $this->ensureAuthenticated();

        $name = Str::slug($this->newFolderName);

        if ($name === '') {
            return;
        }

        $path = $this->currentPath.'/'.$name;

        if (! str_starts_with($path, $this->directory)) {
            return;
        }

        Storage::disk($this->disk)->makeDirectory($path);

        $this->newFolderName = '';
    }

    public function selectFile(string $path): void
    {
        $this->ensureAuthenticated();

        if (! $this->multiple) {
            $this->dispatchFileSelected($path);
            $this->isOpen = false;

            return;
        }

        $this->toggleFile($path);
    }

    public function toggleFile(string $path): void
    {
        if (in_array($path, $this->selectedFiles, true)) {
            $this->selectedFiles = array_values(array_filter(
                $this->selectedFiles,
                fn (string $p): bool => $p !== $path,
            ));
        } else {
            $this->selectedFiles[] = $path;
        }
    }

    public function confirmSelection(): void
    {
        $this->ensureAuthenticated();

        foreach ($this->selectedFiles as $path) {
            $this->dispatchFileSelected($path);
        }

        $this->selectedFiles = [];
        $this->isOpen = false;
    }

    protected function dispatchFileSelected(string $path): void
    {
        // Prevent path traversal
        if (! str_starts_with($path, $this->directory)) {
            return;
        }

        $storage = Storage::disk($this->disk);

        if (! $storage->exists($path)) {
            return;
        }

        $value = $this->storeAsUrl ? $storage->url($path) : $path;
        $basename = basename($path);
        $name = pathinfo($path, PATHINFO_FILENAME);
        $extension = strtoupper(pathinfo($path, PATHINFO_EXTENSION) ?: '');
        $size = $storage->size($path);
        $mime = $storage->mimeType($path) ?: '';

        $this->dispatch(
            'media-selected',
            statePath: $this->statePath,
            url: $value,
            alt: $name,
            title: $name,
            filename: $basename,
            extension: $extension,
            size: $size,
            mime: $mime,
        );
    }

    /**
     * Auto-upload when files are selected.
     */
    public function updatedUpload(): void
    {
        $this->uploadFile();
    }

    public function uploadFile(): void
    {
        $this->ensureAuthenticated();

        $accepted_types = config('filament-media-browser.accepted_file_types', ['image/*', 'video/*', 'audio/*']);
        $max_file_size = config('filament-media-browser.max_file_size', 10240);
        $mime_rules = $this->buildMimeValidationRules($accepted_types);

        $this->validate([
            'upload' => ['required', 'array'],
            'upload.*' => [
                'file',
                'max:'.$max_file_size,
                'mimetypes:'.implode(',', $mime_rules),
            ],
        ]);

        $storage = Storage::disk($this->disk);

        foreach ($this->upload as $file) {
            $safe_filename = $this->sanitizeFilename($file->getClientOriginalName(), preserveName: true);

            $target_path = $this->currentPath.'/'.$safe_filename;

            if ($storage->exists($target_path)) {
                $dot_pos = mb_strrpos($safe_filename, '.');
                $basename = $dot_pos !== false ? mb_substr($safe_filename, 0, $dot_pos) : $safe_filename;
                $ext = $dot_pos !== false ? mb_substr($safe_filename, $dot_pos + 1) : 'bin';
                $safe_filename = $basename.'-'.Str::random(6).'.'.$ext;
            }

            $file->storeAs($this->currentPath, $safe_filename, $this->disk);
        }

        $this->reset('upload');
    }

    public function deleteFile(string $path): void
    {
        $this->ensureAuthenticated();

        // Prevent path traversal
        if (! str_starts_with($path, $this->directory)) {
            return;
        }

        Storage::disk($this->disk)->delete($path);
    }

    public function deleteFolder(string $folder): void
    {
        $this->ensureAuthenticated();

        if (str_contains($folder, '..') || str_contains($folder, '/')) {
            return;
        }

        $path = $this->currentPath.'/'.$folder;

        if (! str_starts_with($path, $this->directory)) {
            return;
        }

        Storage::disk($this->disk)->deleteDirectory($path);
    }

    /**
     * Delete a folder by its full path (used by sidebar tree).
     */
    public function deleteFolderByPath(string $path): void
    {
        $this->ensureAuthenticated();

        if (str_contains($path, '..')) {
            return;
        }

        if (! str_starts_with($path, $this->directory)) {
            return;
        }

        // Don't allow deleting the root directory
        if ($path === $this->directory) {
            return;
        }

        Storage::disk($this->disk)->deleteDirectory($path);

        // If the deleted folder is the current path or an ancestor, navigate to its parent
        if ($this->currentPath === $path || str_starts_with($this->currentPath, $path.'/')) {
            $this->currentPath = dirname($path);
            if (! str_starts_with($this->currentPath, $this->directory)) {
                $this->currentPath = $this->directory;
            }
        }
    }

    public function closeBrowser(): void
    {
        $this->isOpen = false;
    }

    public function render(): View
    {
        $contents = $this->getContents();
        $stats = $this->getDirectoryStats($contents);

        return view('filament-media-browser::livewire.media-browser-modal', [
            'contents' => $contents,
            'breadcrumbs' => $this->getBreadcrumbs(),
            'folderTree' => $this->getFolderTree(),
            'directoryStats' => $stats,
            'selectedFileInfo' => $this->getSelectedFileInfo(),
        ]);
    }

    // --- Helpers ---

    /**
     * Validate and resolve the disk name.
     * Only allows disks that are actually configured in filesystems.php.
     */
    protected function resolveDisk(?string $disk): string
    {
        $resolved = $disk ?? config('filament-media-browser.disk', 'public');

        // Only allow disks that exist in the Laravel filesystem config
        if (config("filesystems.disks.{$resolved}") === null) {
            return config('filament-media-browser.disk', 'public');
        }

        return $resolved;
    }

    /**
     * Validate and resolve the root directory.
     * Prevents empty or traversal-based directory values.
     */
    protected function resolveDirectory(?string $directory): string
    {
        $resolved = $directory ?? config('filament-media-browser.directory', 'media');

        // Reject empty, traversal, or absolute paths
        if ($resolved === '' || str_contains($resolved, '..') || str_starts_with($resolved, '/')) {
            return config('filament-media-browser.directory', 'media');
        }

        return $resolved;
    }

    protected function ensureAuthenticated(): void
    {
        if (! auth()->check()) {
            abort(403, 'Unauthenticated.');
        }
    }

    protected function matchesMediaType(string $mime): bool
    {
        return match ($this->mediaType) {
            'image' => str_starts_with($mime, 'image/'),
            'media' => str_starts_with($mime, 'video/') || str_starts_with($mime, 'audio/'),
            'file' => true,
            default => true,
        };
    }

    protected function sanitizeFilename(string $filename, bool $preserveName = false): string
    {
        // Use mb-safe parsing instead of pathinfo() which breaks on non-ASCII
        $dot_pos = mb_strrpos($filename, '.');
        if ($dot_pos !== false && $dot_pos > 0) {
            $name = mb_substr($filename, 0, $dot_pos);
            $extension = mb_substr($filename, $dot_pos + 1);
        } else {
            $name = $filename;
            $extension = '';
        }

        if ($preserveName) {
            // Keep original name but strip path traversal and null bytes
            $safe_name = str_replace(['..', "\0", '/', '\\'], '', $name);
            $safe_name = trim($safe_name);
        } else {
            $safe_name = Str::slug($name);
        }

        if ($safe_name === '') {
            $safe_name = 'upload-'.Str::random(8);
        }

        $safe_extension = preg_replace('/[^a-zA-Z0-9]/', '', $extension);

        if ($safe_extension === '' || $safe_extension === null) {
            $safe_extension = 'bin';
        }

        return $safe_name.'.'.$safe_extension;
    }

    /**
     * @param  array<string>  $acceptedFileTypes
     * @return array<string>
     */
    protected function buildMimeValidationRules(array $acceptedFileTypes): array
    {
        $mimes = [];

        foreach ($acceptedFileTypes as $type) {
            if ($type === 'image/*') {
                array_push($mimes, 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/svg');
            } elseif ($type === 'image/svg+xml') {
                array_push($mimes, 'image/svg+xml', 'image/svg');
            } elseif ($type === 'video/*') {
                array_push($mimes, 'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime');
            } elseif ($type === 'audio/*') {
                array_push($mimes, 'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/webm');
            } else {
                $mimes[] = $type;
            }
        }

        return $mimes;
    }
}
