{{-- Pilihan jawaban sebagai badge; yang benar hijau dengan tanda centang. --}}
@php
    /** @var \App\Models\SecurityChecklistItem $record */
    $record = $getRecord();
    $type = $record->answer_type;
    $keys = array_map('strval', (array) $record->answer_key);
    $choices = match (true) {
        $type === \App\Enums\SecurityAnswerType::YesNo => array_values(\App\Enums\SecurityAnswerType::yesNoOptions()),
        $type->hasOptions() => array_map('strval', $record->options ?? []),
        default => [],
    };
@endphp

<div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.375rem; padding: 0.25rem 0;">
    @forelse ($choices as $choice)
        @if (in_array($choice, $keys, true))
            <x-filament::badge color="success" icon="heroicon-m-check">{{ $choice }}</x-filament::badge>
        @else
            <x-filament::badge color="gray">{{ $choice }}</x-filament::badge>
        @endif
    @empty
        <span class="text-sm text-gray-500 dark:text-gray-400">Diketik security · tidak dinilai</span>
    @endforelse

    @if ($choices !== [] && $keys === [])
        <span class="text-sm text-gray-500 dark:text-gray-400">· tidak dinilai</span>
    @endif
</div>
