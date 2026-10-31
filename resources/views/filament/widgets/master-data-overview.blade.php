{{-- Judul dan deskripsi seksi sudah dirender oleh Section di halaman dasbor, jadi widget ini hanya mengisi kartunya. --}}
<x-filament-widgets::widget class="fi-wi-master-data">
    <div class="flex flex-col gap-5">
        @foreach ($groups as $group => $items)
            <div>
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ $group }}
                </div>

                <div class="grid gap-2 grid-cols-[repeat(auto-fill,minmax(11rem,1fr))]">
                    @foreach ($items as $item)
                        <a
                            href="{{ $item['url'] }}"
                            class="flex min-w-0 items-center gap-3 rounded-lg bg-white px-3 py-2.5 ring-1 ring-gray-950/5 shadow-sm transition hover:ring-primary-500 dark:bg-white/5 dark:ring-white/10 dark:hover:bg-white/10"
                        >
                            @if ($item['icon'])
                                <x-filament::icon :icon="$item['icon']" class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500" />
                            @endif

                            <span class="min-w-0">
                                <span class="block truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $item['label'] }}">{{ $item['label'] }}</span>
                                <span class="block text-lg font-semibold leading-tight text-gray-950 dark:text-white">{{ $item['count'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
