@foreach($items as $item)
    @php
        $has_children = !empty($item['children']);
        $is_active = $currentPath === $item['path'];
        $is_ancestor = str_starts_with($currentPath, $item['path'] . '/');
    @endphp
    <div wire:key="tree-{{ $item['path'] }}" x-data="{ expanded: @js($is_active || $is_ancestor) }">
        <div class="group flex items-center">
            {{-- Expand/Collapse toggle --}}
            @if($has_children)
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

            {{-- Folder button --}}
            <button
                type="button"
                wire:click="navigateToPath('{{ $item['path'] }}')"
                x-on:click="if (!expanded) expanded = true"
                class="flex items-center gap-1.5 flex-1 min-w-0 px-1.5 py-1 rounded-lg text-sm transition duration-75
                    {{ $is_active
                        ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-medium'
                        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800' }}"
            >
                {{-- Folder icon: open when expanded, closed when collapsed --}}
                <svg x-show="expanded" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/>
                </svg>
                <svg x-show="!expanded" x-cloak class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
                <span class="truncate">{{ $item['name'] }}</span>
            </button>

            {{-- Delete button --}}
            <div class="hidden group-hover:flex shrink-0">
                <x-filament::icon-button
                    icon="heroicon-o-trash"
                    color="danger"
                    size="sm"
                    wire:click.stop="deleteFolderByPath('{{ $item['path'] }}')"
                    wire:confirm="{{ __('filament-media-browser::messages.confirm_delete_folder') }}"
                    :label="__('filament-media-browser::messages.delete')"
                />
            </div>
        </div>

        {{-- Children --}}
        @if($has_children)
            <div x-show="expanded" x-cloak class="ps-5 mt-0.5">
                @include('filament-media-browser::livewire.partials.folder-tree-items', ['items' => $item['children']])
            </div>
        @endif
    </div>
@endforeach
