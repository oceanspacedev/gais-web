{{-- Pemindai QR berbasis kamera (html5-qrcode) plus input manual; hasil dikirim ke $wire.lookup(). --}}
<x-filament::section heading="Pindai QR" description="Arahkan kamera ke QR, atau ketik kodenya lalu tekan Enter.">
    <div
        x-data="{
            code: @entangle('code'),
            scanner: null,
            start() {
                if (! window.Html5QrcodeScanner) { return; }
                this.scanner = new window.Html5QrcodeScanner('sumo-qr-reader', { fps: 10, qrbox: { width: 220, height: 220 } }, false);
                this.scanner.render((text) => { this.code = text; $wire.lookup(text); }, () => {});
            },
        }"
        x-init="start()"
        class="space-y-4"
    >
        <div id="sumo-qr-reader" class="mx-auto w-full max-w-md"></div>
        <div class="flex gap-2">
            <x-filament::input.wrapper class="flex-1">
                <x-filament::input type="text" x-model="code" placeholder="Kode QR / nomor" x-on:keydown.enter.prevent="$wire.lookup(code)" />
            </x-filament::input.wrapper>
            <x-filament::button x-on:click="$wire.lookup(code)" icon="heroicon-o-magnifying-glass">Cari</x-filament::button>
        </div>
    </div>
</x-filament::section>

@once
    @push('scripts')
        <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    @endpush
@endonce
