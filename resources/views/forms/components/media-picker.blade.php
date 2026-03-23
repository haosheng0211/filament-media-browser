@php
    $statePath = $getStatePath();
    $dispatchParams = $getDispatchParams();
    $isDisabled = $isDisabled();
    $isMultiple = $isMultiple();
    $maxItems = $getMaxItems();
    $isReorderable = $isReorderable();
    $mediaType = $getMediaType();
    $previewBaseUrl = $getPreviewBaseUrl();
    $gridColumns = $getGridColumns();
    $gridStyle = "grid-template-columns: repeat({$gridColumns}, minmax(0, 1fr))";
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            multiple: @js($isMultiple),
            maxItems: @js($maxItems),
            previewBaseUrl: @js($previewBaseUrl),
            dragging: null,
            dragOver: null,

            {{-- File metadata store: { url: { filename, extension, size, mime } } --}}
            fileMeta: {},

            previewUrl(value) {
                if (!value) return ''
                if (!this.previewBaseUrl) return value
                if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('/')) return value
                return this.previewBaseUrl + value
            },

            open() {
                if (this.multiple && this.maxItems && this.items.length >= this.maxItems) return
                Livewire.dispatch('open-media-browser', @js($dispatchParams))
            },

            get items() {
                if (!this.multiple) return []
                return Array.isArray(this.state) ? this.state : []
            },

            get canAdd() {
                if (!this.multiple) return !this.state
                return !this.maxItems || this.items.length < this.maxItems
            },

            getMeta(url) {
                if (this.fileMeta[url]) return this.fileMeta[url]
                const parts = url.split('/')
                const filename = parts.pop() || ''
                const dotIndex = filename.lastIndexOf('.')
                const extension = dotIndex > 0 ? filename.substring(dotIndex + 1).toUpperCase() : ''
                return { filename, extension, size: null, mime: '' }
            },

            formatSize(bytes) {
                if (bytes === null || bytes === undefined) return ''
                if (bytes < 1024) return bytes + ' B'
                if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
                if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB'
                return (bytes / 1073741824).toFixed(1) + ' GB'
            },

            getFileTypeStyle(url) {
                const meta = this.getMeta(url)
                const ext = (meta.extension || '').toLowerCase()
                const mime = (meta.mime || '').toLowerCase()

                if (mime.startsWith('image/') || ['jpg','jpeg','png','gif','webp','svg','ico','bmp','tiff'].includes(ext)) {
                    return 'image'
                }
                if (ext === 'pdf') {
                    return 'pdf'
                }
                if (mime.startsWith('video/') || ['mp4','mov','avi','webm','mkv','flv','wmv'].includes(ext)) {
                    return 'video'
                }
                if (mime.startsWith('audio/') || ['mp3','wav','ogg','flac','aac','wma'].includes(ext)) {
                    return 'audio'
                }
                if (['zip','rar','7z','tar','gz','bz2'].includes(ext)) {
                    return 'archive'
                }
                if (['doc','docx','txt','rtf','odt'].includes(ext)) {
                    return 'document'
                }
                if (['xls','xlsx','csv','ods'].includes(ext)) {
                    return 'spreadsheet'
                }
                return 'default'
            },

            isImage(url) {
                return this.getFileTypeStyle(url) === 'image'
            },

            init() {
                if (this.multiple && !Array.isArray(this.state)) {
                    this.state = []
                }
                this._cleanup = Livewire.on('media-selected', (params) => {
                    if (params.statePath !== '{{ $statePath }}') return

                    // Store metadata
                    if (params.url) {
                        this.fileMeta[params.url] = {
                            filename: params.filename || params.url.split('/').pop() || '',
                            extension: params.extension || '',
                            size: params.size ?? null,
                            mime: params.mime || '',
                        }
                    }

                    if (this.multiple) {
                        let current = Array.isArray(this.state) ? [...this.state] : []
                        if (!current.includes(params.url)) {
                            if (!this.maxItems || current.length < this.maxItems) {
                                current.push(params.url)
                                this.state = current
                            }
                        }
                    } else {
                        this.state = params.url
                    }
                })
            },
            remove(index) {
                if (this.multiple) {
                    let current = [...this.items]
                    const removed = current.splice(index, 1)
                    if (removed[0]) delete this.fileMeta[removed[0]]
                    this.state = current
                } else {
                    if (this.state) delete this.fileMeta[this.state]
                    this.state = null
                }
            },
            clear() {
                this.fileMeta = {}
                this.state = this.multiple ? [] : null
            },
            onDragStart(index) {
                this.dragging = index
            },
            onDragOver(index) {
                this.dragOver = index
            },
            onDragEnd() {
                if (this.dragging !== null && this.dragOver !== null && this.dragging !== this.dragOver) {
                    let current = [...this.items]
                    let [moved] = current.splice(this.dragging, 1)
                    current.splice(this.dragOver, 0, moved)
                    this.state = current
                }
                this.dragging = null
                this.dragOver = null
            },
            destroy() {
                if (this._cleanup) {
                    this._cleanup()
                    this._cleanup = null
                }
            },
        }"
        class="w-full"
    >
        @if($isMultiple)
            {{-- Multiple mode --}}
            <div class="flex flex-col gap-3">
                {{-- Item grid --}}
                <template x-if="items.length > 0">
                    <div class="grid gap-3" style="{{ $gridStyle }}">
                        <template x-for="(url, index) in items" x-bind:key="url + '-' + index">
                            <div
                                class="group overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 transition"
                                x-bind:class="{
                                    'ring-2 ring-primary-500': dragOver === index && dragging !== index,
                                    'opacity-50': dragging === index,
                                }"
                                @if($isReorderable && !$isDisabled)
                                    draggable="true"
                                    x-on:dragstart="onDragStart(index)"
                                    x-on:dragover.prevent="onDragOver(index)"
                                    x-on:dragend="onDragEnd()"
                                @endif
                            >
                                {{-- Preview area (1:1) --}}
                                <div class="relative" style="aspect-ratio: 1/1">
                                    {{-- Image preview --}}
                                    <template x-if="isImage(url)">
                                        <img
                                            x-bind:src="previewUrl(url)"
                                            alt=""
                                            class="w-full h-full object-cover"
                                            loading="lazy"
                                        />
                                    </template>

                                    {{-- Non-image file type preview --}}
                                    <template x-if="!isImage(url)">
                                        <div
                                            class="w-full h-full flex items-center justify-center"
                                            x-bind:class="{
                                                'bg-red-50 dark:bg-red-950': getFileTypeStyle(url) === 'pdf',
                                                'bg-purple-50 dark:bg-purple-950': getFileTypeStyle(url) === 'video',
                                                'bg-amber-50 dark:bg-amber-950': getFileTypeStyle(url) === 'audio',
                                                'bg-gray-100 dark:bg-gray-800': getFileTypeStyle(url) === 'archive' || getFileTypeStyle(url) === 'default',
                                                'bg-blue-50 dark:bg-blue-950': getFileTypeStyle(url) === 'document',
                                                'bg-green-50 dark:bg-green-950': getFileTypeStyle(url) === 'spreadsheet',
                                            }"
                                        >
                                            {{-- PDF --}}
                                            <template x-if="getFileTypeStyle(url) === 'pdf'">
                                                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                                </svg>
                                            </template>
                                            {{-- Video --}}
                                            <template x-if="getFileTypeStyle(url) === 'video'">
                                                <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-1.5A1.125 1.125 0 0118 18.375M20.625 4.5H3.375m17.25 0c.621 0 1.125.504 1.125 1.125M20.625 4.5h-1.5C18.504 4.5 18 5.004 18 5.625m3.75 0v1.5c0 .621-.504 1.125-1.125 1.125M3.375 4.5c-.621 0-1.125.504-1.125 1.125M3.375 4.5h1.5C5.496 4.5 6 5.004 6 5.625m-3.75 0v1.5c0 .621.504 1.125 1.125 1.125m0 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m1.5-3.75C5.496 8.25 6 7.746 6 7.125v-1.5M4.875 8.25C5.496 8.25 6 8.754 6 9.375v1.5m0-5.25v5.25m0-5.25C6 5.004 6.504 4.5 7.125 4.5h9.75c.621 0 1.125.504 1.125 1.125m1.125 2.625h1.5m-1.5 0A1.125 1.125 0 0118 7.125v-1.5m1.125 2.625c-.621 0-1.125.504-1.125 1.125v1.5m2.625-2.625c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125M18 5.625v5.25M7.125 12h9.75m-9.75 0A1.125 1.125 0 016 10.875M7.125 12C6.504 12 6 12.504 6 13.125m0-2.25C6 11.496 5.496 12 4.875 12M18 10.875c0 .621-.504 1.125-1.125 1.125M18 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m-12 5.25v-5.25m0 5.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125m-12 0v-1.5c0-.621-.504-1.125-1.125-1.125M18 18.375v-5.25m0 5.25v-1.5c0-.621.504-1.125 1.125-1.125M18 13.125v1.5c0 .621.504 1.125 1.125 1.125M18 13.125c0-.621.504-1.125 1.125-1.125M6 13.125v1.5c0 .621-.504 1.125-1.125 1.125M6 13.125C6 12.504 5.496 12 4.875 12m-1.5 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m1.5-3.75c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M4.875 15.75h1.5m-1.5 0c-.621 0-1.125-.504-1.125-1.125v-1.5"/>
                                                </svg>
                                            </template>
                                            {{-- Audio --}}
                                            <template x-if="getFileTypeStyle(url) === 'audio'">
                                                <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 01-.99-3.467l2.31-.66A2.25 2.25 0 009 15.553z"/>
                                                </svg>
                                            </template>
                                            {{-- Document --}}
                                            <template x-if="getFileTypeStyle(url) === 'document'">
                                                <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                                </svg>
                                            </template>
                                            {{-- Spreadsheet --}}
                                            <template x-if="getFileTypeStyle(url) === 'spreadsheet'">
                                                <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125-.504-1.125-1.125M12 15.75h7.5m-7.5 0c-.621 0-1.125-.504-1.125-1.125M20.625 4.5H3.375m17.25 0c.621 0 1.125.504 1.125 1.125M20.625 4.5h-7.5C12.504 4.5 12 5.004 12 5.625m8.625-1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125-.504-1.125-1.125M12 8.25h7.5m-7.5 0c-.621 0-1.125-.504-1.125-1.125M3.375 8.25c.621 0 1.125-.504 1.125-1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m0 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M12 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125-.504 1.125-1.125m-1.125 1.125c.621 0 1.125.504 1.125 1.125m0-2.25V8.25m0 4.5v1.5c0 .621-.504 1.125-1.125 1.125"/>
                                                </svg>
                                            </template>
                                            {{-- Archive --}}
                                            <template x-if="getFileTypeStyle(url) === 'archive'">
                                                <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                                                </svg>
                                            </template>
                                            {{-- Default --}}
                                            <template x-if="getFileTypeStyle(url) === 'default'">
                                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                                </svg>
                                            </template>
                                        </div>
                                    </template>

                                    {{-- Delete button (hover) --}}
                                    @unless($isDisabled)
                                        <div class="absolute top-1 right-1 hidden group-hover:block">
                                            <x-filament::icon-button
                                                icon="heroicon-o-x-mark"
                                                color="danger"
                                                size="sm"
                                                :label="__('filament-media-browser::messages.remove')"
                                                x-on:click.stop="remove(index)"
                                                class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm"
                                            />
                                        </div>
                                        @if($isReorderable)
                                            <div class="absolute top-1 left-1 hidden group-hover:block cursor-grab">
                                                <x-filament::icon-button
                                                    icon="heroicon-o-bars-2"
                                                    color="gray"
                                                    size="sm"
                                                    :label="__('filament-media-browser::messages.reorder') ?? 'Reorder'"
                                                    class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm pointer-events-none"
                                                />
                                            </div>
                                        @endif
                                    @endunless
                                </div>

                                {{-- Info bar --}}
                                <div class="px-2 py-1.5">
                                    <p class="text-xs text-gray-600 dark:text-gray-300 truncate" x-text="getMeta(url).filename"></p>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span
                                            class="inline-flex items-center rounded-md bg-gray-50 dark:bg-gray-800 px-1.5 py-0.5 text-[10px] font-medium text-gray-600 dark:text-gray-400 ring-1 ring-inset ring-gray-500/10 dark:ring-gray-400/20"
                                            x-text="getMeta(url).extension"
                                        ></span>
                                        <template x-if="getMeta(url).size !== null">
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500" x-text="formatSize(getMeta(url).size)"></span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Add button (inline) --}}
                        @unless($isDisabled)
                            <template x-if="canAdd">
                                <button
                                    type="button"
                                    x-on:click="open()"
                                    class="flex flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 text-gray-400 dark:text-gray-500 transition hover:border-primary-500 hover:text-primary-500 dark:hover:border-primary-500 dark:hover:text-primary-500"
                                    style="aspect-ratio: 1/1"
                                >
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                </button>
                            </template>
                        @endunless
                    </div>
                </template>

                {{-- Empty state --}}
                <template x-if="items.length === 0">
                    <button
                        type="button"
                        x-on:click="open()"
                        @disabled($isDisabled)
                        class="w-full flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 p-6 text-gray-400 dark:text-gray-500 transition hover:border-primary-500 hover:text-primary-500 dark:hover:border-primary-500 dark:hover:text-primary-500 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:border-gray-300 disabled:hover:text-gray-400"
                    >
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            @if($mediaType === 'image')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            @else
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                            @endif
                        </svg>
                        <span class="text-sm font-medium">{{ __('filament-media-browser::messages.choose_files') }}</span>
                    </button>
                </template>
            </div>
        @else
            {{-- Single mode --}}
            <template x-if="state">
                <div class="grid gap-3" style="{{ $gridStyle }}">
                <div class="group overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                    {{-- Preview area (1:1) --}}
                    <div class="relative bg-gray-100 dark:bg-gray-800" style="aspect-ratio: 1/1">
                        {{-- Image preview --}}
                        <template x-if="isImage(state)">
                            <img
                                x-bind:src="previewUrl(state)"
                                alt=""
                                class="w-full h-full object-cover"
                                loading="lazy"
                            />
                        </template>

                        {{-- Non-image file type preview --}}
                        <template x-if="!isImage(state)">
                            <div
                                class="w-full h-full flex items-center justify-center"
                                x-bind:class="{
                                    'bg-red-50 dark:bg-red-950': getFileTypeStyle(state) === 'pdf',
                                    'bg-purple-50 dark:bg-purple-950': getFileTypeStyle(state) === 'video',
                                    'bg-amber-50 dark:bg-amber-950': getFileTypeStyle(state) === 'audio',
                                    'bg-gray-100 dark:bg-gray-800': getFileTypeStyle(state) === 'archive' || getFileTypeStyle(state) === 'default',
                                    'bg-blue-50 dark:bg-blue-950': getFileTypeStyle(state) === 'document',
                                    'bg-green-50 dark:bg-green-950': getFileTypeStyle(state) === 'spreadsheet',
                                }"
                            >
                                {{-- PDF --}}
                                <template x-if="getFileTypeStyle(state) === 'pdf'">
                                    <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                    </svg>
                                </template>
                                {{-- Video --}}
                                <template x-if="getFileTypeStyle(state) === 'video'">
                                    <svg class="w-12 h-12 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-1.5A1.125 1.125 0 0118 18.375M20.625 4.5H3.375m17.25 0c.621 0 1.125.504 1.125 1.125M20.625 4.5h-1.5C18.504 4.5 18 5.004 18 5.625m3.75 0v1.5c0 .621-.504 1.125-1.125 1.125M3.375 4.5c-.621 0-1.125.504-1.125 1.125M3.375 4.5h1.5C5.496 4.5 6 5.004 6 5.625m-3.75 0v1.5c0 .621.504 1.125 1.125 1.125m0 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m1.5-3.75C5.496 8.25 6 7.746 6 7.125v-1.5M4.875 8.25C5.496 8.25 6 8.754 6 9.375v1.5m0-5.25v5.25m0-5.25C6 5.004 6.504 4.5 7.125 4.5h9.75c.621 0 1.125.504 1.125 1.125m1.125 2.625h1.5m-1.5 0A1.125 1.125 0 0118 7.125v-1.5m1.125 2.625c-.621 0-1.125.504-1.125 1.125v1.5m2.625-2.625c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125M18 5.625v5.25M7.125 12h9.75m-9.75 0A1.125 1.125 0 016 10.875M7.125 12C6.504 12 6 12.504 6 13.125m0-2.25C6 11.496 5.496 12 4.875 12M18 10.875c0 .621-.504 1.125-1.125 1.125M18 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m-12 5.25v-5.25m0 5.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125m-12 0v-1.5c0-.621-.504-1.125-1.125-1.125M18 18.375v-5.25m0 5.25v-1.5c0-.621.504-1.125 1.125-1.125M18 13.125v1.5c0 .621.504 1.125 1.125 1.125M18 13.125c0-.621.504-1.125 1.125-1.125M6 13.125v1.5c0 .621-.504 1.125-1.125 1.125M6 13.125C6 12.504 5.496 12 4.875 12m-1.5 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m1.5-3.75c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M4.875 15.75h1.5m-1.5 0c-.621 0-1.125-.504-1.125-1.125v-1.5"/>
                                    </svg>
                                </template>
                                {{-- Audio --}}
                                <template x-if="getFileTypeStyle(state) === 'audio'">
                                    <svg class="w-12 h-12 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 01-.99-3.467l2.31-.66A2.25 2.25 0 009 15.553z"/>
                                    </svg>
                                </template>
                                {{-- Document --}}
                                <template x-if="getFileTypeStyle(state) === 'document'">
                                    <svg class="w-12 h-12 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                    </svg>
                                </template>
                                {{-- Spreadsheet --}}
                                <template x-if="getFileTypeStyle(state) === 'spreadsheet'">
                                    <svg class="w-12 h-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125-.504-1.125-1.125M12 15.75h7.5m-7.5 0c-.621 0-1.125-.504-1.125-1.125M20.625 4.5H3.375m17.25 0c.621 0 1.125.504 1.125 1.125M20.625 4.5h-7.5C12.504 4.5 12 5.004 12 5.625m8.625-1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125-.504-1.125-1.125M12 8.25h7.5m-7.5 0c-.621 0-1.125-.504-1.125-1.125M3.375 8.25c.621 0 1.125-.504 1.125-1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m0 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M12 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125-.504 1.125-1.125m-1.125 1.125c.621 0 1.125.504 1.125 1.125m0-2.25V8.25m0 4.5v1.5c0 .621-.504 1.125-1.125 1.125"/>
                                    </svg>
                                </template>
                                {{-- Archive --}}
                                <template x-if="getFileTypeStyle(state) === 'archive'">
                                    <svg class="w-12 h-12 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                                    </svg>
                                </template>
                                {{-- Default --}}
                                <template x-if="getFileTypeStyle(state) === 'default'">
                                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                    </svg>
                                </template>
                            </div>
                        </template>

                        {{-- Action buttons (hover) --}}
                        @unless($isDisabled)
                            <div class="absolute top-2 right-2 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
                                <x-filament::icon-button
                                    icon="heroicon-o-arrow-path"
                                    color="gray"
                                    size="sm"
                                    :label="__('filament-media-browser::messages.replace')"
                                    x-on:click="open()"
                                    class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm"
                                />
                                <x-filament::icon-button
                                    icon="heroicon-o-trash"
                                    color="danger"
                                    size="sm"
                                    :label="__('filament-media-browser::messages.remove')"
                                    x-on:click="clear()"
                                    class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm"
                                />
                            </div>
                        @endunless
                    </div>
                </div>
                </div>
            </template>

            {{-- Empty state (1:1) --}}
            <template x-if="!state">
                <div class="grid gap-3" style="{{ $gridStyle }}">
                    <button
                        type="button"
                        x-on:click="open()"
                        @disabled($isDisabled)
                        class="flex flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 text-gray-400 dark:text-gray-500 transition hover:border-primary-500 hover:text-primary-500 dark:hover:border-primary-500 dark:hover:text-primary-500 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:border-gray-300 disabled:hover:text-gray-400"
                        style="aspect-ratio: 1/1"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            @if($mediaType === 'image')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            @else
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                            @endif
                        </svg>
                    </button>
                </div>
            </template>
        @endif
    </div>
</x-dynamic-component>
