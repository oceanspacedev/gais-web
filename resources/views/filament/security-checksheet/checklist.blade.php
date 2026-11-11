{{-- Checklist laporan security: satu kartu per bagian, butir di kiri dan kondisinya di kanan. --}}
@php($sections = $getRecord()->checklistSections())

<div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));">
    @foreach ($sections as $index => $section)
        <div class="rounded-xl border border-gray-200 dark:border-white/10" style="padding: 0.875rem 1rem;">
            <p class="text-sm font-semibold text-gray-950 dark:text-white" style="margin-bottom: 0.5rem;">
                {{ $index + 1 }}. {{ $section['title'] }}
            </p>

            <dl style="display: grid; gap: 0.375rem;">
                @foreach ($section['items'] as $item)
                    <div style="display: flex; justify-content: space-between; gap: 0.75rem;" class="text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">{{ $item['label'] }}</dt>
                        <dd class="font-medium text-gray-950 dark:text-white" style="text-align: right;">{{ $item['value'] !== '' ? $item['value'] : '-' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endforeach
</div>
