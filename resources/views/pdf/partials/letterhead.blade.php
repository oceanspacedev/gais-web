{{--
    Kop surat badan usaha. Butuh $letterhead (App\Support\Letterhead).
    Gaya mengikuti template DOCX pengajuan barang (public/templates/form-persetujuan-atasan.docx):
    logo besar di kiri, nama besar tebal + alamat rata kanan, garis ganda (tebal + tipis)
    seperti kop TOP SELULAR / MEDIA SELULAR INDONESIA.
    DomPDF tidak mendukung flex/grid, jadi tata letaknya memakai tabel. Gaya
    tabel/sel global di template induk (border, padding, margin) ditimpa di sini.
--}}
<style>
    .kop { margin: 12px 32px 8px; padding: 0; }
    .kop-image { width: 100%; height: auto; }
    table.kop-table { width: 100%; table-layout: fixed; border: none; border-collapse: collapse; margin: 0; font-size: 14px; }
    table.kop-table td { border: none; padding: 0; vertical-align: middle; }
    table.kop-table td.kop-logo { width: 40%; text-align: left; }
    table.kop-table td.kop-logo img { max-width: 100%; max-height: 2.4cm; }
    table.kop-table td.kop-text { line-height: 1.25; word-wrap: break-word; color: #000; }
    table.kop-table td.kop-align-right { text-align: right; }
    table.kop-table td.kop-align-center { text-align: center; }
    table.kop-table td.kop-align-left { text-align: left; padding-left: 12px; }
    {{-- Teks kiri menempel ke logo, jadi kolom logo dibuat lebih sempit. --}}
    table.kop-table td.kop-logo.kop-logo-left { width: 33%; }
    .kop-name { font-size: 24px; font-weight: bold; line-height: 1.2; margin-bottom: 2px; }
    .kop-contact { font-size: 12px; }
    .kop-line { margin-top: 10px; border-top: 3px solid #000; border-bottom: 1px solid #000; height: 2px; }
    .kop-outlet { margin-top: 2px; font-size: 11px; text-align: right; }
</style>

<div class="kop">
    @if ($letterhead->isImage())
        <img class="kop-image" src="{{ $letterhead->image }}" alt="Kop Surat">
    @else
        <table class="kop-table">
            <tr>
                @if ($letterhead->logo)
                    <td class="kop-logo kop-logo-{{ $letterhead->textAlign() }}"><img src="{{ $letterhead->logo }}" alt="Logo"></td>
                @endif
                <td class="kop-text kop-align-{{ $letterhead->textAlign() }}">
                    @if ($letterhead->name !== '')
                        <div class="kop-name">{{ $letterhead->name }}</div>
                    @endif
                    @if ($letterhead->address)
                        <div>{!! nl2br(e($letterhead->address)) !!}</div>
                    @endif
                    @if ($letterhead->contact)
                        <div class="kop-contact">{{ $letterhead->contact }}</div>
                    @endif
                </td>
            </tr>
        </table>
        <div class="kop-line"></div>
    @endif
    @if ($letterhead->outlet)
        <div class="kop-outlet">Outlet: {{ $letterhead->outlet }}</div>
    @endif
</div>
