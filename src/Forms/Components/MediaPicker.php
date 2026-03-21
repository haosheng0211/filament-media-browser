<?php

declare(strict_types=1);

namespace MrJin\FilamentMediaBrowser\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

class MediaPicker extends Field
{
    protected string $view = 'filament-media-browser::forms.components.media-picker';

    protected string|Closure $mediaType = 'image';

    protected string|Closure|null $mediaDisk = null;

    protected string|Closure|null $mediaDirectory = null;

    protected bool|Closure $isMultiple = false;

    protected int|Closure|null $maxItems = null;

    protected int|Closure|null $minItems = null;

    protected bool|Closure $isReorderable = true;

    protected bool|Closure|null $shouldStoreAsUrl = null;

    public function mediaType(string|Closure $type): static
    {
        $this->mediaType = $type;

        return $this;
    }

    public function mediaDisk(string|Closure|null $disk): static
    {
        $this->mediaDisk = $disk;

        return $this;
    }

    public function mediaDirectory(string|Closure|null $directory): static
    {
        $this->mediaDirectory = $directory;

        return $this;
    }

    public function multiple(bool|Closure $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function maxItems(int|Closure|null $count): static
    {
        $this->maxItems = $count;

        return $this;
    }

    public function minItems(int|Closure|null $count): static
    {
        $this->minItems = $count;

        return $this;
    }

    public function reorderable(bool|Closure $condition = true): static
    {
        $this->isReorderable = $condition;

        return $this;
    }

    public function storeAsUrl(bool|Closure $condition = true): static
    {
        $this->shouldStoreAsUrl = $condition;

        return $this;
    }

    public function storePath(bool|Closure $condition = true): static
    {
        $this->shouldStoreAsUrl = fn (): bool => ! $this->evaluate($condition);

        return $this;
    }

    public function getMediaType(): string
    {
        return $this->evaluate($this->mediaType);
    }

    public function getMediaDisk(): ?string
    {
        return $this->evaluate($this->mediaDisk);
    }

    public function getMediaDirectory(): ?string
    {
        return $this->evaluate($this->mediaDirectory);
    }

    public function isMultiple(): bool
    {
        return $this->evaluate($this->isMultiple);
    }

    public function getMaxItems(): ?int
    {
        return $this->evaluate($this->maxItems);
    }

    public function getMinItems(): ?int
    {
        return $this->evaluate($this->minItems);
    }

    public function isReorderable(): bool
    {
        return $this->evaluate($this->isReorderable);
    }

    public function shouldStoreAsUrl(): bool
    {
        $value = $this->evaluate($this->shouldStoreAsUrl);

        if ($value === null) {
            return (bool) config('filament-media-browser.store_as_url', true);
        }

        return $value;
    }

    public function getDispatchParams(): array
    {
        $params = [
            'statePath' => $this->getStatePath(),
            'mediaType' => $this->getMediaType(),
            'multiple' => $this->isMultiple(),
            'storeAsUrl' => $this->shouldStoreAsUrl(),
        ];

        $disk = $this->getMediaDisk();
        $directory = $this->getMediaDirectory();

        if ($disk !== null) {
            $params['disk'] = $disk;
        }

        if ($directory !== null) {
            $params['directory'] = $directory;
        }

        return $params;
    }
}
