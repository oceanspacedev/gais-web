@php
    /** @var \App\Models\SumoImportRun $run */
    $run = $getRecord();
    $log = $run->displayLog();
@endphp

<div @if ($run->isBusy()) wire:poll.3s="refreshRun" @endif>
    @if ($run->isBusy())
        <p class="mb-2 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <x-filament::loading-indicator class="h-4 w-4" />
            {{ $run->statusLabel() }}…
        </p>
    @endif

    <pre
        x-data
        x-init="$el.scrollTop = $el.scrollHeight"
        class="max-h-[32rem] overflow-auto whitespace-pre-wrap break-words rounded-lg bg-gray-950 p-4 font-mono text-xs leading-relaxed text-gray-100"
    >{{ $log !== '' ? $log : 'Belum ada log.' }}</pre>
</div>
