{{-- Checklist laporan security: satu kartu per bagian, pertanyaan di kiri dan jawabannya di kanan. Jawaban yang berbeda dari kunci ditandai. --}}
@php($sections = $getRecord()->checklistSections())

@if ($sections === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">Checklist tidak diisi.</p>
@else
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
                            <dd style="text-align: right;">
                                @if ($item['matches'] === false)
                                    <span class="font-medium text-danger-600 dark:text-danger-400" data-mismatch="true">{{ $item['value'] !== '' ? $item['value'] : '-' }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">Tidak sesuai · kunci: {{ $item['answer_key'] }}</span>
                                @else
                                    <span class="font-medium text-gray-950 dark:text-white">{{ $item['value'] !== '' ? $item['value'] : '-' }}</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endforeach
    </div>
@endif
