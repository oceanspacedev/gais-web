<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Simpan Pengaturan
            </x-filament::button>
        </div>
    </form>

    <x-filament::section heading="Log pengiriman terakhir" description="20 pengiriman terakhir, termasuk uji coba.">
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-sm">
                <thead>
                    <tr class="text-left">
                        <th class="px-3 py-2">Waktu</th>
                        <th class="px-3 py-2">Jenis</th>
                        <th class="px-3 py-2">Referensi</th>
                        <th class="px-3 py-2">Nomor</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->recentLogs as $log)
                        <tr class="border-t border-gray-200 dark:border-white/10">
                            <td class="px-3 py-2">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-3 py-2">{{ $log->notif_type?->label() ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $log->reference_id ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $log->phone }}</td>
                            <td class="px-3 py-2">
                                <x-filament::badge :color="$log->status === \App\Models\WaNotificationLog::STATUS_SUCCESS ? 'success' : 'danger'">
                                    {{ $log->status }}
                                </x-filament::badge>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-3 py-4 text-gray-500" colspan="5">Belum ada pengiriman.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
