{{-- Pratinjau Security Checksheet seperti di HP petugas: hanya pertanyaan, tanpa kunci jawaban. --}}
@php($types = \App\Enums\SecurityAnswerType::class)

@if ($sections === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada pertanyaan yang tampil di form.</p>
@else
    <div style="display: grid; gap: 1rem;">
        @foreach ($sections as $number => $section)
            <div class="rounded-xl border border-gray-200 dark:border-white/10" style="padding: 0.875rem 1rem;">
                <p class="text-sm font-semibold text-gray-950 dark:text-white" style="margin-bottom: 0.75rem;">
                    {{ $number + 1 }}. {{ $section['title'] }}
                </p>

                <div style="display: grid; gap: 1rem;">
                    @foreach ($section['items'] as $item)
                        @php($definition = $item['definition'])
                        @php($type = $types::tryFrom((string) $definition['type']))
                        <div>
                            <p class="text-sm font-medium text-gray-950 dark:text-white" style="margin-bottom: 0.375rem;">
                                {{ $definition['question'] }}<span class="text-danger-600">*</span>
                            </p>

                            @if ($type === $types::Text)
                                <div class="rounded-lg border border-gray-300 text-sm text-gray-400 dark:border-white/10 dark:text-gray-500" style="padding: 0.375rem 0.625rem;">Tulis kondisinya</div>
                            @elseif ($type === $types::YesNo)
                                <div style="display: flex; gap: 0.5rem;">
                                    @foreach ($types::yesNoOptions() as $option)
                                        <span class="rounded-lg border border-gray-300 text-sm text-gray-700 dark:border-white/10 dark:text-gray-300" style="padding: 0.25rem 0.875rem;">{{ $option }}</span>
                                    @endforeach
                                </div>
                            @else
                                <div style="display: grid; gap: 0.375rem;">
                                    @foreach ((array) ($definition['options'] ?? []) as $option)
                                        <span class="text-sm text-gray-700 dark:text-gray-300" style="display: flex; align-items: center; gap: 0.5rem;">
                                            <span class="border border-gray-400 dark:border-gray-500" style="width: 1rem; height: 1rem; flex: none; border-radius: {{ $type === $types::Checkboxes ? '0.25rem' : '9999px' }};"></span>
                                            {{ $option }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
