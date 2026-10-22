<x-filament-panels::page>
    @include('filament.pages.partials.qr-scanner')

    @if ($error)
        <x-filament::section>
            <p class="text-danger-600 dark:text-danger-400">{{ $error }}</p>
        </x-filament::section>
    @endif

    @if ($result)
        <x-filament::section heading="Hasil pemindaian" :description="$result['name']">
            <div class="flex flex-col gap-4 md:flex-row">
                @if ($result['image'])
                    <img src="{{ $result['image'] }}" alt="{{ $result['name'] }}" class="h-40 w-40 rounded-lg object-cover">
                @endif
                <dl class="grid flex-1 grid-cols-1 gap-x-6 gap-y-3 text-sm md:grid-cols-2">
                    @foreach ([
                        'Kode Barang' => $result['item_code'],
                        'Kategori' => $result['category'],
                        'Satuan' => $result['unit'],
                        'Harga' => $result['price'],
                        'Stok' => $result['stock'],
                        'Keterangan' => $result['description'],
                    ] as $label => $value)
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="font-medium">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
            <div class="mt-4">
                <x-filament::button tag="a" :href="$result['print_url']" target="_blank" icon="heroicon-o-printer" color="gray">
                    Cetak QR
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
