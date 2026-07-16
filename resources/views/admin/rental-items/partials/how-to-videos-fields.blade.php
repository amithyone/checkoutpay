@php
    $videos = old('how_to_videos', $howToVideos ?? []);
    if (! is_array($videos)) {
        $videos = [];
    }
    if ($videos === []) {
        $videos = [['title' => '', 'url' => '']];
    }
    $fieldPrefix = $fieldPrefix ?? 'how_to_videos';
@endphp

<div class="border border-sky-100 rounded-md p-3 bg-sky-50/40 how-to-videos-block" data-prefix="{{ $fieldPrefix }}">
    <div class="flex flex-wrap justify-between items-center gap-2 mb-2">
        <div>
            <div class="text-xs font-semibold text-sky-900">How-to use videos (YouTube)</div>
            <p class="text-[10px] text-gray-500 mt-0.5">Shown in the app on gear detail. You can assign the same links to many items via bulk actions.</p>
        </div>
        <button type="button" class="text-xs bg-white border border-sky-200 text-sky-800 px-2 py-1 rounded-md hover:bg-sky-50 how-to-add-row">
            <i class="fas fa-plus mr-1"></i>Add video
        </button>
    </div>

    <div class="how-to-rows space-y-2">
        @foreach($videos as $index => $video)
            @php
                $video = is_array($video) ? $video : [];
            @endphp
            <div class="how-to-row grid grid-cols-1 md:grid-cols-12 gap-2 items-end bg-white/80 border border-sky-100 rounded-md p-2">
                <div class="md:col-span-5">
                    <label class="block text-[10px] font-medium text-gray-600 mb-0.5">Title</label>
                    <input type="text" name="{{ $fieldPrefix }}[{{ $index }}][title]" maxlength="200"
                           value="{{ $video['title'] ?? '' }}"
                           placeholder="Komodo quick start"
                           class="w-full text-sm border-gray-300 rounded-md py-1.5">
                </div>
                <div class="md:col-span-6">
                    <label class="block text-[10px] font-medium text-gray-600 mb-0.5">YouTube URL</label>
                    <input type="url" name="{{ $fieldPrefix }}[{{ $index }}][url]" maxlength="500"
                           value="{{ $video['url'] ?? '' }}"
                           placeholder="https://www.youtube.com/watch?v=…"
                           class="w-full text-sm border-gray-300 rounded-md py-1.5">
                </div>
                <div class="md:col-span-1 flex md:justify-end">
                    <button type="button" class="text-xs text-red-600 hover:text-red-800 how-to-remove-row" title="Remove row">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>

@once
    @push('scripts')
    <script>
        document.addEventListener('click', function (e) {
            const addBtn = e.target.closest('.how-to-add-row');
            if (addBtn) {
                const block = addBtn.closest('.how-to-videos-block');
                const rows = block.querySelector('.how-to-rows');
                const prefix = block.dataset.prefix || 'how_to_videos';
                const index = rows.querySelectorAll('.how-to-row').length;
                const row = document.createElement('div');
                row.className = 'how-to-row grid grid-cols-1 md:grid-cols-12 gap-2 items-end bg-white/80 border border-sky-100 rounded-md p-2';
                row.innerHTML = `
                    <div class="md:col-span-5">
                        <label class="block text-[10px] font-medium text-gray-600 mb-0.5">Title</label>
                        <input type="text" name="${prefix}[${index}][title]" maxlength="200" placeholder="Komodo quick start" class="w-full text-sm border-gray-300 rounded-md py-1.5">
                    </div>
                    <div class="md:col-span-6">
                        <label class="block text-[10px] font-medium text-gray-600 mb-0.5">YouTube URL</label>
                        <input type="url" name="${prefix}[${index}][url]" maxlength="500" placeholder="https://www.youtube.com/watch?v=…" class="w-full text-sm border-gray-300 rounded-md py-1.5">
                    </div>
                    <div class="md:col-span-1 flex md:justify-end">
                        <button type="button" class="text-xs text-red-600 hover:text-red-800 how-to-remove-row" title="Remove row"><i class="fas fa-times"></i></button>
                    </div>`;
                rows.appendChild(row);
                return;
            }

            const removeBtn = e.target.closest('.how-to-remove-row');
            if (removeBtn) {
                const row = removeBtn.closest('.how-to-row');
                const rows = row.parentElement;
                if (rows.querySelectorAll('.how-to-row').length > 1) {
                    row.remove();
                } else {
                    row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
                }
            }
        });
    </script>
    @endpush
@endonce
