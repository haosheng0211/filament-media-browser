<?php

declare(strict_types=1);

namespace MrJin\FilamentMediaBrowser;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use MrJin\FilamentMediaBrowser\Livewire\MediaBrowserModal;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentMediaBrowserServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-media-browser';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        Livewire::component('filament-media-browser', MediaBrowserModal::class);

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => Blade::render('@livewire(\'filament-media-browser\')'),
        );
    }
}
