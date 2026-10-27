@php
    $flows = $this->flows();

    // Keyframe tiap langkah dibuat per jumlah langkah (3-8) supaya semua alur punya satu jam yang sama
    // dan reset bersamaan: aktif -> selesai -> kembali ke awal.
    $keyframes = '';
    foreach (range(3, 8) as $n) {
        $total = $n * 1.8 + 2;
        for ($i = 0; $i < $n; $i++) {
            $s = round($i * 1.8 / $total * 100, 2);
            $e = round(($i + 1) * 1.8 / $total * 100, 2);
            $s2 = $s + 0.8;
            $e2 = $e + 0.8;
            $on = "0%{opacity:0} {$s}%{opacity:0} {$s2}%{opacity:1} {$e}%{opacity:1} {$e2}%{opacity:.5} 96%{opacity:.5} 98%{opacity:0} 100%{opacity:0}";
            $dot = "0%{opacity:0} {$s}%{opacity:0} {$s2}%{opacity:1} {$e}%{opacity:1} {$e2}%{opacity:.85} 96%{opacity:.85} 98%{opacity:0} 100%{opacity:0}";
            $fill = "0%,{$s}%{transform:scaleY(0)} {$e}%,96%{transform:scaleY(1)} 98%,100%{transform:scaleY(0)}";
            $keyframes .= "@keyframes pg-dot-{$n}-{$i}{{$dot}}@keyframes pg-card-{$n}-{$i}{{$on}}@keyframes pg-fill-{$n}-{$i}{{$fill}}";
        }
    }

    $tabs = [
        'mulai' => 'Mulai di sini',
        'langkah' => 'Langkah demi langkah',
        'login' => 'Masuk ke aplikasi',
        'pengajuan' => 'Pengajuan Barang',
        'gangguan' => 'Lapor Gangguan',
        'aset' => 'Aset dan BA',
        'ob' => 'OB, Kendaraan, Vendor',
        'kontrak' => 'Asuransi, Sewa, Pengingat',
        'akses' => 'Role dan akses',
        'developer' => 'Untuk developer',
    ];
@endphp

<x-filament-panels::page>
    <style>
        {!! $keyframes !!}

        .pg { --pg-line: var(--gray-200, #e5e7eb); --pg-soft: var(--gray-50, #f9fafb); --pg-mute: var(--gray-500, #6b7280); --pg-accent: var(--primary-600, #2563eb); }
        .dark .pg { --pg-line: var(--gray-700, #374151); --pg-soft: color-mix(in srgb, var(--gray-800, #1f2937) 60%, transparent); --pg-mute: var(--gray-400, #9ca3af); --pg-accent: var(--primary-400, #60a5fa); }

        .pg-tabs { display: flex; flex-wrap: wrap; gap: .375rem; padding-bottom: .75rem; border-bottom: 1px solid var(--pg-line); }
        .pg-tab { border: 1px solid var(--pg-line); border-radius: .5rem; padding: .375rem .75rem; font-size: .8125rem; font-weight: 500; cursor: pointer; background: transparent; color: inherit; }
        .pg ol.pg-toc { columns: 2; column-gap: 2rem; margin: .5rem 0 0; padding: 0; list-style: none; }
        .pg ol.pg-toc li { margin-bottom: .35rem; break-inside: avoid; }
        @media (max-width: 640px) { .pg ol.pg-toc { columns: 1; } }
        .pg-link { color: var(--pg-accent); }
        .pg-link:hover { text-decoration: underline; }
        .pg-tab:hover { background: var(--pg-soft); }
        .pg-tab[aria-selected="true"] { background: var(--pg-accent); border-color: var(--pg-accent); color: #fff; }
        .pg-tab:focus-visible, .pg-ctl:focus-visible { outline: 2px solid var(--pg-accent); outline-offset: 2px; }

        .pg-body { display: flex; flex-direction: column; gap: 1.25rem; max-width: 62rem; padding-top: 1rem; }
        .pg-body > * { min-width: 0; }
        .pg h2 { font-size: 1.25rem; font-weight: 600; margin: 0; text-wrap: balance; }
        .pg h3 { font-size: 1rem; font-weight: 600; margin: 0; }
        .pg p, .pg li { line-height: 1.6; font-size: .9rem; }
        .pg p { margin: 0; }
        .pg ul.pg-list { margin: 0; padding-left: 1.2rem; list-style: disc; display: flex; flex-direction: column; gap: .35rem; }
        .pg code { font-size: .8rem; padding: .05rem .35rem; border-radius: .25rem; background: var(--pg-soft); border: 1px solid var(--pg-line); word-break: break-word; }
        .pg pre { margin: 0; padding: .75rem 1rem; border-radius: .5rem; background: var(--pg-soft); border: 1px solid var(--pg-line); overflow-x: auto; font-size: .8rem; line-height: 1.55; }
        .pg-lead { color: var(--pg-mute); max-width: 46rem; }
        .pg-block { display: flex; flex-direction: column; gap: .6rem; }
        .pg-note { border: 1px solid var(--pg-line); border-left: 3px solid var(--pg-accent); border-radius: .5rem; padding: .65rem .85rem; background: var(--pg-soft); }
        .pg-warn { border-left-color: #d97706; }

        .pg-scroll { overflow-x: auto; }
        .pg-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .pg-table th, .pg-table td { text-align: left; vertical-align: top; padding: .5rem .65rem; border-bottom: 1px solid var(--pg-line); }
        .pg-table th { font-weight: 600; font-size: .75rem; letter-spacing: .04em; text-transform: uppercase; color: var(--pg-mute); }

        .pg-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: .75rem; }
        .pg-tile { border: 1px solid var(--pg-line); border-radius: .5rem; padding: .75rem .85rem; display: flex; flex-direction: column; gap: .3rem; animation: pg-rise .5s ease both; animation-delay: calc(var(--k, 0) * 60ms); }
        .pg-tile p { color: var(--pg-mute); font-size: .82rem; }
        @keyframes pg-rise { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

        .pg-bar { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }
        .pg-ctl { border: 1px solid var(--pg-line); border-radius: .5rem; padding: .25rem .65rem; font-size: .78rem; cursor: pointer; background: transparent; color: inherit; }
        .pg-ctl:hover { background: var(--pg-soft); }

        /* Alur animasi */
        .pg-flow { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
        .pg-step { display: grid; grid-template-columns: 2.25rem minmax(0, 1fr); gap: .85rem; }
        .pg-rail { display: flex; flex-direction: column; align-items: center; }
        .pg-dot { position: relative; flex: none; width: 2.25rem; height: 2.25rem; border-radius: 50%; border: 2px solid var(--pg-line); display: grid; place-items: center; font-size: .85rem; font-weight: 600; font-variant-numeric: tabular-nums; }
        .pg-dot-on, .pg-card-on, .pg-fill { animation-duration: calc(var(--n) * 1.8s + 2s); animation-timing-function: linear; animation-iteration-count: infinite; }
        .pg-dot-on { position: absolute; inset: -2px; border-radius: 50%; background: var(--pg-accent); opacity: 0; display: grid; place-items: center; }
        .pg-dot-on::after { content: attr(data-n); color: #fff; font-size: .85rem; font-weight: 600; }
        .pg-seg { flex: 1; width: 2px; min-height: .75rem; background: var(--pg-line); position: relative; overflow: hidden; }
        .pg-fill { position: absolute; inset: 0; background: var(--pg-accent); transform-origin: top; transform: scaleY(0); }
        .pg-card { position: relative; border: 1px solid var(--pg-line); border-radius: .5rem; padding: .7rem .9rem; margin-bottom: .75rem; display: flex; flex-direction: column; gap: .3rem; }
        .pg-card-on { position: absolute; inset: -1px; border-radius: .5rem; border: 1px solid var(--pg-accent); background: color-mix(in srgb, var(--pg-accent) 8%, transparent); opacity: 0; pointer-events: none; }
        .pg-step-title { font-weight: 600; }
        .pg-step-text { color: var(--pg-mute); font-size: .85rem; }
        .pg-badge { align-self: flex-start; font-size: .7rem; font-weight: 600; letter-spacing: .03em; padding: .1rem .5rem; border-radius: 999px; background: color-mix(in srgb, var(--h, #64748b) 16%, transparent); color: var(--h, #64748b); border: 1px solid color-mix(in srgb, var(--h, #64748b) 40%, transparent); }
        .pg-a-pemohon { --h: #2563eb; } .pg-a-accounting { --h: #b45309; } .pg-a-manager { --h: #7c3aed; }
        .pg-a-executor { --h: #047857; } .pg-a-ga { --h: #be185d; } .pg-a-admin { --h: #0e7490; }
        .pg-a-sistem { --h: #64748b; } .pg-a-user { --h: #4f46e5; }
        .dark .pg-a-pemohon { --h: #60a5fa; } .dark .pg-a-accounting { --h: #fbbf24; } .dark .pg-a-manager { --h: #a78bfa; }
        .dark .pg-a-executor { --h: #34d399; } .dark .pg-a-ga { --h: #f472b6; } .dark .pg-a-admin { --h: #22d3ee; }
        .dark .pg-a-sistem { --h: #94a3b8; } .dark .pg-a-user { --h: #818cf8; }

        .pg-paused .pg-dot-on, .pg-paused .pg-card-on, .pg-paused .pg-fill { animation-play-state: paused; }

        /* Status berjalan: token bergerak di jalur */
        .pg-track { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
        .pg-pill { position: relative; border: 1px solid var(--pg-line); border-radius: 999px; padding: .25rem .8rem; font-size: .82rem; font-weight: 500; }
        .pg-pill-on { position: absolute; inset: -1px; border-radius: 999px; background: var(--pg-accent); opacity: 0; animation: pg-pill 10s linear infinite; animation-delay: calc(var(--k) * 2s); }
        .pg-pill span { position: relative; mix-blend-mode: normal; }
        .pg-arrow { color: var(--pg-mute); }
        @keyframes pg-pill { 0% { opacity: 0; } 2% { opacity: .22; } 18% { opacity: .22; } 20% { opacity: 0; } 100% { opacity: 0; } }
        .pg-paused .pg-pill-on, .pg-paused .pg-tile { animation-play-state: paused; }

        .pg-demo { margin: 0 0 1rem; border: 1px solid var(--pg-line); border-radius: .75rem; overflow: hidden; }
        .pg-demo > img { display: block; width: 100%; height: auto; }
        .pg-demo figcaption { padding: .5rem .85rem; font-size: .82rem; color: var(--pg-mute); border-top: 1px solid var(--pg-line); }
        .pg-shots { display: grid; grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr)); gap: .6rem; padding: .75rem; border-top: 1px solid var(--pg-line); }
        .pg-shots a { display: block; text-decoration: none; color: inherit; font-size: .75rem; }
        .pg-shots img { display: block; width: 100%; border: 1px solid var(--pg-line); border-radius: .4rem; }
        .pg-shots span { display: block; margin-top: .25rem; }

        @media (prefers-reduced-motion: reduce) {
            .pg-dot-on, .pg-card-on, .pg-pill-on, .pg-tile { animation: none; }
            .pg-dot-on { opacity: .85; }
            .pg-fill { animation: none; transform: scaleY(1); }
            .pg-pill-on { opacity: .12; }
        }
    </style>

    <div class="pg" x-data="{ tab: 'mulai', paused: false }" x-init="const h = location.hash.slice(1); if (h && {{ \Illuminate\Support\Js::from(array_keys($tabs)) }}.includes(h)) tab = h" :class="{ 'pg-paused': paused }">
        <div class="pg-tabs" role="tablist" aria-label="Bagian panduan">
            @foreach ($tabs as $key => $label)
                <button type="button" role="tab" class="pg-tab" :aria-selected="tab === '{{ $key }}'" x-on:click="tab = '{{ $key }}'; history.replaceState(null, '', '#{{ $key }}'); $nextTick(() => $el.scrollIntoView({ block: 'nearest' }))">{{ $label }}</button>
            @endforeach
        </div>

        <div class="pg-body">
            <div class="pg-bar">
                <button type="button" class="pg-ctl" x-on:click="paused = !paused" :aria-pressed="paused" x-text="paused ? 'Putar animasi' : 'Jeda animasi'">Jeda animasi</button>
                <span class="pg-lead" style="font-size: .8rem">Animasi menyorot satu langkah demi satu. Tiap langkah punya lencana siapa yang mengerjakan.</span>
            </div>

            {{-- ============ MULAI ============ --}}
            <section x-show="tab === 'mulai'" class="pg-body" style="padding-top: 0">
                <div class="pg-block">
                    <h2>Shelf dalam satu halaman</h2>
                    <p class="pg-lead">Shelf adalah aplikasi internal berbasis web untuk mencatat aset dan menangani pekerjaan kantor sehari-hari: pengajuan barang, laporan gangguan, kebersihan (OB), kendaraan, pekerjaan vendor, asuransi dan sewa. Aplikasi ini menggabungkan Shelf yang lama dengan sumo-web, dan semuanya ada di satu panel: <code>/admin</code>.</p>
                </div>

                <div class="pg-block">
                    <h3>Peta menu di sidebar</h3>
                    <div class="pg-grid">
                        @foreach ([
                            ['Aset', 'Data aset, BA transfer (serah terima, pengalihan, pengembalian), servis, rekonsiliasi audit, riwayat label QR.'],
                            ['Master Aset', 'Merek, kategori, atribut kustom (STNK, KIR, dan sejenisnya), vendor.'],
                            ['OB / Kebersihan', 'Checksheet kebersihan, ruangan, shift.'],
                            ['Kendaraan', 'Catatan penggunaan kendaraan: KM, tujuan, kerusakan.'],
                            ['Pekerjaan Vendor', 'Tugas yang dikerjakan vendor beserta biaya dan dokumen.'],
                            ['Pengajuan Barang', 'Pengajuan, scan QR, log, tipe pengajuan, cutoff bulanan, barang, kategori, satuan.'],
                            ['Lapor Gangguan', 'Laporan gangguan dan kategorinya.'],
                            ['Asuransi & Sewa', 'Polis asuransi dan kontrak sewa beserta penyedia, kategori, cakupan.'],
                            ['Organisasi', 'Area, lokasi, badan usaha, divisi, jabatan, pengguna.'],
                            ['Pengaturan', 'Pengaturan notifikasi WhatsApp, Impor Data Sumo, dan peralatan admin lain.'],
                        ] as $k => [$name, $desc])
                            <div class="pg-tile" style="--k: {{ $k }}">
                                <h3>{{ $name }}</h3>
                                <p>{{ $desc }}</p>
                            </div>
                        @endforeach
                    </div>
                    <p class="pg-lead">Menu yang tampil mengikuti role Anda. Kalau sebuah menu tidak muncul, berarti role Anda tidak punya izin untuk itu (lihat tab Role dan akses).</p>
                </div>

                <div class="pg-block">
                    <h3>Tiga hal yang perlu dipahami dulu</h3>
                    <ul class="pg-list">
                        <li><strong>Semua dibatasi per badan usaha.</strong> Kecuali super admin dan user dengan flag "Akses semua badan usaha", Anda hanya melihat data badan usaha Anda sendiri.</li>
                        <li><strong>Akses ditentukan permission, bukan nama role.</strong> Role hanyalah kumpulan permission yang diatur lewat Shield.</li>
                        <li><strong>Pekerjaan lama tidak hilang.</strong> Data dari sumo-web diimpor lewat menu <strong>Pengaturan, Impor Data Sumo</strong> (atau <code>sumo:import</code>), dan kode lama seperti <code>REQ123</code> tetap dipakai.</li>
                    </ul>
                </div>
            </section>

            {{-- ============ LANGKAH DEMI LANGKAH ============ --}}
            <section x-show="tab === 'langkah'" x-cloak class="pg-body" style="padding-top: 0">
                <div class="pg-block">
                    <h2>Langkah demi langkah</h2>
                    <p class="pg-lead">Setiap bab punya animasi layar asli dan screenshot tiap langkah. Data pada animasi hanya contoh. Gambar dibuat otomatis, jadi selalu sesuai tampilan terbaru.</p>
                    <ol class="pg-list pg-toc">
                        <li><a href="#" class="pg-link" x-on:click.prevent="document.getElementById('bab-1').scrollIntoView({ behavior: 'smooth' })">1. Masuk dan keluar</a></li>
                        <li><a href="#" class="pg-link" x-on:click.prevent="document.getElementById('bab-2').scrollIntoView({ behavior: 'smooth' })">2. Dasbor dan menu samping</a></li>
                        <li><a href="#" class="pg-link" x-on:click.prevent="document.getElementById('bab-3').scrollIntoView({ behavior: 'smooth' })">3. Aset: mencari dan melihat detail</a></li>
                        <li><a href="#" class="pg-link" x-on:click.prevent="document.getElementById('bab-4').scrollIntoView({ behavior: 'smooth' })">4. Pengingat dokumen (contoh: STNK)</a></li>
                        <li><a href="#" class="pg-link" x-on:click.prevent="document.getElementById('bab-5').scrollIntoView({ behavior: 'smooth' })">5. Pengajuan Barang dari awal sampai diterima</a></li>
                        <li><a href="#" class="pg-link" x-on:click.prevent="document.getElementById('bab-6').scrollIntoView({ behavior: 'smooth' })">6. Lapor Gangguan</a></li>
                        <li><a href="#" class="pg-link" x-on:click.prevent="document.getElementById('bab-7').scrollIntoView({ behavior: 'smooth' })">7. Berita Acara aset: serah terima, pengalihan, pengembalian</a></li>
                        <li><a href="#" class="pg-link" x-on:click.prevent="document.getElementById('bab-8').scrollIntoView({ behavior: 'smooth' })">8. Servis aset</a></li>
                        <li><a href="#" class="pg-link" x-on:click.prevent="document.getElementById('bab-9').scrollIntoView({ behavior: 'smooth' })">9. OB: menugaskan dan mengerjakan</a></li>
                        <li><a href="#" class="pg-link" x-on:click.prevent="document.getElementById('bab-10').scrollIntoView({ behavior: 'smooth' })">10. Checksheet kendaraan</a></li>
                        <li><a href="#" class="pg-link" x-on:click.prevent="document.getElementById('bab-11').scrollIntoView({ behavior: 'smooth' })">11. Menambah pengguna dan memberi role</a></li>
                        <li><a href="#" class="pg-link" x-on:click.prevent="document.getElementById('bab-12').scrollIntoView({ behavior: 'smooth' })">12. Import &amp; Laporan Audit (contoh: kendaraan)</a></li>
                        <li><a href="#" class="pg-link" x-on:click.prevent="document.getElementById('bab-13').scrollIntoView({ behavior: 'smooth' })">13. Asuransi, Sewa, dan pengingat WhatsApp</a></li>
                        <li><a href="#" class="pg-link" x-on:click.prevent="document.getElementById('bab-14').scrollIntoView({ behavior: 'smooth' })">14. Pekerjaan Vendor</a></li>
                        <li><a href="#" class="pg-link" x-on:click.prevent="document.getElementById('bab-15').scrollIntoView({ behavior: 'smooth' })">15. Import &amp; Laporan Audit stok (CSA)</a></li>
                    </ol>
                </div>

                <div class="pg-block" id="bab-1">
                    <h2>1. Masuk dan keluar</h2>
                    <p>Buka <code>/admin/login</code>, isi username (atau email) dan kata sandi, lalu tekan tombol masuk.</p>
                    <x-guide.demo name="login" caption="Animasi: mengisi username dan kata sandi lalu masuk ke dasbor." :shots="[
                        'login-01-halaman-login.png' => 'Halaman login',
                        'login-02-form-terisi.png' => 'Form terisi',
                        'login-03-dasbor.png' => 'Dasbor setelah masuk',
                    ]" />
                    <ul class="pg-list">
                        <li>Lupa kata sandi: minta admin mengubahnya di menu Organisasi, Pengguna. Reset mandiri dimatikan.</li>
                        <li>Masuk lewat WhatsApp (OTP) dijelaskan di tab <strong>Masuk ke aplikasi</strong>.</li>
                        <li>Untuk keluar, tekan tombol <strong>Keluar</strong> di dasbor atau menu akun di pojok kanan atas.</li>
                    </ul>
                </div>

                <div class="pg-block" id="bab-2">
                    <h2>2. Dasbor dan menu samping</h2>
                    <p>Dasbor menampilkan ringkasan aset (tersedia, digunakan, dijual, hilang, rusak) dan nilai aset. Menu di kiri dikelompokkan, dan hanya menu yang boleh Anda buka yang tampil.</p>
                    <x-guide.demo name="dasbor" caption="Animasi: menutup dan membuka grup menu, lalu membuka halaman Panduan." :shots="[
                        'dasbor-01-dasbor.png' => 'Dasbor',
                        'dasbor-02-grup-menu-tertutup.png' => 'Grup menu ditutup',
                        'dasbor-03-menu-panduan.png' => 'Halaman Panduan',
                    ]" />
                    <ul class="pg-list">
                        <li>Klik judul grup (misalnya <strong>Master Aset</strong>) untuk menutup atau membuka isinya.</li>
                        <li>Menu <strong>Panduan</strong> selalu ada untuk semua user yang sudah login.</li>
                    </ul>
                </div>

                <div class="pg-block" id="bab-3">
                    <h2>3. Aset: mencari dan melihat detail</h2>
                    <p>Menu <strong>Aset</strong> berisi seluruh inventaris. Cari lewat kotak pencarian di atas tabel, lalu klik baris untuk melihat detailnya.</p>
                    <x-guide.demo name="aset-daftar" caption="Animasi: membuka daftar aset, mencari Avanza, lalu membuka detailnya." :shots="[
                        'aset-daftar-01-daftar-aset.png' => 'Daftar aset',
                        'aset-daftar-02-hasil-pencarian.png' => 'Hasil pencarian',
                        'aset-daftar-03-detail-aset.png' => 'Detail aset',
                        'aset-daftar-04-detail-atribut-dan-qr.png' => 'Atribut khusus dan QR',
                    ]" />
                    <ul class="pg-list">
                        <li>Halaman detail memuat informasi aset, <strong>Atribut Khusus</strong> (misalnya dokumen STNK), status dan NBH, lokasi, pemegang, serta QR code.</li>
                        <li>Tombol di kanan atas: cetak atau unduh label QR, buat ulang QR, dan <strong>Ubah</strong>.</li>
                        <li>Kotak pencarian juga mencari isi atribut kustom seperti <strong>Plat Nomor</strong>, serial number, dan IMEI, termasuk yang kolomnya disembunyikan. Spasi, strip, titik, dan garis miring diabaikan, jadi <code>B1234XYZ</code> menemukan <code>B 1234 XYZ</code>. Pencarian yang sama berlaku saat memilih aset di form BA dan servis.</li>
                        <li>Model/tipe, serial number, dan IMEI diisi sebagai atribut di bagian <strong>Attributes</strong>, bukan kolom tetap di form.</li>
                        <li>Cara membuat aset baru beserta dokumen bermasa berlaku ada di bab 4.</li>
                    </ul>
                </div>

                <div class="pg-block" id="bab-4">
                    <h2>4. Pengingat dokumen (contoh: STNK)</h2>
                    <p>Dokumen yang punya masa berlaku, seperti STNK dan KIR, bisa diingatkan lewat WhatsApp sebelum kedaluwarsa. Caranya dua tahap: admin membuat atribut sekali, lalu staf mengisinya di tiap aset.</p>
                    <h3>4.1 Admin membuat atribut dokumen</h3>
                    <x-guide.demo name="setup" caption="Animasi: membuat atribut Dokumen STNK Mobil dengan pengingat mulai H-30." :shots="[
                        'setup-01-menu-custom-attribute.png' => 'Menu Custom Aset Attribute',
                        'setup-02-nama-dan-tipe.png' => 'Nama dan tipe',
                        'setup-03-pengaturan-pengingat.png' => 'Pengaturan pengingat',
                        'setup-04-penerima-dan-kategori.png' => 'Penerima dan kategori',
                        'setup-05-atribut-tersimpan.png' => 'Tersimpan',
                    ]" />
                    <ul class="pg-list">
                        <li><strong>Tipe Input</strong>: pilih <em>Dokumen / Masa Berlaku</em>. <strong>Pola Pengingat</strong>: harian sebelum tanggal berlaku habis.</li>
                        <li><strong>Mulai Pengingat H-</strong>: berapa hari sebelum habis pengingat mulai dikirim setiap hari (contoh 30).</li>
                        <li><strong>Penerima Internal</strong> dan <strong>Kirim Lewat</strong> menentukan siapa dan lewat apa pengingat dikirim. WhatsApp memakai nomor di data user.</li>
                        <li><strong>Kategori</strong>: aset di kategori ini otomatis meminta dokumen. <strong>Wajib Diisi</strong> membuat dokumen tidak boleh kosong.</li>
                    </ul>
                    <h3>4.2 Staf mengisi dokumen pada aset</h3>
                    <x-guide.demo name="isi-aset" caption="Animasi: membuat aset Mobil, atribut STNK muncul otomatis, lalu mengisi nomor, tanggal, dan lampiran." :shots="[
                        'isi-aset-01-info-aset.png' => 'Informasi aset',
                        'isi-aset-02-atribut-otomatis-muncul.png' => 'Atribut muncul otomatis',
                        'isi-aset-03-dokumen-terisi.png' => 'Dokumen terisi',
                        'isi-aset-04-detail-pembelian.png' => 'Detail pembelian',
                        'isi-aset-05-aset-tersimpan.png' => 'Aset tersimpan',
                    ]" />
                    <ul class="pg-list">
                        <li>Begitu <strong>Kategori</strong> dipilih, atribut dokumen yang cocok muncul sendiri di bagian <strong>Attributes</strong>.</li>
                        <li>Isi <strong>Nomor Dokumen</strong>, <strong>Berlaku Sampai</strong>, dan unggah <strong>Lampiran</strong> (PDF/JPG/PNG).</li>
                        <li>Tanggal Pembelian dan Badan Usaha wajib diisi di kolom kanan.</li>
                        <li>Status Pengingat dihitung otomatis: <em>Aman</em> bila masih jauh, <em>Peringatan</em> saat masuk masa H-.</li>
                    </ul>
                    <div class="pg-note"><p>Pengingat dikirim oleh scheduler setiap hari pukul 13:20 selama dokumen belum diperbarui. Perlu <code>WAG_URL</code> dan <code>WAG_TOKEN</code> terisi.</p></div>
                </div>
                <div class="pg-block" id="bab-5">
                    <h2>5. Pengajuan Barang dari awal sampai diterima</h2>
                    <p>Satu pengajuan dikerjakan bergantian oleh beberapa orang. Contoh di bawah memakai tipe <em>Pembelian Inventaris</em>, yang melewati tahap Accounting. Tipe lain (misalnya ATK) langsung ke Manager dan Executor.</p>

                    <h3>5.1 Pemohon membuat pengajuan</h3>
                    <x-guide.demo name="pengajuan-buat" caption="Animasi: pemohon memilih tipe, menambah dua barang, lalu mengirim pengajuan." :shots="[
                        'pengajuan-buat-01-daftar-pengajuan.png' => 'Daftar pengajuan',
                        'pengajuan-buat-02-isi-barang.png' => 'Barang pertama',
                        'pengajuan-buat-03-dua-barang.png' => 'Tambah barang',
                        'pengajuan-buat-04-pengajuan-terkirim.png' => 'Pengajuan terkirim',
                        'pengajuan-buat-05-riwayat-approval.png' => 'Riwayat approval',
                    ]" />
                    <ul class="pg-list">
                        <li>Menu <strong>Pengajuan Barang</strong>, tombol <strong>Buat Pengajuan</strong>.</li>
                        <li>Pilih <strong>Tipe Pengajuan</strong>. Tipe yang kuotanya sudah penuh (2 pengajuan terbuka) tidak muncul.</li>
                        <li>Isi <strong>Barang</strong> dan <strong>Jumlah</strong>. Tekan <strong>Tambah barang</strong> untuk baris berikutnya. Bila tipenya mewajibkan, unggah <strong>File Persetujuan Atasan</strong>.</li>
                        <li>Setelah <strong>Buat</strong>, pengajuan mendapat kode <code>REQ…</code> dan empat tahap approval yang masih <em>Belum</em>.</li>
                    </ul>

                    <h3>5.2 Accounting mengisi status PO</h3>
                    <x-guide.demo name="pengajuan-accounting" caption="Animasi: accounting membuka pengajuan, menandai PO sudah terbit, dan mengisi nomor PO." :shots="[
                        'pengajuan-accounting-01-pengajuan-masuk.png' => 'Pengajuan masuk',
                        'pengajuan-accounting-02-isi-status-po.png' => 'Isi status PO',
                        'pengajuan-accounting-03-po-tersimpan.png' => 'PO tersimpan',
                    ]" />
                    <ul class="pg-list">
                        <li>Hanya untuk tipe yang <em>Lewat tahap Accounting</em>. Accounting melihat semua pengajuan dari tipe tersebut.</li>
                        <li>Tombol <strong>Status PO (Accounting)</strong>: nyalakan <em>PO sudah diterbitkan</em>, isi <em>Nomor PO</em>, lalu <strong>Kirim</strong>.</li>
                    </ul>

                    <h3>5.3 Manager menyetujui, executor memproses</h3>
                    <x-guide.demo name="pengajuan-proses" caption="Animasi: executor menyetujui jumlah sebagai manager, lalu memproses pengajuan sampai Selesai." :shots="[
                        'pengajuan-proses-01-pengajuan-masuk.png' => 'Pengajuan masuk',
                        'pengajuan-proses-02-setujui-jumlah.png' => 'Setujui jumlah',
                        'pengajuan-proses-03-disetujui-manager.png' => 'Disetujui manager',
                        'pengajuan-proses-04-keputusan-selesai.png' => 'Keputusan Selesai',
                        'pengajuan-proses-05-status-selesai.png' => 'Status Selesai',
                    ]" />
                    <ul class="pg-list">
                        <li><strong>Setujui Jumlah (Manager)</strong>: jumlah yang belum diisi disetujui sama dengan jumlah diminta. Untuk mengubah jumlah per barang, pakai <strong>Ubah</strong> di tabel <em>Barang yang Diajukan</em> lebih dulu.</li>
                        <li><strong>Proses (Executor)</strong>: pilih <em>Diproses</em> selama barang disiapkan, <em>Selesai</em> saat barang sudah diserahkan, atau <em>Dibatalkan</em>.</li>
                        <li>Pemohon baru bisa konfirmasi setelah executor memilih <em>Selesai</em>.</li>
                    </ul>

                    <h3>5.4 Pemohon mengonfirmasi penerimaan</h3>
                    <x-guide.demo name="pengajuan-konfirmasi" caption="Animasi: pemohon membuka pengajuannya lalu mengonfirmasi barang sudah diterima." :shots="[
                        'pengajuan-konfirmasi-01-menunggu-konfirmasi.png' => 'Menunggu konfirmasi',
                        'pengajuan-konfirmasi-02-form-konfirmasi.png' => 'Form konfirmasi',
                        'pengajuan-konfirmasi-03-diterima.png' => 'Diterima',
                    ]" />
                    <ul class="pg-list">
                        <li>Tombol <strong>Konfirmasi Penerimaan</strong>, pilih <em>Diterima</em>, isi catatan bila perlu, lalu <strong>Kirim</strong>.</li>
                        <li>Status menjadi <em>Diterima</em> dan pengajuan tidak lagi dihitung ke kuota.</li>
                    </ul>
                </div>

                <div class="pg-block" id="bab-6">
                    <h2>6. Lapor Gangguan</h2>
                    <p>Untuk kerusakan di kantor (AC, listrik, dan seterusnya). Pelapor melapor dengan foto, executor memperbaiki, pelapor mengonfirmasi.</p>

                    <h3>6.1 Pelapor membuat laporan</h3>
                    <x-guide.demo name="gangguan-lapor" caption="Animasi: pelapor memilih jenis gangguan, menulis keterangan, dan mengunggah foto." :shots="[
                        'gangguan-lapor-01-daftar-laporan.png' => 'Daftar laporan',
                        'gangguan-lapor-02-form-terisi.png' => 'Form terisi',
                        'gangguan-lapor-03-laporan-terkirim.png' => 'Laporan terkirim',
                    ]" />
                    <ul class="pg-list">
                        <li>Menu <strong>Laporan Gangguan</strong>, tombol <strong>Lapor Gangguan</strong>.</li>
                        <li><strong>Jenis Gangguan</strong>, <strong>Keterangan Gangguan</strong>, dan <strong>Foto Gangguan / Kerusakan</strong> wajib diisi.</li>
                        <li>Satu pelapor hanya boleh punya satu laporan yang belum dikonfirmasi.</li>
                    </ul>

                    <h3>6.2 Executor memproses</h3>
                    <x-guide.demo name="gangguan-proses" caption="Animasi: executor memilih status Selesai, mengunggah foto hasil, dan menulis hasil pengerjaan." :shots="[
                        'gangguan-proses-01-laporan-masuk.png' => 'Laporan masuk',
                        'gangguan-proses-02-form-proses.png' => 'Form proses',
                        'gangguan-proses-03-laporan-selesai.png' => 'Laporan selesai',
                        'gangguan-proses-04-foto-sebelum-sesudah.png' => 'Foto sebelum dan sesudah',
                    ]" />
                    <ul class="pg-list">
                        <li>Tombol <strong>Proses (Executor)</strong>: pilih status, isi <em>Jadwal Pengerjaan</em> bila perlu, unggah <em>Foto Hasil</em>, tulis <em>Hasil Pengerjaan</em>.</li>
                        <li>Memilih <em>Dibatalkan</em> langsung menutup laporan tanpa menunggu konfirmasi pelapor.</li>
                    </ul>

                    <h3>6.3 Pelapor mengonfirmasi</h3>
                    <x-guide.demo name="gangguan-konfirmasi" caption="Animasi: pelapor menulis ulasan lalu menandai laporan selesai." :shots="[
                        'gangguan-konfirmasi-01-menunggu-konfirmasi.png' => 'Menunggu konfirmasi',
                        'gangguan-konfirmasi-02-form-konfirmasi.png' => 'Form konfirmasi',
                        'gangguan-konfirmasi-03-terkonfirmasi.png' => 'Terkonfirmasi',
                    ]" />
                    <ul class="pg-list">
                        <li>Tombol <strong>Konfirmasi Selesai</strong>, ulasan boleh dikosongkan.</li>
                        <li>Bila pelapor tidak mengonfirmasi, sistem mengonfirmasi otomatis 24 jam setelah laporan ditutup.</li>
                    </ul>
                </div>

                <div class="pg-block" id="bab-7">
                    <h2>7. Berita Acara aset: serah terima, pengalihan, pengembalian</h2>
                    <p>Setiap perpindahan aset antar orang dicatat sebagai Berita Acara (BA). Contoh di bawah mengikuti satu laptop: dikeluarkan dari stok ke Budi, dialihkan ke Putri, lalu dikembalikan ke GA.</p>
                    <h3>7.1 Serah terima: aset keluar dari stok</h3>
                    <x-guide.demo name="ba-serah-terima" caption="Animasi: membuat BA Serah Terima dari staf GA ke penerima, memilih laptop, lalu menyimpan." :shots="[
                        'ba-serah-terima-01-daftar-ba.png' => 'Daftar BA',
                        'ba-serah-terima-02-informasi-ba.png' => 'Informasi BA',
                        'ba-serah-terima-03-aset-diserahkan.png' => 'Aset yang diserahkan',
                        'ba-serah-terima-04-ba-tersimpan.png' => 'BA tersimpan',
                    ]" />
                    <ul class="pg-list">
                        <li>Menu <strong>Aset Transfer</strong>, tombol <strong>Buat Aset Transfer</strong>.</li>
                        <li><strong>Jenis Berita Acara</strong>: <em>Serah Terima</em> (dari stok), <em>Pengalihan</em> (antar pemegang), atau <em>Pengembalian</em> (kembali ke stok).</li>
                        <li><strong>Nomor Surat</strong> terisi otomatis setelah Badan Usaha dipilih. Pihak GA harus user dengan role <code>general_affair</code>.</li>
                        <li>Daftar aset baru muncul setelah jenis dan pihak pertama dipilih. Untuk serah terima, hanya aset <em>Tersedia</em> tanpa pemegang yang bisa dipilih.</li>
                        <li>Setelah disimpan, aset menjadi <em>Digunakan</em> oleh penerima. Tombol <strong>Download PDF</strong> mencetak BA untuk ditandatangani; unggah hasil scan lewat <strong>Ubah</strong>.</li>
                    </ul>

                    <h3>7.2 Pengalihan: pindah ke pemegang lain</h3>
                    <x-guide.demo name="ba-pengalihan" caption="Animasi: BA Pengalihan dari Budi ke Putri; laptop yang dipegang Budi otomatis masuk daftar." :shots="[
                        'ba-pengalihan-01-aset-pemegang-terisi.png' => 'Aset pemegang terisi',
                        'ba-pengalihan-02-ba-tersimpan.png' => 'BA tersimpan',
                    ]" />
                    <ul class="pg-list">
                        <li>Pilih <strong>Dari Pemegang</strong>: semua aset yang sedang ia pegang langsung terisi di <strong>Details</strong>. Hapus baris aset yang tidak ikut dialihkan.</li>
                        <li>Aset tetap <em>Digunakan</em>, pemegangnya berganti.</li>
                    </ul>

                    <h3>7.3 Pengembalian: aset kembali ke stok</h3>
                    <x-guide.demo name="ba-pengembalian" caption="Animasi: BA Pengembalian dari Putri ke staf GA, lalu laptop kembali Tersedia di daftar aset." :shots="[
                        'ba-pengembalian-01-form-pengembalian.png' => 'Form pengembalian',
                        'ba-pengembalian-02-ba-tersimpan.png' => 'BA tersimpan',
                        'ba-pengembalian-03-laptop-kembali-tersedia.png' => 'Aset kembali Tersedia',
                    ]" />
                    <ul class="pg-list">
                        <li>Pihak penerima adalah <strong>Staf GA yang Menerima</strong> (role <code>general_affair</code>).</li>
                        <li>Setelah disimpan, aset menjadi <em>Tersedia</em> tanpa pemegang dan bisa diserahkan lagi lewat BA Serah Terima.</li>
                    </ul>
                </div>

                <div class="pg-block" id="bab-8">
                    <h2>8. Servis aset</h2>
                    <x-guide.demo name="servis" caption="Animasi: mencatat servis motor dengan teknisi internal, lalu menyelesaikannya dengan biaya dan tindakan perbaikan." :shots="[
                        'servis-01-form-servis.png' => 'Form servis',
                        'servis-02-servis-tercatat.png' => 'Servis tercatat',
                        'servis-03-form-selesaikan.png' => 'Form selesaikan',
                        'servis-04-servis-selesai.png' => 'Servis selesai',
                    ]" />
                    <ul class="pg-list">
                        <li>Menu <strong>Servis Aset</strong>, tombol <strong>Tambah Servis Aset</strong>. Isi aset, pelaksana (internal atau vendor), dan kendala.</li>
                        <li>Saat servis selesai, tekan <strong>Selesaikan</strong> di baris tabel: isi tanggal selesai, biaya, tindakan perbaikan, dan bila ada rincian suku cadang serta foto nota.</li>
                    </ul>
                </div>

                <div class="pg-block" id="bab-9">
                    <h2>9. OB: menugaskan dan mengerjakan</h2>
                    <h3>9.1 Atasan menugaskan per shift</h3>
                    <x-guide.demo name="ob-tugaskan" caption="Animasi: memilih Shift Pagi, ruangannya tercentang otomatis, lalu menugaskan ke satu OB." :shots="[
                        'ob-tugaskan-01-daftar-tugas.png' => 'Daftar tugas',
                        'ob-tugaskan-02-form-tugaskan.png' => 'Form tugaskan',
                        'ob-tugaskan-03-tugas-dibuat.png' => 'Tugas dibuat',
                    ]" />
                    <ul class="pg-list">
                        <li>Menu <strong>OB Checksheets</strong>, tombol <strong>Tugaskan per Shift</strong>. Ruangan dan shift diatur di menu <strong>Ruangan OB</strong> dan <strong>Shift OB</strong>.</li>
                        <li>Kosongkan <strong>Petugas OB</strong> agar tugas masuk kolam bersama dan bisa diambil OB mana pun.</li>
                    </ul>

                    <h3>9.2 OB memotret sebelum dan sesudah</h3>
                    <x-guide.demo name="ob-kerja" caption="Animasi: OB mengambil foto sebelum, lalu foto sesudah, dan tugas menjadi Selesai." :shots="[
                        'ob-kerja-01-tugas-hari-ini.png' => 'Tugas hari ini',
                        'ob-kerja-02-foto-sebelum.png' => 'Foto sebelum',
                        'ob-kerja-03-sedang-dikerjakan.png' => 'Sedang dikerjakan',
                        'ob-kerja-04-foto-sesudah.png' => 'Foto sesudah',
                        'ob-kerja-05-selesai.png' => 'Selesai',
                    ]" />
                    <ul class="pg-list">
                        <li><strong>Mulai Semua &amp; Foto Sebelum</strong>: foto tiap ruangan yang mulai dikerjakan. Ruangan tanpa foto tetap <em>Belum Dikerjakan</em>.</li>
                        <li><strong>Selesaikan Semua &amp; Foto Sesudah</strong>: foto hasil, status menjadi <em>Selesai</em> dan durasi tercatat.</li>
                        <li>Di HP, tombol <strong>Buka Kamera</strong> memotret langsung. <strong>Pilih dari Galeri</strong> memakai foto yang sudah ada. Tekan <strong>Gunakan Foto Ini</strong> sampai muncul tanda <em>Tersimpan</em>.</li>
                    </ul>
                </div>

                <div class="pg-block" id="bab-10">
                    <h2>10. Checksheet kendaraan</h2>
                    <x-guide.demo name="kendaraan" caption="Animasi: mencatat keberangkatan motor, lalu mengubah checksheet yang sama saat kendaraan kembali." :shots="[
                        'kendaraan-01-informasi-kendaraan.png' => 'Informasi kendaraan',
                        'kendaraan-02-keberangkatan.png' => 'Keberangkatan',
                        'kendaraan-03-berangkat-tersimpan.png' => 'Berangkat tersimpan',
                        'kendaraan-04-pengembalian.png' => 'Pengembalian',
                        'kendaraan-05-kembali-tersimpan.png' => 'Durasi dan jarak',
                    ]" />
                    <ul class="pg-list">
                        <li>Menu <strong>Vehicle Checksheet</strong>, tombol <strong>Buat</strong>. Isi plat, PIC, lokasi, tujuan, kilometer awal, foto, dan laporan kerusakan saat berangkat.</li>
                        <li>Pilihan <strong>Plat Nomor</strong> diambil dari atribut aset bernama <em>Plat Nomor</em>. Plat yang belum muncul berarti atribut itu belum diisi di asetnya.</li>
                        <li>Saat kembali, buka checksheet lewat <strong>Ubah</strong> dan isi bagian <strong>Informasi Pengembalian</strong>. Durasi dan jarak tempuh dihitung otomatis.</li>
                    </ul>
                </div>

                <div class="pg-block" id="bab-11">
                    <h2>11. Menambah pengguna dan memberi role</h2>
                    <x-guide.demo name="user-baru" caption="Animasi: admin membuat pengguna baru dengan badan usaha, divisi, username, dan role requester." :shots="[
                        'user-baru-01-daftar-pengguna.png' => 'Daftar pengguna',
                        'user-baru-02-informasi-pengguna.png' => 'Informasi pengguna',
                        'user-baru-03-akses-dan-role.png' => 'Akses dan role',
                        'user-baru-04-pengguna-tersimpan.png' => 'Pengguna tersimpan',
                    ]" />
                    <ul class="pg-list">
                        <li>Menu <strong>Organisasi</strong>, <strong>Pengguna</strong>, tombol <strong>Buat Pengguna</strong>.</li>
                        <li>Tanpa role, pengguna tidak bisa masuk. Role menentukan menu yang tampil (lihat tab <strong>Role dan akses</strong>).</li>
                        <li>Untuk pengajuan barang, isi juga <strong>Divisi</strong> dan <strong>Atasan (approver pengajuan barang)</strong>. Untuk login WhatsApp, isi <strong>Nomor WhatsApp</strong>.</li>
                        <li>Data karyawan sebaiknya masuk lewat tombol <strong>Import JSON (Talenta)</strong>, satu-satunya import pengguna. Baris dicocokkan lewat Employee ID, lalu nama; bila ada beberapa pengguna bernama sama, dipersempit dengan nomor HP, email, badan usaha, dan jabatan.</li>
                        <li>Pengguna yang punya <strong>Employee ID</strong> mengikuti Talenta: Nama, Employee ID, Nomor WhatsApp, Email, Badan Usaha, dan Jabatan terkunci di form. Ubah di Talenta lalu import ulang JSON-nya. Divisi, atasan, username, kata sandi, dan role tetap diatur di Shelf.</li>
                        <li>Halaman pengguna punya tab <strong>Aset Dipegang</strong>, <strong>Berita Acara (BA)</strong>, <strong>Pengajuan Barang</strong>, <strong>Approval Pengajuan</strong>, dan <strong>Laporan Gangguan</strong> untuk melihat riwayatnya.</li>
                    </ul>
                </div>

                <div class="pg-block" id="bab-12">
                    <h2>12. Import &amp; Laporan Audit (contoh: kendaraan)</h2>
                    <p>Hasil audit lapangan dicocokkan dengan data Shelf lewat file Excel. Shelf baru berubah setelah koreksi diterapkan, jadi laporan bisa ditinjau dulu dengan aman. Contoh: auditor menemukan satu mobil yang belum tercatat.</p>
                    <x-guide.demo name="audit-kendaraan" caption="Animasi: impor file audit kendaraan, meninjau laporan hasil banding, lalu menerapkan koreksi sehingga mobil baru tercatat." :shots="[
                        'audit-kendaraan-01-daftar-audit.png' => 'Daftar batch audit',
                        'audit-kendaraan-02-tombol-export-format.png' => 'Export format',
                        'audit-kendaraan-03-form-import.png' => 'Form import',
                        'audit-kendaraan-04-laporan-hasil-banding.png' => 'Laporan hasil banding',
                        'audit-kendaraan-05-laporan-per-kendaraan.png' => 'Laporan per kendaraan',
                        'audit-kendaraan-06-konfirmasi-terapkan.png' => 'Konfirmasi terapkan',
                        'audit-kendaraan-07-koreksi-diterapkan.png' => 'Koreksi diterapkan',
                        'audit-kendaraan-08-aset-baru-dari-audit.png' => 'Aset baru dari audit',
                    ]" />
                    <ul class="pg-list">
                        <li><strong>0. Export Format</strong>: di halaman <strong>1. Import Audit</strong>, unduh format <em>Audit Kendaraan</em> (atau <em>CSA</em> untuk stok ritel). File sudah berisi aset yang tercatat di Shelf.</li>
                        <li><strong>Isi di Excel</strong>: lengkapi hasil cek lapangan (keberadaan, BPKB, tanggal STNK/KIR/pajak), tambahkan baris unit yang belum tercatat, dan tulis <em>TERJUAL</em> di kolom ACCOUNTING untuk unit yang sudah dijual. Jangan ubah kolom NOMOR POLISI kecuali koreksi plat.</li>
                        <li><strong>1. Import Audit</strong>: pilih <em>Jenis Audit</em>, <em>Badan Usaha Default</em>, unggah file. Nyalakan <em>Buat aset/lokasi baru dari audit</em> bila unit baru boleh dibuat otomatis.</li>
                        <li><strong>2. Laporan Hasil Banding</strong>: tiap baris berstatus <em>Inline</em> (sudah sama), <em>Gap</em> (akan dikoreksi), atau <em>Terblokir</em> (perlu dicek manual, misalnya plat tidak valid atau muncul dua kali).</li>
                        <li><strong>3. Terapkan Koreksi</strong>: hanya bisa bila tidak ada baris Terblokir. Aset tidak pernah dihapus; duplikat dinonaktifkan. Pakai <strong>Compare Ulang</strong> untuk memastikan semua sudah Inline.</li>
                    </ul>
                    <div class="pg-note"><p>Unit kendaraan dicocokkan lewat atribut <em>Plat Nomor</em>. Aset kategori MOBIL/MOTOR tanpa plat tidak ikut dalam file ekspor.</p></div>
                </div>

                <div class="pg-block" id="bab-13">
                    <h2>13. Asuransi, Sewa, dan pengingat WhatsApp</h2>
                    <h3>13.1 Mencatat polis asuransi</h3>
                    <x-guide.demo name="asuransi" caption="Animasi: membuat polis asuransi stok dengan periode, status, dan nomor WA pengingat." :shots="[
                        'asuransi-01-daftar-asuransi.png' => 'Daftar asuransi',
                        'asuransi-02-polis-dan-nilai.png' => 'Polis dan nilai',
                        'asuransi-03-periode-dan-pengingat.png' => 'Periode dan pengingat',
                        'asuransi-04-asuransi-tersimpan.png' => 'Asuransi tersimpan',
                    ]" />
                    <ul class="pg-list">
                        <li>Menu <strong>Asuransi &amp; Sewa</strong>, <strong>Asuransi</strong>, tombol <strong>Buat Asuransi</strong>. Wajib: Nomor Polis, Nama Tertanggung, Tanggal Mulai, Tanggal Berakhir.</li>
                        <li><strong>Nomor WA Pengingat</strong>: pisahkan dengan koma. Bila kosong, pengingat dikirim ke nomor global di Notifikasi WA.</li>
                        <li>Saat polis diperpanjang, buka detailnya lalu <strong>Tambah Pembaharuan</strong>. Data lama bisa diimpor sekaligus lewat <strong>Impor Excel</strong>.</li>
                    </ul>

                    <h3>13.2 Mencatat perjanjian sewa</h3>
                    <x-guide.demo name="sewa" caption="Animasi: membuat perjanjian sewa ruko, lalu kontrak yang hampir habis muncul di dasbor." :shots="[
                        'sewa-01-data-kontrak.png' => 'Data kontrak',
                        'sewa-02-pembayaran-dan-dokumen.png' => 'Pembayaran dan dokumen',
                        'sewa-03-sewa-tersimpan.png' => 'Sewa tersimpan',
                        'sewa-04-dasbor-kontrak-berakhir.png' => 'Dasbor: jatuh tempo',
                    ]" />
                    <ul class="pg-list">
                        <li>Menu <strong>Perjanjian Sewa</strong>, tombol <strong>Buat Perjanjian Sewa</strong>. Wajib: Kode Sewa, Objek Sewa, Pihak Pertama, Pihak Kedua, dan kedua tanggal.</li>
                        <li>Bagian <strong>Dokumen</strong> mencatat ada tidaknya bukti potong dan dokumen perjanjian, plus file scan-nya.</li>
                        <li>Dasbor menampilkan tabel <strong>Asuransi/Sewa Terdekat Jatuh Tempo</strong>. Perpanjangan dicatat lewat <strong>Tambah Perpanjangan</strong> di detail sewa.</li>
                    </ul>

                    <h3>13.3 Mengatur pengingat WhatsApp</h3>
                    <x-guide.demo name="wa-pengaturan" caption="Animasi: membuka Pengaturan, Notifikasi WA, berisi nomor global, jam kirim, dan jadwal pengingat." :shots="[
                        'wa-pengaturan-01-pengaturan-umum.png' => 'Pengaturan umum',
                        'wa-pengaturan-02-jenis-pengingat.png' => 'Jenis pengingat',
                    ]" />
                    <ul class="pg-list">
                        <li>Menu <strong>Pengaturan</strong>, <strong>Notifikasi WA</strong>. <strong>Jam kirim</strong>: pengingat dikirim sekali sehari setelah jam ini.</li>
                        <li><strong>Hari sebelum</strong>: daftar H-n dipisah koma (default 30,14,7,1), terpisah untuk sewa, asuransi, dan cutoff pengajuan barang.</li>
                        <li><strong>Kirim Uji Coba</strong> mengetes koneksi WhatsApp. <strong>Ganti Nomor</strong> mengganti satu nomor di semua kontrak sekaligus.</li>
                    </ul>
                </div>

                <div class="pg-block" id="bab-14">
                    <h2>14. Pekerjaan Vendor</h2>
                    <p>Pekerjaan yang dikerjakan pihak luar (servis AC, perbaikan gedung, dan sejenisnya) dicatat sebagai Task. Statusnya berjalan <em>open</em>, <em>in_progress</em>, lalu <em>completed</em>, dan diakhiri dengan berita acara yang ditandatangani. Contoh: servis AC ruang meeting oleh CV Sumber Teknik.</p>
                    <x-guide.demo name="vendor-tugas" caption="Animasi: membuat task, memprosesnya, menyelesaikan dengan foto hasil, lalu tombol unduh dan unggah berita acara." :shots="[
                        'vendor-tugas-01-daftar-tugas.png' => 'Daftar task',
                        'vendor-tugas-02-informasi-umum.png' => 'Informasi umum',
                        'vendor-tugas-03-pilih-vendor.png' => 'Pilih vendor',
                        'vendor-tugas-04-tugas-tersimpan.png' => 'Task tersimpan',
                        'vendor-tugas-05-status-in-progress.png' => 'Diproses',
                        'vendor-tugas-06-form-selesai.png' => 'Foto hasil',
                        'vendor-tugas-07-status-completed.png' => 'Selesai',
                        'vendor-tugas-08-aksi-download-upload.png' => 'Download dan Upload',
                    ]" />
                    <ul class="pg-list">
                        <li>Menu <strong>Pekerjaan Vendor</strong>, <strong>Task</strong>, tombol <strong>Buat Task</strong>. Isi Nama Pekerjaan, Biaya, waktu kerja (default sekarang), Deskripsi, Lokasi, Entitas Bisnis, dan PIC (default Anda sendiri).</li>
                        <li><strong>Vendor</strong>: pilih dari daftar. Bila belum ada, tekan tombol <strong>+</strong> di samping pilihan untuk membuat vendor baru langsung dari form.</li>
                        <li>Nomor surat dibuat otomatis per badan usaha per tahun, contoh <code>001/BAP/CV BERSAMA CS/GA/2026</code>. Mengganti badan usaha membuat nomor baru.</li>
                        <li>Di tabel, buka menu titik tiga pada baris task. <strong>Process</strong> mengubah status menjadi <em>in_progress</em>. Task hanya bisa diedit selama masih <em>open</em>.</li>
                        <li><strong>Complete</strong>: unggah 1 sampai 5 foto hasil pekerjaan (gambar, maks 2 MB per foto). Status menjadi <em>completed</em> dan foto tampil di detail task.</li>
                        <li><strong>Download</strong> mencetak PDF berita acara penyelesaian untuk ditandatangani. Setelah ditandatangani, unggah scan-nya lewat <strong>Upload</strong> (PDF atau Word, maks 5 MB). Sesudah dokumen diunggah, kedua tombol hilang dan dokumen dibuka dari kolom <em>Dokumen</em> di tabel.</li>
                    </ul>
                </div>

                <div class="pg-block" id="bab-15">
                    <h2>15. Import &amp; Laporan Audit stok (CSA)</h2>
                    <p>Sama seperti bab 12, tetapi untuk stok inventori per gudang yang dihitung dalam jumlah (qty). Contoh: hasil stock opname menemukan kertas thermal tinggal 8 rol (Shelf mencatat 10) dan 6 tinta printer yang belum tercatat.</p>
                    <x-guide.demo name="audit-csa" caption="Animasi: impor file CSA hasil stock opname, meninjau laporan per barang, lalu menerapkan koreksi qty dan barang baru." :shots="[
                        'audit-csa-01-tombol-export-format.png' => 'Export format CSA',
                        'audit-csa-02-form-import.png' => 'Form import',
                        'audit-csa-03-laporan-hasil-banding.png' => 'Laporan hasil banding',
                        'audit-csa-04-laporan-per-barang.png' => 'Laporan per barang',
                        'audit-csa-05-konfirmasi-terapkan.png' => 'Konfirmasi terapkan',
                        'audit-csa-06-koreksi-diterapkan.png' => 'Koreksi diterapkan',
                    ]" />
                    <ul class="pg-list">
                        <li><strong>0. Export Format CSA</strong>: berisi sheet <em>ASET</em>, satu blok per gudang, dengan aset inventori aktif yang punya lokasi. Kolom <em>Qty Akhir</em> adalah saldo Shelf saat export. Jangan ubah nama sheet dan header.</li>
                        <li><strong>Isi di Excel</strong>: tulis hasil hitung di <em>Fisik</em>. <em>Selisih</em> boleh dikosongkan (dihitung Fisik dikurangi Qty Akhir); bila diisi, harus cocok. Barang yang belum tercatat ditambahkan sebagai baris baru di blok gudangnya.</li>
                        <li><strong>1. Import Audit</strong>: Jenis Audit <em>CSA (stok ritel / inventori)</em>, pilih Badan Usaha Default, unggah file. Judul blok bertanda <em>(CSA CSN)</em> mengarahkan baris ke badan usaha dengan alias itu. Opsi <em>Buat lokasi baru</em> menyala secara default untuk kode gudang yang belum dikenal.</li>
                        <li><strong>2. Laporan</strong>: barang dicocokkan lewat S/N, lalu kode item + gudang, lalu nama + gudang. Qty berbeda atau barang baru menjadi <em>Gap</em>; kode gudang kosong, data duplikat, atau qty tidak konsisten menjadi <em>Terblokir</em>.</li>
                        <li><strong>3. Terapkan Koreksi</strong>: qty aset disesuaikan, barang baru dibuat sebagai aset di gudangnya. Barang dengan fisik 0 dinonaktifkan dari inventori (tidak dihapus). Jalankan <strong>Compare Ulang</strong> untuk memastikan semua sudah Inline.</li>
                    </ul>
                </div>
            </section>

            {{-- ============ LOGIN ============ --}}
            <section x-show="tab === 'login'" x-cloak class="pg-body" style="padding-top: 0">
                <div class="pg-block">
                    <h2>Masuk ke aplikasi</h2>
                    <p class="pg-lead">Ada dua cara masuk. Pendaftaran sendiri dan reset password dimatikan, jadi akun dibuat dan dikelola admin.</p>
                </div>
                <div class="pg-block">
                    <h3>1. Username atau email dan kata sandi</h3>
                    <p>Buka <code>/admin/login</code>. Lupa kata sandi: minta admin mengubahnya di Organisasi, Pengguna.</p>
                </div>
                <div class="pg-block">
                    <h3>2. Kode OTP lewat WhatsApp</h3>
                    <x-guide.flow :steps="$flows['login']" />
                    <div class="pg-note"><p>Admin mengisi <strong>Nomor WhatsApp login</strong> di form user (perlu izin <code>manage_access_user</code>). Nomor harus unik. Nomor kontak biasa tidak bisa dipakai untuk login.</p></div>
                </div>
            </section>

            {{-- ============ PENGAJUAN ============ --}}
            <section x-show="tab === 'pengajuan'" x-cloak class="pg-body" style="padding-top: 0">
                <div class="pg-block">
                    <h2>Pengajuan Barang</h2>
                    <p class="pg-lead">Karyawan meminta barang (ATK, perlengkapan, dan seterusnya). Pengajuan melewati persetujuan bertahap sampai barang diterima. Menu: Pengajuan Barang, Pengajuan.</p>
                </div>

                <div class="pg-block">
                    <h3>Alur lengkap</h3>
                    <x-guide.flow :steps="$flows['request']" />
                </div>

                <div class="pg-block">
                    <h3>Status pengajuan</h3>
                    <div class="pg-track" aria-label="Urutan status">
                        @foreach (['Menunggu', 'Diproses', 'Selesai', 'Diterima'] as $k => $label)
                            <span class="pg-pill"><span class="pg-pill-on" style="--k: {{ $k }}"></span><span>{{ $label }}</span></span>
                            @unless ($loop->last)<span class="pg-arrow" aria-hidden="true">→</span>@endunless
                        @endforeach
                    </div>
                    <div class="pg-scroll">
                        <table class="pg-table">
                            <thead><tr><th>Status</th><th>Arti</th><th>Kode lama sumo</th></tr></thead>
                            <tbody>
                                <tr><td>Menunggu</td><td>Baru dibuat atau dikembalikan ke Pending oleh executor</td><td>0</td></tr>
                                <tr><td>Diterima</td><td>Pemohon sudah mengonfirmasi menerima barang</td><td>1</td></tr>
                                <tr><td>Dibatalkan</td><td>Dibatalkan pemilik, atau oleh executor</td><td>2</td></tr>
                                <tr><td>Diproses</td><td>Executor sedang mengerjakan</td><td>3</td></tr>
                                <tr><td>Selesai</td><td>Executor menutup, menunggu konfirmasi pemohon</td><td>4</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="pg-lead">Pengajuan "terbuka" adalah yang berstatus Menunggu, Diproses, atau Selesai. Hanya yang terbuka dihitung ke batas 2 per tipe.</p>
                </div>

                <div class="pg-block">
                    <h3>Siapa melihat apa</h3>
                    <ul class="pg-list">
                        <li>Pemohon melihat pengajuannya sendiri.</li>
                        <li>Atasan (kolom "approver" di user) melihat pengajuan bawahannya.</li>
                        <li>Accounting melihat semua pengajuan dari tipe yang butuh Accounting.</li>
                        <li>Executor, manager, dan auditor melihat sesuai cakupan divisi, tipe, dan area (tabel <code>item_request_scopes</code>). Cakupan kosong berarti semua.</li>
                        <li>Izin <code>view_all</code> melihat semuanya.</li>
                    </ul>
                </div>

                <div class="pg-block">
                    <h3>Fitur pendukung</h3>
                    <ul class="pg-list">
                        <li><strong>Cetak QR:</strong> satu label per barang dalam pengajuan. Isi QR adalah id detail barang, sama seperti label lama sumo.</li>
                        <li><strong>Scan QR:</strong> menu Scan QR Pengajuan dan Scan QR Barang membuka info dari kode yang dipindai.</li>
                        <li><strong>Log:</strong> mencatat pengajuan atas nama orang lain, pembatalan, dan penggantian pemohon. Tidak bisa diedit.</li>
                        <li><strong>Tipe pengajuan:</strong> tiap tipe punya syarat sendiri: wajib lampiran, wajib ada barang, butuh Accounting.</li>
                        <li><strong>Cutoff bulanan:</strong> pengaturan periode buka dan tutup pengajuan. Dipakai untuk pengingat WhatsApp.</li>
                    </ul>
                    <div class="pg-note pg-warn"><p>Stok barang hanya kolom yang diisi manual di menu Barang. Sistem belum mengurangi stok otomatis saat pengajuan selesai.</p></div>
                </div>
            </section>

            {{-- ============ GANGGUAN ============ --}}
            <section x-show="tab === 'gangguan'" x-cloak class="pg-body" style="padding-top: 0">
                <div class="pg-block">
                    <h2>Lapor Gangguan</h2>
                    <p class="pg-lead">Karyawan melaporkan kerusakan atau gangguan (AC, listrik, jaringan, dan sebagainya) lengkap dengan foto. Tim executor mengerjakan, lalu pelapor memastikan sudah beres.</p>
                </div>
                <x-guide.flow :steps="$flows['problem']" />
                <div class="pg-block">
                    <h3>Aturan penting</h3>
                    <ul class="pg-list">
                        <li>Pelapor melihat laporannya sendiri. Izin <code>view_all</code> melihat semua.</li>
                        <li>Divisi HCM hanya melihat kategori UMUM (diatur di <code>config/problem-report.php</code>).</li>
                        <li>Kategori dikelola di menu Lapor Gangguan, Kategori.</li>
                    </ul>
                </div>
            </section>

            {{-- ============ ASET ============ --}}
            <section x-show="tab === 'aset'" x-cloak class="pg-body" style="padding-top: 0">
                <div class="pg-block">
                    <h2>Aset dan Berita Acara (BA)</h2>
                    <p class="pg-lead">Setiap aset punya kondisi dan pemegang. Perpindahan pemegang selalu lewat dokumen BA, supaya riwayatnya bisa ditelusuri.</p>
                </div>
                <div class="pg-block">
                    <h3>Perjalanan sebuah aset</h3>
                    <x-guide.flow :steps="$flows['asset']" />
                </div>
                <div class="pg-block">
                    <h3>Kondisi aset</h3>
                    <p>Tersedia, Digunakan, Dijual, Hilang, Rusak. Aset Rusak punya proses NBH (pending lalu selesai) sampai perbaikan divalidasi.</p>
                </div>
                <div class="pg-block">
                    <h3>Aturan BA</h3>
                    <ul class="pg-list">
                        <li>Jenis dokumen dipilih saat BA dibuat: <strong>BA</strong> serah terima, <strong>BAPAB</strong> pengalihan, <strong>BAPEB</strong> pengembalian.</li>
                        <li>Staf GA tidak bisa membuat BA atas nama staf GA lain. Pengecualian: super admin dan pemegang izin <strong>Kelola BA Stok</strong>.</li>
                        <li>BA dibuat dalam satu transaksi. Jika aturan menolak, BA ikut dibatalkan dan form bisa dikirim ulang.</li>
                        <li>Menyimpan ulang BA (misalnya mengunggah scan yang sudah ditandatangani) tidak memindahkan aset lagi.</li>
                        <li>PDF BA bisa diunduh dari BA itu (perlu login dan izin lihat).</li>
                    </ul>
                </div>
                <div class="pg-block">
                    <h3>Lainnya di grup Aset</h3>
                    <ul class="pg-list">
                        <li><strong>Rekonsiliasi:</strong> unggah workbook CSA atau audit kendaraan untuk dibandingkan dengan data Shelf. Data baru berubah hanya setelah menekan Terapkan Koreksi.</li>
                        <li><strong>Label QR:</strong> tiap aset punya QR. Pemindaian publik membuka halaman info aset.</li>
                        <li><strong>Atribut kustom:</strong> dokumen bermasa berlaku (STNK, KIR). Dokumen yang hampir kedaluwarsa dikirim sebagai pengingat.</li>
                    </ul>
                </div>
            </section>

            {{-- ============ OB ============ --}}
            <section x-show="tab === 'ob'" x-cloak class="pg-body" style="padding-top: 0">
                <div class="pg-block">
                    <h2>OB, Kendaraan, dan Pekerjaan Vendor</h2>
                </div>
                <div class="pg-block">
                    <h3>OB / Kebersihan</h3>
                    <p class="pg-lead">Office boy mencatat pekerjaan bersih-bersih per ruangan dan shift, dengan foto sebelum dan sesudah.</p>
                    <x-guide.flow :steps="$flows['ob']" />
                    <p>Ruangan dan shift adalah master per lokasi. Satu shift bisa mencakup beberapa ruangan. Data bisa diexport.</p>
                </div>
                <div class="pg-block">
                    <h3>Kendaraan</h3>
                    <p>Satu baris per pemakaian: kendaraan (terhubung ke data aset), nomor referensi, PIC, plat, tujuan, KM awal dan akhir, jam berangkat dan kembali, foto, serta laporan kerusakan. Jarak dan durasi dihitung otomatis. Tersedia import dari Excel.</p>
                </div>
                <div class="pg-block">
                    <h3>Pekerjaan Vendor</h3>
                    <p>Mencatat pekerjaan yang dikerjakan vendor: kode, vendor, biaya, lokasi, waktu kerja, lampiran, dan dokumen. Status: open, in progress, completed. PDF penyelesaian tugas bisa diunduh.</p>
                </div>
            </section>

            {{-- ============ KONTRAK ============ --}}
            <section x-show="tab === 'kontrak'" x-cloak class="pg-body" style="padding-top: 0">
                <div class="pg-block">
                    <h2>Asuransi, Sewa, dan Pengingat WhatsApp</h2>
                    <p class="pg-lead">Polis asuransi dan kontrak sewa punya tanggal berakhir. Shelf mengingatkan lewat WhatsApp sebelum jatuh tempo.</p>
                </div>
                <div class="pg-block">
                    <h3>Data kontrak</h3>
                    <ul class="pg-list">
                        <li>Status: Berjalan, Pembaharuan, Tutup, Refund.</li>
                        <li>Setiap perpanjangan dicatat sebagai riwayat. Tanggal berakhir efektif mengikuti riwayat terakhir.</li>
                        <li>Tiap kontrak menyimpan nomor WhatsApp pengingatnya sendiri. Import dan export Excel tersedia.</li>
                    </ul>
                </div>
                <div class="pg-block">
                    <h3>Cara pengingat bekerja</h3>
                    <x-guide.flow :steps="$flows['reminder']" />
                </div>
                <div class="pg-block">
                    <h3>Pengaturan (menu Pengaturan, Notifikasi WhatsApp)</h3>
                    <ul class="pg-list">
                        <li>Aktif atau mati, nomor global, dan jam kirim (default 08:00).</li>
                        <li>Pengingat sewa, asuransi, dan cutoff pengajuan masing-masing punya daftar hari sebelum jatuh tempo.</li>
                        <li><strong>Kirim Uji Coba</strong> mengirim pesan contoh. <strong>Ganti Nomor</strong> mengganti satu nomor di semua kontrak sekaligus.</li>
                        <li>Dua puluh kiriman terakhir tampil di halaman yang sama.</li>
                    </ul>
                    <div class="pg-note"><p>Pengingat hanya berjalan jika scheduler aktif di server dan <code>WAG_URL</code> serta <code>WAG_TOKEN</code> terisi.</p></div>
                </div>
            </section>

            {{-- ============ AKSES ============ --}}
            <section x-show="tab === 'akses'" x-cloak class="pg-body" style="padding-top: 0">
                <div class="pg-block">
                    <h2>Role dan akses</h2>
                    <p class="pg-lead">Masuk ke panel butuh minimal satu role. Apa yang bisa dilakukan ditentukan permission pada role itu.</p>
                </div>
                <div class="pg-scroll">
                    <table class="pg-table">
                        <thead><tr><th>Role</th><th>Untuk siapa</th><th>Bisa apa</th></tr></thead>
                        <tbody>
                            <tr><td><code>super_admin</code></td><td>Pemegang sistem</td><td>Semua, termasuk semua badan usaha dan mengelola akses user.</td></tr>
                            <tr><td><code>pengajuan_admin</code></td><td>Admin pengajuan</td><td>Semua izin pengajuan, laporan gangguan, dan master terkait.</td></tr>
                            <tr><td><code>accounting</code></td><td>Tim Accounting</td><td>Melihat dan menyetujui tahap Accounting, export, melihat master.</td></tr>
                            <tr><td><code>executor</code></td><td>Pelaksana</td><td>Memproses pengajuan dan laporan gangguan, setujui sebagai manager, ganti pemohon, export, scan QR.</td></tr>
                            <tr><td><code>requester</code></td><td>Karyawan biasa</td><td>Membuat dan melihat pengajuan serta laporan miliknya, melihat barang.</td></tr>
                            <tr><td><code>office_boy</code></td><td>OB</td><td>Melihat dan membuat OB checksheet.</td></tr>
                            <tr><td><code>general_affair</code>, <code>admin</code>, <code>audit</code></td><td>Operasional lama Shelf</td><td>Aset, BA, servis, export. Role ini dibuat manual, tidak otomatis ada.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="pg-block">
                    <h3>Pembatasan badan usaha</h3>
                    <ul class="pg-list">
                        <li>Super admin: semua badan usaha.</li>
                        <li>User dengan flag <strong>Akses semua badan usaha</strong>: semua, termasuk yang dibuat belakangan.</li>
                        <li>User lain: badan usaha asalnya ditambah yang dicentang di form user.</li>
                    </ul>
                </div>
                <div class="pg-block">
                    <h3>Cara memberi akses ke karyawan baru</h3>
                    <ul class="pg-list">
                        <li>Organisasi, Pengguna: import JSON Talenta atau buat user manual, lalu isi divisi dan atasan (approver). Untuk user dari Talenta, badan usaha dan jabatan mengikuti Talenta.</li>
                        <li>Isi Nomor WhatsApp login bila ingin masuk lewat OTP.</li>
                        <li>Beri role yang sesuai. Tanpa role, user tidak bisa masuk <code>/admin</code>.</li>
                        <li>Untuk role baru atau penyesuaian: menu Roles (Shield), centang permission yang dibutuhkan.</li>
                    </ul>
                </div>
            </section>

            {{-- ============ DEVELOPER ============ --}}
            <section x-show="tab === 'developer'" x-cloak class="pg-body" style="padding-top: 0">
                <div class="pg-block">
                    <h2>Untuk developer dan yang memegang server</h2>
                    <p class="pg-lead">Laravel 12 dan Filament 4, MySQL, Redis untuk antrean (Horizon), PDF dengan DomPDF, notifikasi lewat gateway WhatsApp (WagHub).</p>
                </div>

                <div class="pg-block">
                    <h3>Jalur sebuah permintaan</h3>
                    <x-guide.flow :steps="$flows['arch']" />
                </div>

                <div class="pg-block">
                    <h3>Peta kode</h3>
                    <div class="pg-scroll">
                        <table class="pg-table">
                            <thead><tr><th>Lokasi</th><th>Isi</th></tr></thead>
                            <tbody>
                                <tr><td><code>app/Filament/Resources</code></td><td>Semua menu data (CRUD, tabel, form, aksi)</td></tr>
                                <tr><td><code>app/Filament/Pages</code></td><td>Halaman khusus: scan QR, pengaturan WA, panduan ini</td></tr>
                                <tr><td><code>app/Filament/Widgets</code></td><td>Widget dashboard</td></tr>
                                <tr><td><code>app/Services</code></td><td>Aturan bisnis: ItemRequestWorkflow, ProblemReportWorkflow, OTP, WhatsApp, pengingat, rekonsiliasi, import Talenta, impor sumo (<code>app/Services/Sumo</code>)</td></tr>
                                <tr><td><code>app/Models</code>, <code>app/Policies</code></td><td>Data dan otorisasi</td></tr>
                                <tr><td><code>app/Console/Commands</code></td><td><code>sumo:import</code>, <code>reminder:whatsapp</code>, <code>problem-reports:auto-confirm</code></td></tr>
                                <tr><td><code>routes/web.php</code>, <code>routes/console.php</code></td><td>Route PDF dan QR, jadwal scheduler</td></tr>
                                <tr><td><code>config/item-request.php</code>, <code>problem-report.php</code>, <code>sumo-import.php</code></td><td>Aturan modul sumo</td></tr>
                                <tr><td><code>database/migrations</code></td><td>Skema, permission, dan role (role sumo dibuat lewat migration, bukan seeder)</td></tr>
                                <tr><td><code>docs/</code></td><td>Catatan teknis tambahan</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="pg-block">
                    <h3>Menjalankan di lokal</h3>
<pre>composer install
cp .env.example .env
php artisan key:generate
npm ci
php artisan migrate --seed
php artisan storage:link

php artisan serve       # panel di http://127.0.0.1:8000/admin
npm run dev             # frontend
php artisan horizon     # antrean (butuh Redis)
php artisan schedule:work   # bila menguji pengingat</pre>
                    <div class="pg-note pg-warn"><p>Aplikasi ini butuh PHP 8.4 atau lebih baru. Jangan pernah menjalankan <code>migrate:fresh</code>, <code>migrate:refresh</code>, atau <code>db:wipe</code> pada database berisi data. Test fitur memakai database dari <code>.env</code>, jadi arahkan ke database development.</p></div>
                </div>

                <div class="pg-block">
                    <h3>Scheduler di server</h3>
<pre>* * * * * cd /path/ke/shelf-web &amp;&amp; php artisan schedule:run &gt;&gt; storage/logs/scheduler.log 2&gt;&amp;1</pre>
                    <ul class="pg-list">
                        <li><code>reminder:whatsapp</code>: tiap jam.</li>
                        <li><code>problem-reports:auto-confirm</code>: tiap 10 menit.</li>
                        <li><code>notifications:send-scheduled</code>: tiap hari 13:20 (pengingat dokumen atribut kustom).</li>
                        <li><code>model:prune</code>: harian.</li>
                        <li>Worker antrean: <code>php artisan horizon</code>, contoh unit systemd di <code>deploy/web-shelf-queue.service</code>.</li>
                    </ul>
                </div>

                <div class="pg-block">
                    <h3>Mengimpor data dari sumo-web</h3>
                    <x-guide.flow :steps="$flows['import']" />
<p>Cara yang disarankan: menu <strong>Pengaturan, Impor Data Sumo</strong> (izin <code>Manage:SumoImport</code>, awalnya hanya super admin). Unggah dump <code>.sql</code> atau <code>.gz</code> (batas default 12 MB), tunggu uji coba otomatis selesai, periksa laporannya, lalu tekan Impor sungguhan dan ketik <code>IMPOR</code>. Setiap run menyimpan file, tabel yang dimuat, laporan, dan log. Butuh Horizon dengan supervisor antrean <code>long-running</code>.</p>
                    <p>Dump yang lebih besar dimuat manual ke database staging, lalu lewat CLI:</p>
<pre>php artisan sumo:import --dry-run
php artisan sumo:import
php artisan sumo:import --only=requests,problems   # sebagian langkah</pre>
                    <p>Langkah yang tersedia: <code>areas, divisions, masters, users, requests, problems, insurances, rents, wa, scopes, access</code>. Baris yang tidak bisa dipetakan dilewati dengan peringatan, bukan menggagalkan semuanya.</p>
                </div>

                <div class="pg-block">
                    <h3>Variabel .env yang paling sering dipakai</h3>
                    <div class="pg-scroll">
                        <table class="pg-table">
                            <thead><tr><th>Variabel</th><th>Fungsi</th></tr></thead>
                            <tbody>
                                <tr><td><code>DB_*</code></td><td>Koneksi database utama (db_shelf)</td></tr>
                                <tr><td><code>SUMO_DB_DATABASE</code></td><td>Database staging sumo lama. Impor lewat panel mengosongkannya lalu memuat dump. Jangan isi dengan database shelf.</td></tr>
                                <tr><td><code>SUMO_IMPORT_MAX_UPLOAD_KB</code></td><td>Batas unggah dump di menu Impor Data Sumo (default 12288)</td></tr>
                                <tr><td><code>QUEUE_CONNECTION</code>, <code>REDIS_*</code></td><td>Antrean (default redis, butuh Horizon)</td></tr>
                                <tr><td><code>WAG_URL</code>, <code>WAG_TOKEN</code></td><td>Gateway WhatsApp untuk OTP dan pengingat</td></tr>
                                <tr><td><code>ITEM_REQUEST_MAX_OPEN_PER_TYPE</code></td><td>Batas pengajuan terbuka per tipe (default 2)</td></tr>
                                <tr><td><code>WHATSAPP_OTP_TTL_MINUTES</code></td><td>Masa berlaku OTP (default 5)</td></tr>
                                <tr><td><code>FILESYSTEM_DISK</code>, <code>AWS_*</code></td><td>Penyimpanan berkas lokal atau S3</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="pg-block">
                    <h3>Kalau ada masalah</h3>
                    <ul class="pg-list">
                        <li><strong>Perubahan tidak terbaca:</strong> <code>php artisan optimize:clear</code>.</li>
                        <li><strong>Tampilan rusak atau Vite manifest not found:</strong> <code>npm ci &amp;&amp; npm run build</code>.</li>
                        <li><strong>Berkas /storage 404:</strong> <code>php artisan storage:link</code>.</li>
                        <li><strong>Notifikasi, export, atau Impor Data Sumo tidak jalan:</strong> pastikan Redis dan <code>php artisan horizon</code> aktif. Setelah deploy, jalankan <code>php artisan horizon:terminate</code> supaya supervisor baru ikut jalan.</li>
                        <li><strong>WhatsApp tidak terkirim:</strong> cek <code>WAG_URL</code> dan <code>WAG_TOKEN</code>, format nomor 62..., lalu <code>storage/logs/laravel.log</code>.</li>
                        <li><strong>User tidak bisa login:</strong> pastikan punya minimal satu role. Untuk OTP, pastikan Nomor WhatsApp login terisi.</li>
                        <li><strong>Menu tidak muncul:</strong> role belum punya permission. Setelah mengubah permission lewat kode: <code>php artisan permission:cache-reset</code>.</li>
                    </ul>
                </div>

                <div class="pg-block">
                    <h3>Aturan kerja</h3>
                    <ul class="pg-list">
                        <li>Jangan mengubah migration yang sudah dijalankan di server lain. Tambahkan migration baru.</li>
                        <li>Tiap perbaikan bug di policy, workflow, atau WhatsApp ditambah test regresi.</li>
                        <li>Sebelum commit: <code>vendor/bin/pint</code> dan <code>php artisan test --filter=...</code> untuk area yang diubah.</li>
                        <li>Jangan commit <code>.env</code>, token, dump database, atau data pribadi.</li>
                        <li>Endpoint <code>POST /api/integrations/whatsapp/assets/query</code> tidak memakai autentikasi. Jangan buka ke internet tanpa pembatasan jaringan.</li>
                    </ul>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
