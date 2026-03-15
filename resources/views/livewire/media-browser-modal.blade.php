<div>
    @if($isOpen)
        <div
            class="fixed inset-0 flex items-center justify-center bg-black/50"
            style="z-index: 1500"
            x-data
            x-on:keydown.escape.window="$wire.closeBrowser()"
        >
            <div class="w-full max-w-5xl bg-white dark:bg-gray-900 rounded-xl shadow-xl ring-1 ring-gray-950/5 dark:ring-white/10 flex flex-col overflow-hidden" style="height: 85vh">

                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h2 class="fi-modal-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        {{ __('filament-media-browser::messages.media_browser') }}
                    </h2>
                    <div class="flex items-center gap-2">
                        @if($multiple)
                            @if(count($selectedFiles) > 0)
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('filament-media-browser::messages.selected_count', ['count' => count($selectedFiles)]) }}
                                </span>
                            @endif
                            <x-filament::button
                                color="primary"
                                size="sm"
                                wire:click="confirmSelection"
                                :disabled="count($selectedFiles) === 0"
                            >
                                {{ __('filament-media-browser::messages.done') }}
                            </x-filament::button>
                        @endif
                        <x-filament::icon-button
                            icon="heroicon-o-x-mark"
                            color="gray"
                            :label="__('filament-media-browser::messages.close')"
                            wire:click="closeBrowser"
                        />
                    </div>
                </div>

                {{-- Body: Dual Panel --}}
                <div class="flex flex-1 overflow-hidden">

                    {{-- Sidebar (Folder Tree) --}}
                    <nav class="w-56 shrink-0 overflow-y-auto bg-gray-50 dark:bg-gray-900 border-r border-gray-200 dark:border-gray-700 p-3">
                        <div x-data="{ expanded: true }">
                            {{-- Root folder --}}
                            <div class="flex items-center">
                                @if(!empty($folderTree))
                                    <button
                                        type="button"
                                        x-on:click.stop="expanded = !expanded"
                                        class="flex items-center justify-center w-5 h-5 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition duration-75 shrink-0"
                                    >
                                        <svg
                                            class="w-3 h-3 transition duration-75"
                                            x-bind:class="expanded ? 'rotate-90' : ''"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                @else
                                    <span class="w-5 shrink-0"></span>
                                @endif

                                <button
                                    type="button"
                                    wire:click="navigateToPath('{{ $directory }}')"
                                    x-on:click="if (!expanded) expanded = true"
                                    class="flex items-center gap-1.5 flex-1 min-w-0 px-1.5 py-1 rounded-lg text-sm transition duration-75
                                        {{ $currentPath === $directory
                                            ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-medium'
                                            : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800' }}"
                                >
                                    <svg x-show="expanded" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/>
                                    </svg>
                                    <svg x-show="!expanded" x-cloak class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                    </svg>
                                    <span class="truncate">{{ basename($directory) }}</span>
                                </button>
                            </div>

                            {{-- Nested folders --}}
                            @if(!empty($folderTree))
                                <div x-show="expanded" x-cloak class="ps-5 mt-0.5">
                                    @include('filament-media-browser::livewire.partials.folder-tree-items', ['items' => $folderTree])
                                </div>
                            @endif
                        </div>
                    </nav>

                    {{-- Main Panel --}}
                    <div class="flex-1 flex flex-col overflow-hidden">

                        {{-- Toolbar --}}
                        <div class="flex items-center gap-3 border-b border-gray-200 dark:border-gray-700 px-4 py-3">
                            <div class="flex-1">
                                <x-filament::input.wrapper>
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="search"
                                        placeholder="{{ __('filament-media-browser::messages.search') }}"
                                        class="block w-full border-none bg-transparent px-3 py-1.5 text-base text-gray-950 outline-none transition duration-75 placeholder:text-gray-400 focus:ring-0 dark:text-white dark:placeholder:text-gray-500 sm:text-sm sm:leading-6"
                                    />
                                </x-filament::input.wrapper>
                            </div>

                            {{-- New Folder --}}
                            <div x-data="{ showInput: false }" class="flex items-center gap-2">
                                <template x-if="showInput">
                                    <div class="flex items-center gap-2">
                                        <x-filament::input.wrapper>
                                            <input
                                                type="text"
                                                wire:model="newFolderName"
                                                placeholder="{{ __('filament-media-browser::messages.folder_name') }}"
                                                x-on:keydown.enter="$wire.createFolder(); showInput = false"
                                                x-on:keydown.escape="showInput = false"
                                                class="block w-32 border-none bg-transparent px-3 py-1.5 text-base text-gray-950 outline-none transition duration-75 placeholder:text-gray-400 focus:ring-0 dark:text-white dark:placeholder:text-gray-500 sm:text-sm sm:leading-6"
                                            />
                                        </x-filament::input.wrapper>
                                        <x-filament::icon-button
                                            icon="heroicon-o-check"
                                            color="success"
                                            size="sm"
                                            :label="__('filament-media-browser::messages.create')"
                                            wire:click="createFolder"
                                            x-on:click="showInput = false"
                                        />
                                    </div>
                                </template>
                                <x-filament::icon-button
                                    icon="heroicon-o-folder-plus"
                                    color="gray"
                                    :label="__('filament-media-browser::messages.new_folder')"
                                    x-on:click="showInput = !showInput"
                                />
                            </div>

                            {{-- Upload --}}
                            <div x-data="{ uploading: false }" x-on:livewire-upload-start="uploading = true" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-error="uploading = false">
                                <x-filament::button
                                    icon="heroicon-o-arrow-up-tray"
                                    size="sm"
                                    x-on:click="$refs.fileUpload.click()"
                                    x-bind:disabled="uploading"
                                >
                                    <span x-show="!uploading">{{ __('filament-media-browser::messages.upload') }}</span>
                                    <span x-show="uploading" x-cloak class="flex items-center gap-1">
                                        <x-filament::loading-indicator class="w-4 h-4" />
                                        {{ __('filament-media-browser::messages.uploading') }}
                                    </span>
                                </x-filament::button>
                                <input
                                    type="file"
                                    multiple
                                    x-ref="fileUpload"
                                    wire:model="upload"
                                    accept="{{ implode(',', config('filament-media-browser.accepted_file_types', ['image/*', 'video/*', 'audio/*'])) }}"
                                    class="hidden"
                                />
                            </div>
                        </div>

                        {{-- Content Grid --}}
                        <div class="flex-1 overflow-y-auto p-4">
                            @php
                                $has_files = !empty($contents['files']);
                            @endphp

                            {{-- Files --}}
                            @if($has_files)
                                <div class="grid grid-cols-4 gap-3 sm:grid-cols-5 lg:grid-cols-6">
                                    @foreach($contents['files'] as $file)
                                        @php
                                            $is_selected = in_array($file['path'], $selectedFiles, true);
                                        @endphp
                                        <div
                                            wire:key="file-{{ md5($file['path']) }}"
                                            class="group relative overflow-hidden rounded-lg border-2 cursor-pointer transition duration-75 {{ $is_selected ? 'border-primary-500 ring-2 ring-primary-500/30' : 'border-transparent hover:border-primary-500' }}"
                                            wire:click="selectFile('{{ $file['path'] }}')"
                                        >
                                            <div class="bg-gray-100 dark:bg-gray-700" style="aspect-ratio: 1/1">
                                            @if(str_starts_with($file['mime'], 'image/'))
                                                <img
                                                    src="{{ $file['url'] }}"
                                                    alt="{{ $file['name'] }}"
                                                    class="w-full h-full object-cover"
                                                    loading="lazy"
                                                />
                                            @elseif(str_starts_with($file['mime'], 'video/'))
                                                <div class="w-full h-full flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                                                    <svg class="w-8 h-8 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                                    </svg>
                                                    <span class="text-xs truncate w-full text-center px-1">{{ $file['name'] }}</span>
                                                </div>
                                            @elseif(str_starts_with($file['mime'], 'audio/'))
                                                <div class="w-full h-full flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                                                    <svg class="w-8 h-8 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                                                    </svg>
                                                    <span class="text-xs truncate w-full text-center px-1">{{ $file['name'] }}</span>
                                                </div>
                                            @else
                                                <div class="w-full h-full flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                                                    <svg class="w-8 h-8 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                    </svg>
                                                    <span class="text-xs truncate w-full text-center px-1">{{ $file['name'] }}</span>
                                                </div>
                                            @endif

                                            {{-- Selected checkmark --}}
                                            @if($is_selected)
                                                <div class="absolute top-1.5 left-1.5">
                                                    <div class="flex items-center justify-center w-5 h-5 rounded-full bg-primary-500 text-white shadow">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                                        </svg>
                                                    </div>
                                                </div>
                                            @endif

                                            {{-- Delete button --}}
                                            <div class="absolute top-1 right-1 hidden group-hover:block">
                                                <x-filament::icon-button
                                                    icon="heroicon-o-trash"
                                                    color="danger"
                                                    size="sm"
                                                    wire:click.stop="deleteFile('{{ $file['path'] }}')"
                                                    wire:confirm="{{ __('filament-media-browser::messages.confirm_delete_file') }}"
                                                    :label="__('filament-media-browser::messages.delete')"
                                                />
                                            </div>
                                            </div>

                                            {{-- Filename --}}
                                            <div class="px-1.5 py-1">
                                                <p class="text-xs text-gray-600 dark:text-gray-300 truncate text-center" title="{{ $file['name'] }}">{{ $file['name'] }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Empty state --}}
                            @if(!$has_files)
                                <div class="flex flex-col items-center justify-center py-12">
                                    <div class="mb-4 rounded-full bg-gray-100 dark:bg-gray-500/20 p-3">
                                        <svg class="w-6 h-6 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('filament-media-browser::messages.no_files_found') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Footer (Status Bar) --}}
                <div class="flex items-center px-4 py-2 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400">
                    @if($multiple && count($selectedFiles) > 0)
                        {{ __('filament-media-browser::messages.selected_count', ['count' => count($selectedFiles)]) }}
                    @elseif($selectedFileInfo)
                        {{ $selectedFileInfo['name'] }} · {{ $selectedFileInfo['size'] }}
                        @if($selectedFileInfo['dimensions'])
                            · {{ $selectedFileInfo['dimensions'] }}
                        @endif
                    @else
                        {{ __('filament-media-browser::messages.file_count', ['count' => $directoryStats['files']]) }},
                        {{ __('filament-media-browser::messages.folder_count', ['count' => $directoryStats['folders']]) }}
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
