<x-filament-panels::page>
    @include('filament.pages.partials.qr-scanner')

    @if ($error)
        <x-filament::section>
            <p class="text-danger-600 dark:text-danger-400">{{ $error }}</p>
        </x-filament::section>
    @endif

    @if ($result)
        <x-filament::section heading="Hasil pemindaian" :description="$result['request_code'].' · '.$result['type']">
            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm md:grid-cols-2">
                @foreach ([
                    'Barang' => $result['product'],
                    'Jumlah Diminta' => $result['qty_request'],
                    'Jumlah Disetujui' => $result['qty_approved'],
                    'Sisa' => $result['qty_remaining'],
                    'Keterangan' => $result['description'],
                    'Pemohon' => $result['requester'].' ('.$result['division'].')',
                    'Tanggal Pengajuan' => $result['requested_at'],
                    'Status PO' => $result['po_status'],
                    'Diselesaikan Oleh' => $result['closed_by'],
                    'Waktu Selesai' => $result['closed_at'],
                    'Status Akhir' => $result['client_status'],
                    'Catatan Executor' => $result['notes'],
                    'Catatan Pemohon' => $result['user_notes'],
                ] as $label => $value)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="font-medium">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            <div class="mt-4">
                <x-filament::button tag="a" :href="$result['url']" icon="heroicon-o-arrow-top-right-on-square">
                    Buka pengajuan
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
