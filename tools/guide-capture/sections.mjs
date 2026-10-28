import { mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { execFileSync } from 'node:child_process';
import { BASE, PHP, ROOT } from './lib.mjs';

export default [
  {
    id: 'login',
    anonymous: true, // tanpa sesi login tersimpan
    async run(page, r) {
      await page.goto(`${BASE}/admin/login`);
      await r.pause(1000);
      await r.shot('halaman-login');
      await r.type(page.locator('input[type=text]').first(), 'admin');
      await r.type(page.locator('input[type=password]'), 'password');
      await r.shot('form-terisi');
      await r.click(page.locator('button[type=submit]:visible').first());
      await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 60000 });
      await r.pause(1500);
      await r.shot('dasbor');
      await r.pause(800);
    },
  },
  {
    id: 'dasbor',
    async run(page, r) {
      await page.goto(`${BASE}/admin`);
      await r.pause(1500);
      await r.shot('dasbor');
      // Buka-tutup grup menu di sidebar.
      const group = page.locator('button.mky-sidebar-group-label:visible', { hasText: 'Master Aset' }).first();
      await r.click(group);
      await r.shot('grup-menu-tertutup', group);
      await r.click(group);
      await r.click(page.getByRole('link', { name: 'Panduan' }).first());
      await page.waitForURL(/panduan/);
      await r.pause(1500);
      await r.shot('menu-panduan');
      await r.pause(800);
    },
  },
  {
    id: 'setup',
    async run(page, r) {
      await page.goto(`${BASE}/admin`);
      await r.pause(800);
      await r.click(page.getByRole('link', { name: 'Custom Aset Attribute' }).first());
      await page.waitForURL(/custom-asset-attributes/);
      await r.shot('menu-custom-attribute');
      await r.click(page.getByRole('link', { name: /Buat|Baru/ }).first());
      await page.waitForURL(/create/);
      await r.fill('Nama Atribut', 'Dokumen STNK Mobil');
      await r.select('Tipe Input', 'Dokumen / Masa Berlaku');
      await r.shot('nama-dan-tipe');
      await r.toggle('Aktifkan Pengingat');
      await r.select('Pola Pengingat', 'Harian sebelum');
      await r.fill('Mulai Pengingat H-', '30');
      await r.shot('pengaturan-pengingat');
      await r.select('Penerima Internal', 'Super Admin');
      await r.select('Kategori', 'MOBIL');
      await r.toggle('Wajib Diisi');
      await r.shot('penerima-dan-kategori');
      await r.click(page.locator('button[type=submit]:visible').first());
      await page.waitForURL((u) => !u.pathname.endsWith('/create'));
      await r.pause(1200);
      await r.shot('atribut-tersimpan');
      await r.pause(1000);
    },
  },
  {
    id: 'isi-aset',
    async run(page, r) {
      // Contoh dokumen untuk diunggah (dibuat on-the-fly, tidak disimpan di repo).
      const sample = join(mkdtempSync(join(tmpdir(), 'stnk-')), 'stnk-contoh.png');
      const p2 = await page.context().newPage();
      await p2.setContent('<body style="margin:0;width:640px;height:400px;font:28px sans-serif;background:#fffbe6;padding:24px"><b>STNK (CONTOH)</b><br>No: STNK-12345<br>Berlaku s/d 31-12-2026</body>');
      await p2.screenshot({ path: sample, clip: { x: 0, y: 0, width: 640, height: 400 } });
      await p2.close();

      await page.goto(`${BASE}/admin`);
      await r.pause(800);
      await r.click(page.getByRole('link', { name: 'Aset', exact: true }).first());
      await page.waitForURL(/\/admin\/assets/);
      await r.click(page.getByRole('link', { name: /Buat|Baru/ }).first());
      await page.waitForURL(/create/);
      await r.select('Kategori', 'MOBIL');
      await r.fill('Nama', 'Toyota Avanza B 1234 CD');
      await r.select('Merek', 'TOYOTA');
      await r.shot('info-aset');

      // Atribut STNK otomatis muncul karena kategori MOBIL cocok dengan atribut yang dibuat tadi.
      const attr = page.locator('.fi-fo-repeater-item').first();
      await attr.scrollIntoViewIfNeeded();
      await r.pause(500);
      await r.shot('atribut-otomatis-muncul', attr);
      await r.fill('Nomor Dokumen', 'STNK-12345');
      await r.date('Berlaku Sampai', '2026-12-31');
      await r.upload('Lampiran Dokumen', sample);
      await r.shot('dokumen-terisi', attr);

      await page.evaluate(() => window.scrollTo(0, 0));
      await r.date('Tanggal Pembelian', '2026-10-13');
      await r.select('Badan Usaha', 'CV BERSAMA CS');
      await r.shot('detail-pembelian');
      await r.click(page.locator('button[type=submit]:visible').first());
      await page.waitForURL((u) => !u.pathname.endsWith('/create'), { timeout: 60000 });
      await r.pause(1500);
      await r.shot('aset-tersimpan');
      await r.pause(1000);
    },
  },
  {
    id: 'aset-daftar', // butuh aset dari bagian isi-aset
    async run(page, r) {
      await page.goto(`${BASE}/admin`);
      await r.pause(800);
      await r.click(page.getByRole('link', { name: 'Aset', exact: true }).first());
      await page.waitForURL(/\/admin\/assets/);
      await r.pause(1200);
      await r.shot('daftar-aset');
      await r.type(page.locator('input[type=search]:visible').first(), 'Avanza');
      await r.pause(1500);
      await r.shot('hasil-pencarian');
      await r.click(page.getByText('Toyota Avanza B 1234 CD').first());
      await page.waitForURL(/\/assets\/\d+/);
      await r.pause(1500);
      await r.shot('detail-aset');
      await page.mouse.wheel(0, 500);
      await r.pause(1500);
      await r.shot('detail-atribut-dan-qr');
      await r.pause(800);
    },
  },

  // ---- Pengajuan Barang: empat akun bergantian (pemohon -> accounting -> executor -> pemohon) ----
  {
    id: 'pengajuan-buat',
    user: 'pemohon',
    async run(page, r) {
      await page.goto(`${BASE}/admin`);
      await r.pause(800);
      await r.click(page.locator('aside').first().getByRole('link', { name: 'Pengajuan Barang' }).first());
      await page.waitForURL(/item-requests/);
      await r.pause(800);
      await r.shot('daftar-pengajuan');
      await r.click(page.getByRole('link', { name: 'Buat Pengajuan' }).first());
      await page.waitForURL(/create/);
      await r.select('Tipe Pengajuan', 'PEMBELIAN INVENTARIS');
      await r.pause(800);
      await r.select('Barang', 'KURSI KERJA');
      await r.fill('Jumlah', '2');
      await r.fill('Keterangan', 'Kursi untuk staf baru');
      await r.shot('isi-barang');
      await r.click(page.getByRole('button', { name: 'Tambah barang' }).first());
      await page.locator('.fi-fo-repeater-item').nth(1).waitFor();
      await r.pause(800);
      await r.select('Barang', 'PULPEN HITAM');
      await r.fill('Jumlah', '10');
      const rows = await page.locator('.fi-fo-repeater-item .fi-select-input-btn').allInnerTexts();
      if (!rows[0].includes('KURSI') || !rows[1].includes('PULPEN')) throw new Error(`Isi baris barang salah: ${rows}`);
      await r.shot('dua-barang', page.locator('.fi-fo-repeater').first());
      await r.click(page.locator('button[type=submit]:visible').first());
      await page.waitForURL(/item-requests\/\d+$/, { timeout: 60000 });
      await r.pause(1500);
      await r.shot('pengajuan-terkirim');
      await page.mouse.wheel(0, 500);
      await r.pause(1200);
      await r.shot('riwayat-approval');
      await r.pause(800);
    },
  },
  {
    id: 'pengajuan-accounting',
    user: 'accounting',
    async run(page, r) {
      await openFirst(page, r, 'item-requests', 'Pengajuan Barang');
      await r.shot('pengajuan-masuk');
      await r.action('Status PO (Accounting)');
      await r.toggle('PO sudah diterbitkan');
      await r.fill('Nomor PO', 'PO-2026-0142');
      await r.shot('isi-status-po', r.modal());
      await r.submitModal();
      await r.shot('po-tersimpan');
      await r.pause(800);
    },
  },
  {
    id: 'pengajuan-proses',
    user: 'executor',
    async run(page, r) {
      await openFirst(page, r, 'item-requests', 'Pengajuan Barang');
      await r.shot('pengajuan-masuk');
      await r.action('Setujui Jumlah (Manager)');
      await r.shot('setujui-jumlah', r.modal());
      await r.submitModal();
      await r.shot('disetujui-manager');
      await r.action('Proses (Executor)');
      await r.radio('Selesai');
      await r.fill('Catatan', 'Barang sudah diserahkan ke pemohon');
      await r.shot('keputusan-selesai', r.modal());
      await r.submitModal();
      await r.shot('status-selesai');
      await r.pause(800);
    },
  },
  {
    id: 'pengajuan-konfirmasi',
    user: 'pemohon',
    async run(page, r) {
      await openFirst(page, r, 'item-requests', 'Pengajuan Barang');
      await r.shot('menunggu-konfirmasi');
      await r.action('Konfirmasi Penerimaan');
      await r.fill('Catatan', 'Sudah diterima lengkap');
      await r.shot('form-konfirmasi', r.modal());
      await r.submitModal();
      await r.shot('diterima');
      await r.pause(800);
    },
  },

  // ---- Lapor Gangguan: pelapor -> executor -> pelapor ----
  {
    id: 'gangguan-lapor',
    user: 'pemohon',
    async run(page, r) {
      const foto = await r.sampleImage('ac-bocor.png', '<div style="background:#e0f2fe;height:352px;padding:24px;box-sizing:border-box"><b>FOTO CONTOH</b><br>AC ruang rapat lantai 2 meneteskan air</div>');
      await page.goto(`${BASE}/admin`);
      await r.pause(800);
      await r.click(page.locator('aside').first().getByRole('link', { name: 'Laporan Gangguan' }).first());
      await page.waitForURL(/problem-reports/);
      await r.pause(800);
      await r.shot('daftar-laporan');
      await r.click(page.locator('.fi-header').getByRole('link', { name: 'Lapor Gangguan' }).first());
      await page.waitForURL(/create/);
      await r.select('Jenis Gangguan', 'AC');
      await r.fill('Judul (opsional)', 'AC ruang rapat bocor');
      await r.fill('Keterangan Gangguan', 'AC di ruang rapat lantai 2 meneteskan air sejak pagi.');
      await r.upload('Foto Gangguan / Kerusakan', foto);
      await r.shot('form-terisi');
      await r.click(page.locator('button[type=submit]').getByText('Buat', { exact: true }));
      await page.waitForURL(/problem-reports\/\d+$/, { timeout: 60000, waitUntil: 'commit' });
      await page.waitForLoadState('networkidle');
      await r.pause(1500);
      await r.shot('laporan-terkirim');
      await r.pause(800);
    },
  },
  {
    id: 'gangguan-proses',
    user: 'executor',
    async run(page, r) {
      const foto = await r.sampleImage('ac-beres.png', '<div style="background:#dcfce7;height:352px;padding:24px;box-sizing:border-box"><b>FOTO CONTOH</b><br>Selang pembuangan AC sudah diganti</div>');
      await openFirst(page, r, 'problem-reports', 'Laporan Gangguan');
      await r.shot('laporan-masuk');
      await r.action('Proses (Executor)');
      await r.radio('Selesai');
      await r.upload('Foto Hasil', foto);
      await r.fill('Hasil Pengerjaan', 'Selang pembuangan diganti, AC normal kembali.');
      await r.shot('form-proses', r.modal());
      await r.submitModal();
      await r.shot('laporan-selesai');
      await page.mouse.wheel(0, 600);
      await r.pause(1200);
      await r.shot('foto-sebelum-sesudah');
      await r.pause(800);
    },
  },
  {
    id: 'gangguan-konfirmasi',
    user: 'pemohon',
    async run(page, r) {
      await openFirst(page, r, 'problem-reports', 'Laporan Gangguan');
      await r.shot('menunggu-konfirmasi');
      await r.action('Konfirmasi Selesai');
      await r.fill('Ulasan (opsional)', 'Terima kasih, sudah tidak bocor.');
      await r.shot('form-konfirmasi', r.modal());
      await r.submitModal();
      await r.shot('terkonfirmasi');
      await r.pause(800);
    },
  },
  // ---- Aset: BA serah terima, servis ----
  {
    id: 'ba-serah-terima',
    async run(page, r) {
      await openMenu(page, r, 'Aset Transfer', 'asset-transfers');
      await r.shot('daftar-ba');
      await r.click(page.locator('.fi-header').getByRole('link', { name: /Buat/ }).first());
      await waitPage(page, /create$/);
      await r.select('Jenis Berita Acara', 'BERITA ACARA SERAH TERIMA');
      await r.select('Badan Usaha', 'CV BERSAMA CS');
      await r.select('Staf GA yang Menyerahkan', 'Gita General Affair');
      await r.select('Ke Penerima', 'Budi Santoso');
      await r.date('Transfer date', today());
      await r.shot('informasi-ba');
      await r.select('Aset', 'Laptop Lenovo ThinkPad E14');
      await r.fill('Perlengkapan', 'Charger dan tas laptop');
      await r.shot('aset-diserahkan', page.locator('.fi-fo-repeater').first());
      await r.click(page.locator('button[type=submit]').getByText('Buat', { exact: true }));
      await waitPage(page, (u) => !u.pathname.endsWith('/create'));
      await r.pause(1500);
      await r.shot('ba-tersimpan');
      await r.pause(800);
    },
  },
  {
    id: 'ba-pengalihan', // butuh laptop yang dipegang Budi dari bagian ba-serah-terima
    async run(page, r) {
      await openMenu(page, r, 'Aset Transfer', 'asset-transfers');
      await r.click(page.locator('.fi-header').getByRole('link', { name: /Buat/ }).first());
      await waitPage(page, /create$/);
      await r.select('Jenis Berita Acara', 'BERITA ACARA PENGALIHAN BARANG');
      await r.select('Badan Usaha', 'CV BERSAMA CS');
      await r.select('Dari Pemegang', 'Budi Santoso');
      await r.select('Ke Penerima', 'Putri Pemohon');
      await r.date('Transfer date', today());
      // Yang digulir panel konten, bukan window: gulir ke judul halaman.
      await page.locator('.fi-header').first().evaluate((e) => e.scrollIntoView({ block: 'start' }));
      await r.pause(800);
      await r.shot('aset-pemegang-terisi', page.locator('.fi-fo-repeater').first());
      await r.click(page.locator('button[type=submit]').getByText('Buat', { exact: true }));
      await waitPage(page, (u) => !u.pathname.endsWith('/create'));
      await r.pause(1500);
      await r.shot('ba-tersimpan');
      await r.pause(800);
    },
  },
  {
    id: 'ba-pengembalian', // butuh laptop yang dipegang Putri dari bagian ba-pengalihan
    async run(page, r) {
      await openMenu(page, r, 'Aset Transfer', 'asset-transfers');
      await r.click(page.locator('.fi-header').getByRole('link', { name: /Buat/ }).first());
      await waitPage(page, /create$/);
      await r.select('Jenis Berita Acara', 'BERITA ACARA PENGEMBALIAN BARANG');
      await r.select('Badan Usaha', 'CV BERSAMA CS');
      await r.select('Dari Pemegang', 'Putri Pemohon');
      await r.select('Staf GA yang Menerima', 'Gita General Affair');
      await r.date('Transfer date', today());
      await r.pause(800);
      await r.shot('form-pengembalian');
      await r.click(page.locator('button[type=submit]').getByText('Buat', { exact: true }));
      await waitPage(page, (u) => !u.pathname.endsWith('/create'));
      await r.pause(1500);
      await r.shot('ba-tersimpan');
      // Aset kembali ke stok: Tersedia, tanpa pemegang.
      await openMenu(page, r, 'Aset', 'assets');
      await r.shot('laptop-kembali-tersedia', page.locator('.fi-ta-row', { hasText: 'Laptop Lenovo' }).first());
      await r.pause(800);
    },
  },
  {
    id: 'servis',
    async run(page, r) {
      await openMenu(page, r, 'Servis Aset', 'asset-services');
      await r.click(page.locator('.fi-header').getByRole('link', { name: 'Tambah Servis Aset' }).first());
      await waitPage(page, /create$/);
      await r.select('Aset yang Diservis', 'Honda Vario B 4321 KLM');
      await r.select('Teknisi Internal', 'Eko Executor');
      await r.fill('Kendala / Kerusakan', 'Rem belakang blong, perlu ganti kampas.');
      await r.shot('form-servis');
      await r.click(page.locator('button[type=submit]').getByText('Buat', { exact: true }));
      await waitPage(page, /asset-services$/);
      await r.pause(1500);
      await r.shot('servis-tercatat');
      await r.click(page.locator('.fi-ta-row').first().getByRole('button', { name: 'Selesaikan' }));
      await r.modal().waitFor();
      await r.pause(600);
      await r.date('Tanggal Selesai', today());
      await r.fill('Total Biaya (Rp)', '150000');
      await r.fill('Tindakan Perbaikan', 'Ganti kampas rem belakang');
      await r.shot('form-selesaikan', r.modal());
      await r.submitModal();
      await r.shot('servis-selesai');
      await r.pause(800);
    },
  },

  // ---- Organisasi: user baru + role ----
  {
    id: 'user-baru',
    async run(page, r) {
      await openMenu(page, r, 'Pengguna', 'users');
      await r.shot('daftar-pengguna');
      await r.click(page.locator('.fi-header').getByRole('link', { name: 'Buat Pengguna' }).first());
      await waitPage(page, /create$/);
      await r.fill('Name', 'Rina Wulandari');
      await r.select('Badan Usaha', 'CV BERSAMA CS');
      await r.select('Divisi', 'SALES');
      await r.shot('informasi-pengguna');
      await r.fill('Username', 'rina');
      await r.fill('Email', 'rina@contoh.test');
      await r.fill('Password', 'password');
      await r.select('Roles', 'requester');
      await r.shot('akses-dan-role');
      await r.click(page.locator('button[type=submit]').getByText('Buat', { exact: true }));
      await waitPage(page, (u) => !u.pathname.endsWith('/create'));
      await r.pause(1500);
      await r.shot('pengguna-tersimpan');
      await r.pause(800);
    },
  },

  // ---- OB: atasan menugaskan per shift, OB memotret sebelum dan sesudah ----
  {
    id: 'ob-tugaskan',
    async run(page, r) {
      await openMenu(page, r, 'OB Checksheets', 'ob-checksheets');
      await r.shot('daftar-tugas');
      await r.action('Tugaskan per Shift');
      await r.select('Shift', 'Shift Pagi');
      await r.pause(800);
      await r.select('Petugas OB', 'Oki Office Boy');
      await r.shot('form-tugaskan', r.modal());
      await r.submitModal();
      await r.shot('tugas-dibuat');
      await r.pause(800);
    },
  },
  {
    id: 'ob-kerja',
    user: 'ob',
    async run(page, r) {
      const before = await r.sampleImage('ruang-sebelum.png', '<div style="background:#fee2e2;height:352px;padding:24px;box-sizing:border-box"><b>FOTO CONTOH</b><br>Ruang Meeting Finance, sebelum dibersihkan</div>');
      const after = await r.sampleImage('ruang-sesudah.png', '<div style="background:#dcfce7;height:352px;padding:24px;box-sizing:border-box"><b>FOTO CONTOH</b><br>Ruang Meeting Finance, sudah bersih</div>');
      // Field kamera baris pertama (bukan field repeater yang membungkusnya).
      const cameraIn = () => r.modal().locator('.fi-fo-field:visible').filter({ has: page.locator('.fi-fo-camera-capture') }).filter({ hasNot: page.locator('.fi-fo-repeater') }).first();
      await openMenu(page, r, 'OB Checksheets', 'ob-checksheets');
      await r.shot('tugas-hari-ini');
      await r.action('Mulai Semua & Foto Sebelum');
      await r.camera('Foto Sebelum Pembersihan', before, cameraIn());
      await r.shot('foto-sebelum', r.modal());
      await r.submitModal();
      await r.shot('sedang-dikerjakan');
      await r.action('Selesaikan Semua & Foto Sesudah');
      await r.camera('Foto Sesudah Pembersihan', after, cameraIn());
      await r.shot('foto-sesudah', r.modal());
      await r.submitModal();
      await r.shot('selesai');
      await r.pause(800);
    },
  },

  // ---- Kendaraan: checksheet berangkat lalu kembali ----
  {
    id: 'kendaraan',
    async run(page, r) {
      const foto = await r.sampleImage('motor.png', '<div style="background:#f1f5f9;height:352px;padding:24px;box-sizing:border-box"><b>FOTO CONTOH</b><br>Honda Vario B 4321 KLM</div>');
      await openMenu(page, r, 'Vehicle Checksheet', 'vehicle-checksheets');
      await r.click(page.locator('.fi-header').getByRole('link', { name: /Buat/ }).first());
      await waitPage(page, /create$/);
      await r.select('Plat Nomor', 'B 4321 KLM');
      await r.fill('PIC (Penanggung Jawab)', 'Budi Santoso');
      await r.fill('Lokasi Kendaraan', 'Parkiran kantor pusat');
      await r.fill('Tujuan', 'Kirim dokumen ke cabang Bekasi');
      await r.shot('informasi-kendaraan');
      await r.fill('Kilometer Awal', '12500');
      await r.date('Waktu Keberangkatan', `${today()}T08:00`);
      await r.upload('Foto Keberangkatan', foto);
      await r.upload('Laporan Kerusakan Saat Keberangkatan', foto);
      await r.shot('keberangkatan');
      await r.click(page.locator('button[type=submit]').getByText('Buat', { exact: true }));
      await waitPage(page, (u) => !u.pathname.endsWith('/create'));
      await r.pause(1500);
      await r.shot('berangkat-tersimpan');
      // Saat kendaraan kembali: ubah checksheet yang sama dan isi bagian pengembalian.
      if (!/\/edit$/.test(page.url())) {
        await r.click(page.locator('.fi-ta-row').first().getByRole('link', { name: 'Ubah' }));
        await waitPage(page, /edit$/);
      }
      await r.fill('Kilometer Akhir', '12538');
      await r.date('Waktu Pengembalian', `${today()}T11:30`);
      await r.upload('Foto Pengembalian', foto);
      await r.upload('Laporan Kerusakan Saat Pengembalian', foto);
      await r.shot('pengembalian', page.locator('.fi-section', { hasText: 'Informasi Pengembalian' }).first());
      await r.click(page.getByRole('button', { name: /^Simpan/ }).first());
      await r.pause(2000);
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
      await r.pause(800);
      await r.shot('kembali-tersimpan', page.locator('.fi-section', { hasText: 'Informasi Tambahan' }).first());
      await r.pause(800);
    },
  },

  // ---- Asuransi & Sewa, pengingat WhatsApp ----
  {
    id: 'asuransi',
    async run(page, r) {
      await openMenu(page, r, 'Asuransi', 'insurances');
      await r.shot('daftar-asuransi');
      await r.click(page.locator('.fi-header').getByRole('link', { name: 'Buat Asuransi' }).first());
      await waitPage(page, /create$/);
      await r.fill('Nomor Polis', 'POL-2025-00871');
      await r.fill('Nama Tertanggung', 'CV BERSAMA CS - GUDANG PUSAT');
      await r.select('Badan Usaha', 'CV BERSAMA CS');
      await r.fill('Nilai Stok', '750000000');
      await r.fill('Premi Stok', '3250000');
      await r.shot('polis-dan-nilai');
      const period = page.locator('.fi-section', { hasText: 'Periode dan Status' }).first();
      await period.scrollIntoViewIfNeeded();
      await r.date('Tanggal Mulai', shiftDays(-340));
      await r.date('Tanggal Berakhir', shiftDays(25));
      await r.fill('Nomor WA Pengingat', '081234567890');
      await r.shot('periode-dan-pengingat', period);
      await r.click(page.locator('button[type=submit]').getByText('Buat', { exact: true }));
      await waitPage(page, (u) => !u.pathname.endsWith('/create'));
      await r.pause(1500);
      await r.shot('asuransi-tersimpan');
      await r.pause(800);
    },
  },
  {
    id: 'sewa',
    async run(page, r) {
      await openMenu(page, r, 'Perjanjian Sewa', 'rents');
      await r.click(page.locator('.fi-header').getByRole('link', { name: 'Buat Perjanjian Sewa' }).first());
      await waitPage(page, /create$/);
      await r.fill('Kode Sewa', 'SW-BKS-2026');
      await r.select('Badan Usaha', 'CV BERSAMA CS');
      await r.fill('Objek Sewa', 'Ruko 2 lantai cabang Bekasi');
      await r.fill('Pihak Pertama', 'Bapak Hendra (pemilik)');
      await r.fill('Pihak Kedua', 'CV BERSAMA CS');
      await r.date('Tanggal Mulai', shiftDays(-320));
      await r.date('Tanggal Berakhir', shiftDays(45));
      await r.fill('Pengingat (bulan sebelum)', '2');
      await r.shot('data-kontrak');
      await r.fill('Sewa per Tahun', '120000000');
      await r.select('Dokumen Perjanjian', 'ADA (SCAN)');
      await r.shot('pembayaran-dan-dokumen', page.locator('.fi-section', { hasText: 'Dokumen' }).last());
      await r.click(page.locator('button[type=submit]').getByText('Buat', { exact: true }));
      await waitPage(page, (u) => !u.pathname.endsWith('/create'));
      await r.pause(1500);
      await r.shot('sewa-tersimpan');
      // Kontrak yang hampir habis muncul di dasbor.
      await page.goto(`${BASE}/admin`);
      await page.waitForLoadState('networkidle');
      await r.pause(1500);
      // Widget dasbor dimuat malas (lazy) saat terlihat: gulir pelan sampai tabel jatuh tempo muncul.
      const expiring = page.getByText('Asuransi Terdekat Jatuh Tempo').first();
      for (let i = 0; i < 15 && !(await expiring.isVisible()); i++) {
        await page.mouse.wheel(0, 500);
        await r.pause(700);
      }
      await expiring.evaluate((e) => e.scrollIntoView({ block: 'start', behavior: 'smooth' }));
      await r.pause(1500);
      await r.shot('dasbor-kontrak-berakhir');
      await r.pause(800);
    },
  },
  {
    id: 'wa-pengaturan',
    async run(page, r) {
      await page.goto(`${BASE}/admin`);
      await r.pause(800);
      const link = page.locator('aside a[href$="/wa-notification-settings"]').first();
      // Grup Pengaturan tertutup secara default: buka dulu.
      if (!(await link.isVisible())) await r.click(page.locator('button.mky-sidebar-group-label', { hasText: 'Pengaturan' }).first());
      await r.click(link);
      await waitPage(page, /wa-notification-settings$/);
      await r.pause(1000);
      await r.shot('pengaturan-umum');
      await page.mouse.wheel(0, 400);
      await r.pause(1000);
      await r.shot('jenis-pengingat');
      await r.pause(800);
    },
  },
  // ---- Import & Laporan Audit (kendaraan): export format -> isi -> import -> laporan -> terapkan ----
  {
    id: 'audit-kendaraan',
    async run(page, r) {
      const workbook = vehicleAuditWorkbook();
      await openMenu(page, r, 'Import & Laporan Audit', 'asset-reconciliations');
      await r.shot('daftar-audit');
      await r.click(page.locator('.fi-header').getByRole('link', { name: /Import Audit/ }).first());
      await waitPage(page, /create$/);
      await r.pause(800);
      await r.shot('tombol-export-format', page.locator('.fi-header').getByRole('button', { name: /Export Format Audit Kendaraan/ }).first());
      await r.select('Jenis Audit', 'Kendaraan (Monitoring Asset / LHP)');
      await r.select('Badan Usaha Default', 'CV BERSAMA CS');
      await r.upload('File Excel Audit', workbook);
      await r.toggle('Buat aset/lokasi baru dari audit (opt-in)');
      await r.shot('form-import');
      await r.click(page.locator('button[type=submit]').getByText('Buat', { exact: true }));
      await waitPage(page, /asset-reconciliations\/[0-9]+$/);
      await r.pause(2000);
      await r.shot('laporan-hasil-banding');
      // Tabel per kendaraan (relation manager) dimuat saat terlihat: gulir sampai barisnya muncul.
      const items = page.locator('.fi-ta').last();
      for (let i = 0; i < 12 && !(await page.locator('.fi-ta-row').first().isVisible()); i++) {
        await page.mouse.wheel(0, 500);
        await r.pause(700);
      }
      await items.evaluate((e) => e.scrollIntoView({ block: 'start', behavior: 'smooth' }));
      await r.pause(1500);
      await r.shot('laporan-per-kendaraan', items);
      await page.locator('.fi-header').first().evaluate((e) => e.scrollIntoView({ block: 'start' }));
      await r.pause(600);
      await r.action('3. Terapkan Koreksi');
      await r.shot('konfirmasi-terapkan', r.modal());
      await r.submitModal();
      await r.pause(1000);
      await r.shot('koreksi-diterapkan');
      await openMenu(page, r, 'Aset', 'assets');
      await r.shot('aset-baru-dari-audit', page.locator('.fi-ta-row', { hasText: 'GRAN MAX' }).first());
      await r.pause(800);
    },
  },
  {
    id: 'audit-csa',
    async run(page, r) {
      const workbook = csaAuditWorkbook();
      await openMenu(page, r, 'Import & Laporan Audit', 'asset-reconciliations');
      await r.click(page.locator('.fi-header').getByRole('link', { name: /Import Audit/ }).first());
      await waitPage(page, /create$/);
      await r.pause(800);
      await r.shot('tombol-export-format', page.locator('.fi-header').getByRole('button', { name: /Export Format CSA/ }).first());
      await r.select('Jenis Audit', 'CSA (stok ritel / inventori)');
      await r.select('Badan Usaha Default', 'CV BERSAMA CS');
      await r.upload('File Excel Audit', workbook);
      await r.shot('form-import');
      await r.click(page.locator('button[type=submit]').getByText('Buat', { exact: true }));
      await waitPage(page, /asset-reconciliations\/[0-9]+$/);
      await r.pause(2000);
      await r.shot('laporan-hasil-banding');
      const items = page.locator('.fi-ta').last();
      for (let i = 0; i < 12 && !(await page.locator('.fi-ta-row').first().isVisible()); i++) {
        await page.mouse.wheel(0, 500);
        await r.pause(700);
      }
      await items.evaluate((e) => e.scrollIntoView({ block: 'start', behavior: 'smooth' }));
      await r.pause(1500);
      await r.shot('laporan-per-barang', items);
      await page.locator('.fi-header').first().evaluate((e) => e.scrollIntoView({ block: 'start' }));
      await r.pause(600);
      await r.action('3. Terapkan Koreksi');
      await r.shot('konfirmasi-terapkan', r.modal());
      await r.submitModal();
      await r.pause(1000);
      await r.shot('koreksi-diterapkan');
      await r.pause(800);
    },
  },

  // ---- Pekerjaan Vendor ----
  {
    id: 'vendor-tugas',
    async run(page, r) {
      await openMenu(page, r, 'Task', 'tasks');
      await r.shot('daftar-tugas');
      await r.click(page.locator('.fi-header').getByRole('link', { name: /Buat|Baru|Tambah/ }).first());
      await waitPage(page, /create$/);
      await r.fill('Nama Pekerjaan', 'Servis AC Ruang Meeting');
      await r.fill('Biaya', '750000');
      await r.fill('Deskripsi', 'Cuci dan isi freon 2 unit AC split.');
      await r.fill('Lokasi', 'Kantor Pusat Lt. 2');
      await r.select('Entitas Bisnis', 'CV BERSAMA CS');
      await r.shot('informasi-umum');
      await r.select('Vendor', 'CV Sumber Teknik');
      await r.shot('pilih-vendor', r.field('Vendor'));
      await r.click(page.locator('button[type=submit]').getByText('Buat', { exact: true }));
      await waitPage(page, /tasks\/[0-9]+$/);
      await r.pause(1500);
      await r.shot('tugas-tersimpan');

      // Open -> In Progress -> Completed lewat menu aksi di baris tabel.
      await openMenu(page, r, 'Task', 'tasks');
      await rowAction(page, r, 'Process');
      await r.pause(1500);
      await r.shot('status-in-progress', page.locator('.fi-ta-row').first());
      await rowAction(page, r, 'Complete');
      await r.modal().waitFor();
      await r.pause(600);
      const photo = await r.sampleImage('ac-selesai.png', '<b>FOTO HASIL (CONTOH)</b><br>AC Ruang Meeting<br>sudah dicuci dan diisi freon');
      await r.upload('Upload Lampiran', photo);
      await r.shot('form-selesai', r.modal());
      await r.submitModal();
      await r.shot('status-completed', page.locator('.fi-ta-row').first());
      await r.click(page.locator('.fi-ta-row').first().locator('.fi-dropdown-trigger').first());
      await r.pause(600);
      await r.shot('aksi-download-upload', page.locator('.fi-dropdown-panel:visible').first());
      await page.keyboard.press('Escape');
      await r.pause(800);
    },
  },
];

// Buka menu aksi (ActionGroup) di baris teratas lalu pilih salah satu aksinya.
async function rowAction(page, r, label) {
  const item = page.locator('.fi-dropdown-panel:visible').getByText(label, { exact: true }).first();
  // Setelah aksi sebelumnya, Livewire me-render ulang baris: klik pemicu bisa hilang, jadi ulangi.
  for (let attempt = 0; attempt < 3 && !(await item.isVisible()); attempt++) {
    await page.waitForLoadState('networkidle');
    await r.click(page.locator('.fi-ta-row').first().locator('.fi-dropdown-trigger').first());
    await item.waitFor({ timeout: 3000 }).catch(() => {});
  }
  await r.click(item);
}

// File audit CSA contoh: hasil "Export Format CSA" dengan fisik kertas thermal 8 (GAIS 10) dan satu barang
// yang ditemukan di gudang tapi belum tercatat.
function csaAuditWorkbook() {
  const file = join(mkdtempSync(join(tmpdir(), 'audit-')), 'audit-csa-contoh.xlsx').replace(/\\/g, '/');
  const code = String.raw`$s = app(App\Services\CsaAssetAuditWorkbookExporter::class)->build(); $sh = $s->getSheetByName('ASET');`
    + String.raw` foreach ($sh->getRowIterator() as $row) { $r = $row->getRowIndex(); if ($sh->getCell('C'.$r)->getValue() === 'KERTAS THERMAL 80MM') {`
    + String.raw` $sh->setCellValue('F'.$r, 8); $sh->setCellValue('G'.$r, -2); $sh->setCellValue('H'.$r, '2 rol rusak');`
    + String.raw` $sh->insertNewRowBefore($r + 1); $sh->fromArray(['GDG-PST', 'ATK-002', 'TINTA PRINTER EPSON 003', null, 0, 6, 6, 'Belum tercatat'], null, 'A'.($r + 1)); break; } }`
    + ` (new PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx($s))->save('${file}');`;
  execFileSync(PHP, ['artisan', 'tinker', '--execute', code], {
    cwd: ROOT,
    env: { ...process.env, DB_DATABASE: process.env.CAPTURE_DB || 'db_gais_capture' },
    stdio: 'pipe',
  });
  return file;
}

// File audit kendaraan contoh: hasil "Export Format Audit Kendaraan" ditambah satu mobil yang ditemukan auditor
// tapi belum tercatat di GAIS (seperti yang diisi orang di Excel sebelum diimpor).
function vehicleAuditWorkbook() {
  const file = join(mkdtempSync(join(tmpdir(), 'audit-')), 'audit-kendaraan-contoh.xlsx').replace(/\\/g, '/');
  const code = String.raw`$s = app(App\Services\VehicleAssetAuditWorkbookExporter::class)->build();`
    + String.raw` $sh = $s->getSheetByName('Monitoring Asset'); $r = $sh->getHighestRow() + 1;`
    + String.raw` $sh->fromArray([$r - 2, 'B 1717 KJT', 'CV BERSAMA CS', null, null, null, 'KANTOR PUSAT', 'DAIHATSU', 'GRAN MAX', 'MOBIL', 'PUTIH', '2022', 'MHKV1BA2JNK012345', 'Budi Santoso', 'ADA'], null, 'A'.$r);`
    + ` (new PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx($s))->save('${file}');`;
  execFileSync(PHP, ['artisan', 'tinker', '--execute', code], {
    cwd: ROOT,
    env: { ...process.env, DB_DATABASE: process.env.CAPTURE_DB || 'db_gais_capture' },
    stdio: 'pipe',
  });
  return file;
}

function today() {
  return shiftDays(0);
}

// Tanggal ISO relatif terhadap hari ini (mis. -30 = 30 hari lalu).
function shiftDays(n) {
  const d = new Date();
  d.setDate(d.getDate() + n);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

// Navigasi Filament memakai wire:navigate (tanpa event 'load'): tunggu URL berubah lalu jaringan tenang.
async function waitPage(page, url) {
  await page.waitForURL(url, { timeout: 60000, waitUntil: 'commit' });
  await page.waitForLoadState('networkidle');
}

// Buka menu lewat sidebar.
async function openMenu(page, r, menu, slug) {
  await page.goto(`${BASE}/admin`);
  await r.pause(800);
  await r.click(page.locator('aside').first().getByRole('link', { name: menu, exact: true }).first());
  await waitPage(page, new RegExp(`${slug}$`));
  await r.pause(1000);
}


// Buka menu lewat sidebar lalu buka baris teratas di tabel (data terbaru).
async function openFirst(page, r, slug, menu) {
  await page.goto(`${BASE}/admin`);
  await r.pause(800);
  await r.click(page.locator('aside').first().getByRole('link', { name: menu }).first());
  await page.waitForURL(new RegExp(`${slug}$`), { waitUntil: 'commit' });
  await page.waitForLoadState('networkidle');
  await r.pause(1000);
  await r.click(page.locator('.fi-ta-row').first().getByRole('link', { name: 'Lihat' }));
  await page.waitForURL(new RegExp(`${slug}/[0-9]+$`), { waitUntil: 'commit' });
  await page.waitForLoadState('networkidle');
  await r.pause(1500);
}
