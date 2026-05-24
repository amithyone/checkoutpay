<div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between gap-2">
    <button type="button" id="sidebar-toggle-edit"
            class="text-xs font-medium text-gray-500 hover:text-primary flex items-center gap-1">
        <i class="fas fa-grip-vertical"></i>
        <span>Customize menu</span>
    </button>
    <div id="sidebar-edit-actions" class="hidden items-center gap-2">
        <button type="button" id="sidebar-save-order"
                class="text-xs font-medium text-white bg-primary px-2 py-1 rounded hover:opacity-90">
            Save
        </button>
        <button type="button" id="sidebar-reset-order"
                class="text-xs font-medium text-gray-600 hover:text-gray-900">
            Reset
        </button>
        <button type="button" id="sidebar-cancel-edit"
                class="text-xs font-medium text-gray-500 hover:text-gray-700">
            Cancel
        </button>
    </div>
</div>
<p id="sidebar-edit-hint" class="hidden px-4 py-1 text-xs text-amber-700 bg-amber-50 border-b border-amber-100">
    Drag items to reorder, then click Save.
</p>

<ul id="admin-sidebar-menu" class="flex-1 min-h-0 px-4 py-4 space-y-1 overflow-y-auto list-none m-0">
    @foreach($adminSidebarMenu as $item)
        @if(($item['type'] ?? '') === 'divider')
            <li data-menu-key="{{ $item['key'] }}" class="admin-sidebar-item admin-sidebar-divider pt-3 pb-1">
                <p class="px-0 text-xs font-semibold uppercase tracking-wide text-gray-400 pointer-events-none select-none">
                    <span class="sidebar-drag-handle hidden mr-2 text-gray-300"><i class="fas fa-grip-vertical"></i></span>
                    More
                </p>
            </li>
        @else
            @php
                $variant = $item['variant'] ?? 'default';
                $active = $item['is_active'] ?? false;
                $linkClass = 'sidebar-menu-link flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 ';
                if ($variant === 'danger' && $active) {
                    $linkClass .= 'bg-red-50 text-red-700 border-l-4 border-red-600';
                } elseif ($active) {
                    $linkClass .= 'bg-primary/10 text-primary';
                }
                $badgeCount = (int) ($item['badge_count'] ?? 0);
                $badgeColor = match ($item['badge_color'] ?? 'red') {
                    'indigo' => 'bg-indigo-500',
                    'yellow' => 'bg-yellow-500',
                    default => 'bg-red-500',
                };
            @endphp
            <li data-menu-key="{{ $item['key'] }}" class="admin-sidebar-item">
                <a href="{{ $item['url'] }}"
                   onclick="if (document.getElementById('admin-sidebar-menu').classList.contains('sidebar-editing')) { event.preventDefault(); } else { closeSidebar(); }"
                   class="{{ $linkClass }}">
                    <span class="sidebar-drag-handle hidden cursor-grab active:cursor-grabbing text-gray-400 mr-2 shrink-0" title="Drag to reorder">
                        <i class="fas fa-grip-vertical"></i>
                    </span>
                    <i class="{{ $item['icon'] }} w-5 mr-3 shrink-0"></i>
                    <span class="truncate">{{ $item['label'] }}</span>
                    @if($badgeCount > 0)
                        <span class="ml-auto {{ $badgeColor }} text-white text-xs rounded-full px-2 py-0.5 shrink-0">{{ $badgeCount }}</span>
                    @endif
                </a>
            </li>
        @endif
    @endforeach
</ul>
