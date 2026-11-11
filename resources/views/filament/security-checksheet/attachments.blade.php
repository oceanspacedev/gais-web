{{-- Lampiran laporan security: foto bisa diklik untuk diperbesar, video diputar di tempat. --}}
@php($paths = $getRecord()->attachmentPaths())

@if ($paths === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada lampiran.</p>
@else
    <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr));">
        @foreach ($paths as $path)
            @php($url = \App\Support\StoredFile::browserUrl($path))

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-100 dark:border-white/10 dark:bg-gray-900" style="height: 14rem;">
                @if (\App\Models\SecurityChecksheet::isVideo($path))
                    <video src="{{ $url }}" controls preload="metadata" playsinline style="width: 100%; height: 100%; object-fit: contain; background-color: #000;"></video>
                @else
                    <img src="{{ $url }}" alt="Lampiran laporan security" loading="lazy" data-lightbox="true" title="Klik untuk memperbesar" style="width: 100%; height: 100%; object-fit: cover; cursor: zoom-in;" />
                @endif
            </div>
        @endforeach
    </div>
@endif
