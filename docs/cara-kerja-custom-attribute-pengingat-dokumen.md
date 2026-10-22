# Panduan Penggunaan Custom Attribute: Pengingat Dokumen (STNK, KIR, dll.)

Dokumentasi ini menjelaskan langkah-demi-langkah (step-by-step) cara membuat dan menggunakan **Custom Attribute** dengan tipe **Dokumen / Masa Berlaku**. Fitur ini sangat berguna untuk memberikan pengingat harian terkait dokumen yang memiliki masa berlaku, seperti STNK Mobil, KIR, Kontrak, atau dokumen perizinan lainnya.

---

## 1. Konfigurasi Custom Attribute (Admin Setup)

Langkah pertama adalah membuat atribut baru di sistem agar bisa digunakan pada form pendataan Aset. Pada contoh ini, kita membuat Atribut untuk **Dokumen STNK Mobil**.

### Langkah-langkah:
1. Login ke sistem admin di `http://web-shelf.test/admin/login` (atau alamat sistem yang sesuai) menggunakan akun berhak akses Admin.
2. Pada menu sidebar sebelah kiri (grup **Master Aset**), klik menu **Custom Aset Attribute**.
3. Klik tombol **Buat** di pojok kanan atas daftar.

![Menu Custom Aset Attribute](../public/images/guide/setup-01-menu-custom-attribute.png)
4. Isi form pembuatan atribut:
   - **Nama Atribut**: Isi dengan nama dokumen, misalnya `Dokumen STNK Mobil`.
   - **Tipe Input**: Pilih `Dokumen / Masa Berlaku`.

   ![Nama dan tipe atribut](../public/images/guide/setup-02-nama-dan-tipe.png)

   - **Aktifkan Pengingat**: Klik toggle (tombol geser) hingga menyala.
   - **Pola Pengingat**: Pilih `Harian sebelum tanggal berlaku habis`.
   - **Mulai Pengingat H-**: Isi dengan angka, misalnya `30` (sistem akan mengirim notifikasi 30 hari berturut-turut sebelum dokumen hangus).

   ![Pengaturan pengingat](../public/images/guide/setup-03-pengaturan-pengingat.png)

   - **Kirim Lewat**: Pilih channel pengiriman, misalnya `WhatsApp`.
   - **Penerima Internal**: Pilih user atau karyawan yang akan menerima notifikasi via WhatsApp/Email.
   - **Kategori**: Pilih kategori aset yang relevan. Misalnya untuk STNK, pilih `MOBIL` (atau Kendaraan).
   - **Wajib Diisi**: Nyalakan jika setiap aset Mobil harus melampirkan STNK.

   ![Penerima dan kategori](../public/images/guide/setup-04-penerima-dan-kategori.png)

5. Klik **Buat** di bagian bawah halaman. Muncul notifikasi *Data berhasil dibuat*.

![Atribut tersimpan](../public/images/guide/setup-05-atribut-tersimpan.png)

### 🎥 Rekaman Pembuatan Atribut
Gambar bergerak berikut mengulang seluruh langkah di atas (dibuat otomatis, lihat bagian [Memperbarui screenshot dan animasi](#memperbarui-screenshot-dan-animasi)):

![Langkah Pembuatan Atribut](../public/images/guide/setup.gif)

> [!NOTE]
> Setelah atribut disimpan, seluruh aset yang berada dalam kategori yang dipilih (misal: MOBIL) akan secara otomatis meminta data dokumen STNK ini.

---

## 2. Pengisian Data pada Aset (Operasional)

Setelah atribut siap, langkah selanjutnya adalah mengisi data atribut dokumen tersebut saat membuat atau mengedit Aset.

### Langkah-langkah:
1. Pada menu sidebar sebelah kiri, klik menu **Aset**.
2. Klik tombol **Buat** atau edit aset yang sudah ada.
3. Isi informasi dasar aset, pastikan **Kategori** yang dipilih sesuai dengan kategori pada langkah pertama (misalnya `MOBIL`).

   ![Informasi aset](../public/images/guide/isi-aset-01-info-aset.png)

4. Gulir ke bawah menuju bagian **Attributes**.
5. Begitu kategori dipilih, field untuk atribut (misal: `Dokumen STNK Mobil`) **muncul otomatis**.

   ![Atribut muncul otomatis](../public/images/guide/isi-aset-02-atribut-otomatis-muncul.png)
6. Lengkapi form dokumen yang muncul:
   - **Nomor Dokumen**: Masukkan nomor dokumen resmi, misalnya `STNK-12345` atau `B 1234 CD`.
   - **Berlaku Sampai**: Pilih tanggal kapan dokumen tersebut akan kedaluwarsa.
   - **Lampiran Dokumen**: Upload scan atau foto dari dokumen tersebut (PDF/JPG/PNG).
   - Sistem akan otomatis menghitung **Status Pengingat** menjadi `Aman` jika masih jauh dari tanggal jatuh tempo, atau `Peringatan` jika mendekati hari H.

   ![Dokumen terisi](../public/images/guide/isi-aset-03-dokumen-terisi.png)

7. Lengkapi **Detail Pembelian** di kolom kanan (Tanggal Pembelian dan Badan Usaha wajib diisi).

   ![Detail pembelian](../public/images/guide/isi-aset-04-detail-pembelian.png)

8. Klik **Buat**. Halaman aset menampilkan atribut beserta status pengingatnya.

![Aset tersimpan](../public/images/guide/isi-aset-05-aset-tersimpan.png)

### 🎥 Rekaman Pengisian Data Aset

![Langkah Pengisian Aset](../public/images/guide/isi-aset.gif)

---

## Bagaimana Pengingat (Notifikasi) Bekerja?

> [!TIP]
> **Proses Latar Belakang (Background Job)**
> Sistem (melalui perintah cron harian) akan secara otomatis mengecek setiap aset setiap harinya pada pukul 13:20.
> - Jika tanggal saat ini masuk ke dalam periode `Mulai Pengingat H-30` (30 hari sebelum **Berlaku Sampai**).
> - Maka sistem akan langsung mengirimkan pesan WhatsApp / Email kepada **Penerima Internal** yang dipilih.
> - Pengingat akan terus dikirim **setiap hari** hingga dokumen tersebut diperbarui (di-upload dokumen baru dan tanggal kedaluwarsa diperpanjang).

---

## Memperbarui screenshot dan animasi

Semua gambar di `public/images/guide/` dibuat otomatis oleh skrip Playwright di `tools/guide-capture/`, jadi cukup dijalankan ulang kalau tampilan aplikasi berubah:

```bash
cd tools/guide-capture
npm install
npx playwright install chromium
npm run capture
```

Skrip memakai database terpisah `db_shelf_capture` (tidak menyentuh data asli) dan server sementara di port 8099. Detail ada di [tools/guide-capture/README.md](../tools/guide-capture/README.md).
