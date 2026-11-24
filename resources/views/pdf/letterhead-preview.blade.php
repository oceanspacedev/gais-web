<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Pratinjau Kop Surat {{ $businessEntity->name }}</title>
    <style>
        @page { margin: 0.5cm 1cm; }

        body { font-family: 'Times New Roman', Times, serif; line-height: 1.5; margin: 0; padding: 0; }

        .content { margin: 32px; font-size: 16px; }

        h1 { text-align: center; font-size: 18px; margin: 0 0 24px; text-transform: uppercase; }

        .note { color: #555; font-size: 13px; }
    </style>
</head>

<body>
    @include('pdf.partials.letterhead')

    <div class="content">
        <h1>Pratinjau Kop Surat</h1>
        <p>Halaman ini menunjukkan kop yang akan tercetak di Berita Acara Serah Terima dan Berita Acara Pengerjaan
            untuk <strong>{{ $businessEntity->name }}</strong>.</p>
        <p class="note">Field kop yang kosong tidak dicetak. Jika kop gambar diisi, gambar itu dipakai dan data kop
            lainnya diabaikan.</p>
    </div>
</body>

</html>
