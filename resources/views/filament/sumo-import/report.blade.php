@php
    $report = $getState() ?? [];
    $counts = $report['counts'] ?? [];
    $notes = $report['notes'] ?? [];
    $warnings = $report['warnings'] ?? [];
@endphp

<div class="space-y-5 text-sm">
    @isset($report['seconds'])
        <p class="text-gray-500 dark:text-gray-400">
            Selesai dalam {{ number_format($report['seconds'], 1, ',', '.') }} detik
            @isset($report['finished_at'])
                · {{ \Illuminate\Support\Carbon::parse($report['finished_at'])->timezone(config('app.timezone'))->translatedFormat('d M Y H:i:s') }}
            @endisset
        </p>
    @endisset

    @if ($counts !== [])
        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-2 font-medium">Langkah</th>
                        <th class="px-3 py-2 text-right font-medium">Jumlah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($counts as $key => $count)
                        @php
                            $muted = str_ends_with($key, '.existing');
                            $flag = str_contains($key, 'skipped') || str_contains($key, 'without_');
                        @endphp
                        <tr @class(['text-gray-400 dark:text-gray-500' => $muted, 'text-warning-600 dark:text-warning-400' => $flag])>
                            <td class="px-3 py-1.5 font-mono text-xs">
                                {{ $key }}
                                @if ($muted)
                                    <span class="ms-1 font-sans">(sudah ada, dilewati)</span>
                                @endif
                            </td>
                            <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($count, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($notes !== [])
        <div>
            <h4 class="mb-1 font-semibold text-gray-950 dark:text-white">Pemetaan ({{ count($notes) }})</h4>
            <ul class="list-disc space-y-0.5 ps-5 text-gray-700 dark:text-gray-300">
                @foreach ($notes as $note)
                    <li>{{ $note }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div>
        <h4 class="mb-1 font-semibold text-gray-950 dark:text-white">Peringatan ({{ count($warnings) }})</h4>
        @if ($warnings === [])
            <p class="text-gray-500 dark:text-gray-400">Tidak ada.</p>
        @else
            <ul class="max-h-96 list-disc space-y-0.5 overflow-y-auto ps-5 text-warning-700 dark:text-warning-400">
                @foreach ($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
