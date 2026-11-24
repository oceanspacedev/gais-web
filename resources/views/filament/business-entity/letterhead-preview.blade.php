{{-- Pratinjau langsung kop surat di form Badan Usaha. Butuh $letterhead (App\Support\Letterhead). --}}
<div>
    <div class="mb-2 text-sm font-medium text-gray-950 dark:text-white">Pratinjau kop</div>
    {{-- Kertas selalu putih, juga di mode gelap, supaya mirip hasil cetak. --}}
    <div style="background: #fff; color: #000; font-family: 'Times New Roman', Times, serif; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 0; overflow: hidden;">
        @include('pdf.partials.letterhead')
    </div>
    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Bagian yang kosong tidak dicetak.</div>
</div>
