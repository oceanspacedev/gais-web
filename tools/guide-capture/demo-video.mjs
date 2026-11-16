// Video demo layanan GA (pindahan Sumo) dari sudut pandang karyawan/pemohon:
// login -> Pengajuan Barang (buat, konfirmasi penerimaan) -> Lapor Gangguan (lapor, konfirmasi selesai).
// Langkah petugas (executor) dikerjakan di luar kamera supaya alurnya utuh.
// Jalankan: CAPTURE_PORT=8098 PHP_BIN="$(which php)" node demo-video.mjs   -> video-out/demo-pemohon.mp4
// Video sisi Tim GA: DEMO=ga ... node demo-video.mjs   -> video-out/demo-ga.mp4
// Default: teks keterangan + musik latar orisinal (music.mjs). MUSIC_FILE=lagu.mp3 untuk lagu sendiri, NARRATION=1 untuk suara TTS.
import { chromium } from 'playwright';
import { mkdirSync, mkdtempSync, writeFileSync, rmSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { execFileSync, execFile } from 'node:child_process';
import { promisify } from 'node:util';
import http from 'node:http';
import net from 'node:net';
import { createHash } from 'node:crypto';
import ffmpeg from 'ffmpeg-static';

const execFileP = promisify(execFile);
const ffprobe = process.env.FFPROBE_BIN || 'ffprobe';
import { makeMusic } from './music.mjs';
import { BASE, CURSOR_JS, Recorder, startServer, resetData, resetPermissionCache } from './lib.mjs';

const DB = process.env.CAPTURE_DB || 'db_gais_capture';
const OUTDIR = join(import.meta.dirname, 'video-out');
const VIEW = { width: 1440, height: 810 };
mkdirSync(OUTDIR, { recursive: true });

// ---------- Overlay di layar: kartu judul dan teks keterangan ----------
async function caption(page, text) {
  await page.evaluate((t) => {
    let c = document.getElementById('__cap');
    if (!c) {
      c = document.createElement('div');
      c.id = '__cap';
      c.style.cssText = 'position:fixed;left:50%;bottom:28px;transform:translateX(-50%);z-index:2147483646;pointer-events:none;'
        + 'max-width:86%;background:rgba(15,23,42,.88);color:#fff;font:600 21px/1.4 system-ui,-apple-system,sans-serif;'
        + 'padding:14px 24px;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.25);text-align:center;transition:opacity .25s';
      document.documentElement.appendChild(c);
    }
    c.style.opacity = t ? '1' : '0';
    if (t) c.innerHTML = t;
  }, text).catch(() => {});
}
// ---------- Narasi suara (say -v Damayanti, bawaan macOS) ----------
// Tiap teks keterangan dan kartu judul dibacakan. Audio dicatat waktunya relatif ke awal klip,
// lalu digabung ke video per adegan. Keterangan berikutnya menunggu narasi sebelumnya selesai.
const NARRATION = !!process.env.NARRATION; // default: tanpa suara, pakai musik latar
const VOICE = process.env.NARRATION_VOICE || 'Damayanti';
const RATE = process.env.NARRATION_RATE || '180';
const TTS = join(OUTDIR, '.tts');
mkdirSync(TTS, { recursive: true });
let narr = null; // narator adegan yang sedang direkam (adegan berjalan berurutan)

function spoken(html) {
  const rep = [
    [/gais\.oceanspace\.co\.id\/admin/g, 'gais titik oceanspace titik co titik id, garis miring admin'],
    [/REQ…/g, 'R E Q'], [/PR…/g, 'P R'], [/&amp;/g, 'dan'], [/→/g, ', lalu'], [/·/g, ','],
    [/dll\./g, 'dan lain-lain'], [/\bATK\b/g, 'A T K'], [/\bOTP\b/g, 'O T P'], [/\bWA\b/g, 'W A'],
    [/\bGAIS\b/g, 'Gais'], [/\bGA\b/g, 'G A'], [/<br\s*\/?>/g, '. '], [/<[^>]+>/g, ' '],
    [/[✔📦🛠️🔔📖]/gu, ''], [/\s+/g, ' '],
  ];
  return rep.reduce((t, [a, b]) => t.replace(a, b), html).trim();
}
async function synth(text) {
  const file = join(TTS, `${createHash('sha1').update(`${VOICE}|${RATE}|${text}`).digest('hex')}.aiff`);
  if (!existsSync(file)) await execFileP('say', ['-v', VOICE, '-r', RATE, '-o', file, text]);
  const { stdout } = await execFileP(ffprobe, ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', file]);
  return { file, dur: Number(stdout.trim()) };
}
function newNarrator(t0) {
  return {
    t0, cues: [], busyUntil: 0,
    now() { return (Date.now() - this.t0) / 1000; },
    async waitFree(page) { const left = this.busyUntil - this.now(); if (left > 0) await page.waitForTimeout(left * 1000); },
    // Mulai bacakan teks sekarang; kembalikan durasinya (detik).
    async speak(text, preSynth) {
      if (!text) return 0;
      const at = this.now();
      const a = preSynth || await synth(text);
      this.cues.push({ file: a.file, at });
      this.busyUntil = at + a.dur + 0.35;
      return a.dur;
    },
  };
}

// Keterangan hilang saat Livewire berpindah halaman (wire:navigate), jadi pasang ulang tiap halaman baru.
function keepCaption(page) {
  let current = '';
  page.on('framenavigated', (f) => { if (f === page.mainFrame() && current) setTimeout(() => caption(page, current), 300); });
  return async (text, hold = 0) => {
    if (narr) await narr.waitFree(page);
    current = text;
    await caption(page, text);
    const t = Date.now();
    if (narr && text) await narr.speak(spoken(text));
    const left = hold - (Date.now() - t);
    if (left > 0) await page.waitForTimeout(left);
  };
}
async function titleCard(page, title, sub, ms = 3500) {
  const a = narr ? await synth(spoken(`${title}. ${sub}`)) : null;
  if (narr) await narr.waitFree(page);
  if (a) ms = Math.max(ms, Math.round(a.dur * 1000) + 900);
  await page.setContent(`<body style="margin:0;height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;
    background:linear-gradient(135deg,#0f172a,#1e3a8a);color:#fff;font-family:system-ui,-apple-system,sans-serif;text-align:center">
    <div style="font-size:22px;letter-spacing:4px;opacity:.7;margin-bottom:18px">GAIS · LAYANAN GA</div>
    <div style="font-size:54px;font-weight:800;max-width:1100px;line-height:1.15">${title}</div>
    <div style="font-size:26px;opacity:.85;margin-top:22px;max-width:1000px;line-height:1.45">${sub}</div>
    <div style="position:fixed;left:0;bottom:0;height:6px;width:100%;background:rgba(255,255,255,.12)">
      <div style="height:100%;width:0;background:#60a5fa;animation:p ${ms}ms linear forwards"></div></div>
    <style>@keyframes p{to{width:100%}}</style></body>`);
  // Video Playwright hanya menyimpan frame saat layar berubah; bilah progres di atas menjaga frame tetap mengalir.
  if (a) await narr.speak('-', a);
  await page.waitForTimeout(ms);
}


// ---------- WhatsApp & email tiruan: menangkap kode OTP tanpa mengirim ke luar ----------
const inbox = { wa: [], mail: [] };
const WA_PORT = 8097;
const SMTP_PORT = 2526;
function startFakeWhatsApp() {
  return http.createServer((req, res) => {
    let body = '';
    req.on('data', (c) => { body += c; });
    req.on('end', () => {
      try { inbox.wa.push(JSON.parse(body)?.message?.text || ''); } catch {}
      res.writeHead(200, { 'content-type': 'application/json' });
      res.end('{"ok":true,"status":"sent"}');
    });
  }).listen(WA_PORT, '127.0.0.1');
}
function startFakeSmtp() {
  const qp = (t) => t.replace(/=\r?\n/g, '').replace(/=([0-9A-F]{2})/g, (_, h) => String.fromCharCode(parseInt(h, 16)));
  return net.createServer((sock) => {
    let buf = ''; let inData = false;
    const handle = () => {
      for (;;) {
        if (inData) {
          const end = buf.indexOf('\r\n.\r\n');
          if (end < 0) return;
          inbox.mail.push(qp(buf.slice(0, end)));
          buf = buf.slice(end + 5); inData = false;
          sock.write('250 OK\r\n');
          continue;
        }
        const i = buf.indexOf('\r\n');
        if (i < 0) return;
        const line = buf.slice(0, i); buf = buf.slice(i + 2);
        const cmd = line.slice(0, 4).toUpperCase();
        if (cmd === 'DATA') { inData = true; sock.write('354 Go ahead\r\n'); }
        else if (cmd === 'QUIT') { sock.end('221 Bye\r\n'); return; }
        else sock.write(cmd === 'EHLO' || cmd === 'HELO' ? '250 localhost\r\n' : '250 OK\r\n');
      }
    };
    sock.on('data', (c) => { buf += c.toString('latin1'); handle(); });
    sock.on('error', () => {});
    sock.write('220 localhost ESMTP\r\n');
  }).listen(SMTP_PORT, '127.0.0.1');
}
async function waitInbox(kind, before, timeout = 20000) {
  const until = Date.now() + timeout;
  while (inbox[kind].length <= before) {
    if (Date.now() > until) throw new Error(`Tidak ada pesan ${kind} yang masuk`);
    await new Promise((res) => setTimeout(res, 200));
  }
  return inbox[kind][inbox[kind].length - 1];
}
// Mockup HP (WhatsApp) / kotak masuk email di sisi kanan layar.
async function showMock(page, html) {
  await page.evaluate((h) => {
    let m = document.getElementById('__mock');
    if (!m) { m = document.createElement('div'); m.id = '__mock'; document.documentElement.appendChild(m); }
    m.style.cssText = 'position:fixed;right:70px;top:50%;z-index:2147483645;transform:translate(40px,-50%);opacity:0;transition:all .45s ease;pointer-events:none';
    m.innerHTML = h;
    requestAnimationFrame(() => requestAnimationFrame(() => { m.style.opacity = '1'; m.style.transform = 'translate(0,-50%)'; }));
  }, html);
  await page.waitForTimeout(700);
}
async function hideMock(page) {
  await page.evaluate(() => { const m = document.getElementById('__mock'); if (m) { m.style.opacity = '0'; m.style.transform = 'translate(40px,-50%)'; } });
  await page.waitForTimeout(500);
}
const esc = (t) => t.replace(/&/g, '&amp;').replace(/</g, '&lt;');
async function showWhatsApp(page, text) {
  const body = esc(text).replace(/\*(.+?)\*/g, '<b>$1</b>').replace(/\n/g, '<br>');
  await showMock(page, `<div style="width:300px;height:600px;background:#111;border-radius:42px;padding:12px;box-shadow:0 25px 60px rgba(0,0,0,.35);font-family:system-ui,-apple-system,sans-serif">
    <div style="height:100%;border-radius:32px;overflow:hidden;background:#efe7dd;display:flex;flex-direction:column">
      <div style="background:#075e54;color:#fff;padding:34px 16px 12px;display:flex;align-items:center;gap:10px">
        <div style="width:36px;height:36px;border-radius:50%;background:#25d366;display:flex;align-items:center;justify-content:center;font-weight:800">G</div>
        <div><div style="font-weight:700;font-size:15px">GAIS</div><div style="font-size:11px;opacity:.8">online</div></div></div>
      <div style="padding:16px 12px;flex:1">
        <div style="font-size:11px;color:#667;text-align:center;margin-bottom:10px;background:#e1f3fb;border-radius:6px;padding:3px 8px;width:max-content;margin-inline:auto">HARI INI</div>
        <div style="background:#fff;border-radius:0 10px 10px 10px;padding:10px 12px;font-size:14px;line-height:1.45;color:#111;box-shadow:0 1px 1px rgba(0,0,0,.1)">${body}
          <div style="text-align:right;font-size:10px;color:#889;margin-top:4px">${new Date().toTimeString().slice(0, 5)}</div></div></div></div></div>`);
}
async function showEmail(page, code) {
  await showMock(page, `<div style="width:420px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,.3);font-family:system-ui,-apple-system,sans-serif;color:#111">
    <div style="background:#f1f5f9;padding:12px 16px;font-size:13px;color:#475569;display:flex;gap:6px;align-items:center">
      <span style="width:11px;height:11px;border-radius:50%;background:#f87171;display:inline-block"></span><span style="width:11px;height:11px;border-radius:50%;background:#fbbf24;display:inline-block"></span><span style="width:11px;height:11px;border-radius:50%;background:#34d399;display:inline-block"></span>
      <span style="margin-left:10px;font-weight:600">Kotak Masuk</span></div>
    <div style="padding:18px 22px 6px;border-bottom:1px solid #e2e8f0">
      <div style="font-weight:700;font-size:17px">Kode masuk GAIS</div>
      <div style="font-size:12px;color:#64748b;margin:4px 0 12px">dari GAIS · ke pemohon@capture.test</div></div>
    <div style="padding:20px 22px 24px;font-size:14px;line-height:1.5">Halo Putri Pemohon, masukkan kode ini di halaman masuk atau klik tombol di bawah.
      <div style="margin:16px 0;text-align:center;font-size:12px;color:#64748b">Kode OTP<div style="font-size:34px;font-weight:800;letter-spacing:8px;color:#111;margin-top:4px">${code}</div></div>
      <div style="text-align:center"><span style="display:inline-block;background:#2563eb;color:#fff;font-weight:600;padding:10px 26px;border-radius:8px">Masuk ke GAIS</span></div></div></div>`);
}

// ---------- Helper navigasi ----------
async function waitPage(page, url) {
  await page.waitForURL(url, { timeout: 60000, waitUntil: 'commit' });
  await page.waitForLoadState('networkidle');
}
async function openMenu(page, r, menu, slug) {
  await r.click(page.locator('aside').first().getByRole('link', { name: menu }).first());
  await waitPage(page, new RegExp(`${slug}$`));
  await r.pause(1000);
}
async function openFirstRow(page, r, slug) {
  await r.click(page.locator('.fi-ta-row').first().getByRole('link', { name: 'Lihat' }));
  await waitPage(page, new RegExp(`${slug}/[0-9]+$`));
  await r.pause(1200);
}
async function smoothScroll(page, dy, steps = 8) {
  for (let i = 0; i < steps; i++) { await page.mouse.wheel(0, dy / steps); await page.waitForTimeout(70); }
  await page.waitForTimeout(600);
}

// ---------- Adegan ----------
const PEMOHON = [
  {
    id: '01-pembuka',
    anonymous: true,
    async run(page, r, say) {
      await titleCard(page, 'Pengajuan Barang &amp; Lapor Gangguan<br>sekarang ada di GAIS',
        'Fitur dari aplikasi Sumo sudah pindah ke GAIS.<br>Video ini dari sudut pandang <b>karyawan / pemohon</b>.', 5000);
      await page.goto(`${BASE}/admin/login`);
      await page.waitForLoadState('networkidle');
      await say('Buka <b>gais.oceanspace.co.id/admin</b>', 2500);
      const wa = page.getByRole('link', { name: 'WhatsApp' }).first();
      const em = page.getByRole('link', { name: 'Email' }).first();
      for (const el of [wa, em]) {
        const b = await el.boundingBox();
        await page.mouse.move(b.x + b.width / 2, b.y + b.height / 2, { steps: 18 });
        await r.pause(500);
      }
      await say('Masuk bisa lewat <b>WhatsApp</b> atau <b>Email</b>. Tidak perlu ingat kata sandi, cukup kode OTP', 4000);
      await say('Cara 1: klik <b>WhatsApp</b>', 1200);
      await r.click(wa);
      await waitPage(page, /phone-login|whatsapp|login\//);
      await say('Isi <b>Nomor HP</b> yang terdaftar di akun Anda, lalu klik <b>Kirim OTP WhatsApp</b>');
      await r.fill('Nomor HP', '0812 3456 7890');
      const waBefore = inbox.wa.length;
      await r.click(page.getByRole('button', { name: 'Kirim OTP WhatsApp' }));
      const msg = await waitInbox('wa', waBefore);
      const code = msg.match(/\b(\d{6})\b/)[1];
      await page.getByRole('button', { name: 'Verifikasi OTP' }).waitFor();
      await r.pause(600);
      await showWhatsApp(page, msg);
      await say('Kode OTP <b>6 digit</b> langsung masuk ke WhatsApp Anda (berlaku 5 menit)', 3500);
      await say('Ketik kodenya, lalu klik <b>Verifikasi OTP</b>');
      await r.fill('Kode OTP', code);
      await r.pause(600);
      await hideMock(page);
      await r.click(page.getByRole('button', { name: 'Verifikasi OTP' }));
      await page.waitForURL((u) => /\/admin\/?$/.test(u.pathname), { timeout: 60000, waitUntil: 'commit' });
      await page.waitForLoadState('networkidle');
      await say('Berhasil masuk ke <b>Dasbor</b> ✔', 2500);
      await say('');
    },
  },
  {
    id: '01b-login-email',
    anonymous: true,
    async run(page, r, say) {
      await page.goto(`${BASE}/admin/login`);
      await page.waitForLoadState('networkidle');
      await say('Cara 2: klik <b>Email</b>', 1500);
      await r.click(page.getByRole('link', { name: 'Email' }).first());
      await waitPage(page, /email-login|email/);
      await say('Isi <b>Email</b> Anda, lalu klik <b>Kirim Kode ke Email</b>');
      await r.fill('Email', 'pemohon@capture.test');
      const before = inbox.mail.length;
      await r.click(page.getByRole('button', { name: 'Kirim Kode ke Email' }));
      const mail = await waitInbox('mail', before);
      const code = (mail.match(/Kode OTP[^0-9]{0,400}?(\d{6})/s) || mail.match(/\b(\d{6})\b/))[1];
      await page.getByRole('button', { name: 'Verifikasi dan Masuk' }).waitFor();
      await r.pause(600);
      await showEmail(page, code);
      await say('Kode OTP dikirim ke email Anda (berlaku 10 menit).<br>Bisa juga langsung klik tombol <b>Masuk</b> di email', 4500);
      await say('Ketik kodenya, lalu klik <b>Verifikasi dan Masuk</b>');
      await r.fill('Kode OTP', code);
      await r.pause(600);
      await hideMock(page);
      await r.click(page.getByRole('button', { name: 'Verifikasi dan Masuk' }));
      await page.waitForURL((u) => /\/admin\/?$/.test(u.pathname), { timeout: 60000, waitUntil: 'commit' });
      await page.waitForLoadState('networkidle');
      await say('Setelah masuk, Anda tiba di <b>Dasbor</b>', 2500);
      const nav = page.locator('aside').first();
      for (const [menu, text] of [
        ['Pengajuan Barang', 'Menu <b>Pengajuan Barang</b>: minta barang (ATK, inventaris, dll.) ke tim GA'],
        ['Laporan Gangguan', 'Menu <b>Lapor Gangguan</b>: laporkan kerusakan, misalnya AC, listrik, atau jaringan'],
        ['Panduan', 'Menu <b>Panduan</b>: tutorial lengkap kalau lupa caranya'],
      ]) {
        const link = nav.getByRole('link', { name: menu }).first();
        if (!(await link.count())) continue;
        await link.scrollIntoViewIfNeeded();
        const b = await link.boundingBox();
        await page.mouse.move(b.x + b.width / 2, b.y + b.height / 2, { steps: 18 });
        await link.evaluate((e) => { e.style.outline = '3px solid #ef4444'; e.style.outlineOffset = '2px'; e.style.borderRadius = '8px'; });
        await say(text, 3000);
        await link.evaluate((e) => { e.style.outline = ''; });
      }
      await say('');
    },
  },
  {
    id: '02-pengajuan-buat',
    user: 'pemohon',
    async run(page, r, say) {
      await titleCard(page, 'Bagian 1 · Pengajuan Barang', 'Buat pengajuan → tunggu diproses tim GA → konfirmasi barang sudah diterima');
      await page.goto(`${BASE}/admin`);
      await page.waitForLoadState('networkidle');
      await say('Buka menu <b>Pengajuan Barang</b>');
      await openMenu(page, r, 'Pengajuan Barang', 'item-requests');
      await say('Di sini tampil semua pengajuan Anda beserta statusnya. Klik <b>Buat Pengajuan</b>', 2000);
      await r.click(page.getByRole('link', { name: 'Buat Pengajuan' }).first());
      await waitPage(page, /create/);
      await say('Pilih <b>Tipe Pengajuan</b>');
      await r.select('Tipe Pengajuan', 'ATK');
      await r.pause(800);
      await say('Isi barang yang dibutuhkan: pilih <b>Barang</b>, <b>Jumlah</b>, dan <b>Keterangan</b>');
      await r.select('Barang', 'KERTAS A4 80GR');
      await r.fill('Jumlah', '5');
      await r.fill('Keterangan', 'Kebutuhan cetak laporan bulanan');
      await say('Butuh lebih dari satu barang? Klik <b>Tambah barang</b>');
      await r.click(page.getByRole('button', { name: 'Tambah barang' }).first());
      await page.locator('.fi-fo-repeater-item').nth(1).waitFor();
      await r.pause(800);
      await r.select('Barang', 'PULPEN HITAM');
      await r.fill('Jumlah', '10');
      await say('Kalau tipe pengajuan mewajibkan, unggah juga <b>File Persetujuan Atasan</b>. Lalu klik <b>Buat</b>', 2500);
      await r.click(page.locator('button[type=submit]:visible').first());
      await waitPage(page, /item-requests\/\d+$/);
      await r.pause(800);
      await say('Pengajuan terkirim dengan kode <b>REQ…</b> dan status <b>Menunggu</b>', 3500);
      await smoothScroll(page, 450);
      await say('Daftar barang dan riwayat persetujuan bisa dipantau di halaman ini.<br>Selama masih Menunggu, barang masih boleh ditambah atau diubah', 4500);
      await smoothScroll(page, 450);
      await r.pause(1500);
      await say('Catatan: maksimal <b>2 pengajuan terbuka per tipe</b>. Tidak ada notifikasi WA, jadi pantau statusnya di menu ini', 4500);
    },
  },
  {
    id: 'x-executor-pengajuan',
    user: 'executor',
    offCamera: true,
    async run(page, r) {
      await page.goto(`${BASE}/admin`);
      await openMenu(page, r, 'Pengajuan Barang', 'item-requests');
      await openFirstRow(page, r, 'item-requests');
      const has = async (name) => page.locator('.fi-header').getByRole('button', { name, exact: true }).count();
      if (await has('Setujui Jumlah (Manager)')) { await r.action('Setujui Jumlah (Manager)'); await r.submitModal(); }
      await r.action('Proses (Executor)');
      await r.radio('Selesai');
      await r.fill('Catatan', 'Barang sudah bisa diambil di ruang GA');
      await r.submitModal();
    },
  },
  {
    id: '03-pengajuan-konfirmasi',
    user: 'pemohon',
    async run(page, r, say) {
      await titleCard(page, 'Pengajuan sudah diproses', 'Tim GA menandai pengajuan <b>Selesai</b>.<br>Sekarang giliran pemohon mengonfirmasi penerimaan.', 3500);
      await page.goto(`${BASE}/admin`);
      await page.waitForLoadState('networkidle');
      await openMenu(page, r, 'Pengajuan Barang', 'item-requests');
      await say('Status pengajuan berubah menjadi <b>Selesai</b>. Buka detailnya', 2500);
      await openFirstRow(page, r, 'item-requests');
      await say('Catatan dari tim GA terlihat di sini. Setelah barang diterima, klik <b>Konfirmasi Penerimaan</b>', 3500);
      await r.action('Konfirmasi Penerimaan');
      await say('Pilih <b>Diterima</b>, tambahkan catatan bila perlu, lalu <b>Kirim</b>');
      const diterima = r.modal().getByRole('radio', { name: 'Diterima', exact: true });
      if (await diterima.count()) await r.click(diterima);
      await r.fill('Catatan', 'Sudah diterima lengkap, terima kasih');
      await r.pause(800);
      await r.submitModal();
      await say('Status menjadi <b>Diterima</b>. Pengajuan selesai ✔', 3500);
      await say('Penting: konfirmasi penerimaan <b>hanya bisa dilakukan oleh pemohon sendiri</b>', 4000);
    },
  },
  {
    id: '04-gangguan-lapor',
    user: 'pemohon',
    async run(page, r, say) {
      const foto = await r.sampleImage('ac-bocor.png', '<div style="background:#e0f2fe;height:352px;padding:24px;box-sizing:border-box"><b>FOTO CONTOH</b><br>AC ruang rapat lantai 2 meneteskan air</div>');
      await titleCard(page, 'Bagian 2 · Lapor Gangguan', 'Laporkan kerusakan → petugas memperbaiki → konfirmasi pekerjaan selesai.<br><span style="opacity:.8">Semua karyawan bisa melapor, walaupun belum punya role.</span>', 4500);
      await page.goto(`${BASE}/admin`);
      await page.waitForLoadState('networkidle');
      await say('Buka menu <b>Laporan Gangguan</b>');
      await openMenu(page, r, 'Laporan Gangguan', 'problem-reports');
      await say('Anda hanya melihat laporan milik sendiri. Klik <b>Lapor Gangguan</b>', 2500);
      await r.click(page.locator('.fi-header').getByRole('link', { name: 'Lapor Gangguan' }).first());
      await waitPage(page, /create/);
      await say('Pilih <b>Jenis Gangguan</b>');
      await r.select('Jenis Gangguan', 'AC');
      await say('Isi <b>Judul</b> (opsional) dan <b>Keterangan Gangguan</b> sejelas mungkin');
      await r.fill('Judul (opsional)', 'AC ruang rapat bocor');
      await r.fill('Keterangan Gangguan', 'AC di ruang rapat lantai 2 meneteskan air sejak pagi.');
      await say('Lampirkan <b>Foto Gangguan / Kerusakan</b> (wajib)');
      await r.upload('Foto Gangguan / Kerusakan', foto);
      await say('Lalu klik <b>Buat</b>', 1200);
      await r.click(page.locator('button[type=submit]').getByText('Buat', { exact: true }));
      await waitPage(page, /problem-reports\/\d+$/);
      await r.pause(800);
      await say('Laporan terkirim dengan kode <b>PR…</b> dan status <b>Menunggu</b>', 3500);
      await smoothScroll(page, 450);
      await say('Satu karyawan hanya boleh punya <b>satu laporan yang belum dikonfirmasi</b>.<br>Laporan berikutnya bisa dibuat setelah yang ini selesai', 5000);
    },
  },
  {
    id: 'x-executor-gangguan',
    user: 'executor',
    offCamera: true,
    async run(page, r) {
      const foto = await r.sampleImage('ac-beres.png', '<div style="background:#dcfce7;height:352px;padding:24px;box-sizing:border-box"><b>FOTO CONTOH</b><br>Selang pembuangan AC sudah diganti</div>');
      await page.goto(`${BASE}/admin`);
      await openMenu(page, r, 'Laporan Gangguan', 'problem-reports');
      await openFirstRow(page, r, 'problem-reports');
      await r.action('Proses (Executor)');
      await r.radio('Selesai');
      await r.upload('Foto Hasil', foto);
      await r.fill('Hasil Pengerjaan', 'Selang pembuangan diganti, AC normal kembali.');
      await r.submitModal();
    },
  },
  {
    id: '05-gangguan-konfirmasi',
    user: 'pemohon',
    async run(page, r, say) {
      await titleCard(page, 'Gangguan sudah diperbaiki', 'Petugas menandai laporan <b>Selesai</b> dan melampirkan foto hasil.<br>Sekarang pelapor mengecek dan mengonfirmasi.', 3500);
      await page.goto(`${BASE}/admin`);
      await page.waitForLoadState('networkidle');
      await openMenu(page, r, 'Laporan Gangguan', 'problem-reports');
      await say('Status laporan sudah <b>Selesai</b>. Buka detailnya', 2500);
      await openFirstRow(page, r, 'problem-reports');
      await say('Cek hasil pengerjaan dari petugas', 2000);
      await smoothScroll(page, 500);
      await say('Bandingkan foto <b>sebelum</b> dan <b>sesudah</b> perbaikan', 3500);
      await smoothScroll(page, -500);
      await say('Kalau sudah beres, klik <b>Konfirmasi Selesai</b>', 1500);
      await r.action('Konfirmasi Selesai');
      await say('Beri <b>ulasan</b> (opsional), lalu konfirmasi');
      await r.fill('Ulasan (opsional)', 'Terima kasih, sudah tidak bocor.');
      await r.pause(800);
      await r.submitModal();
      await say('Laporan ditutup dan terkunci ✔', 4500);
    },
  },
];


// ---------- Video sisi Tim GA (petugas yang memproses) ----------
const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
async function refill(r, label, text) {
  const f = r.field(label).locator('textarea,input').first();
  await r.click(f);
  await f.fill('');
  await r.page.keyboard.type(text, { delay: 90 });
  await r.pause(400);
}
async function highlight(page, loc, on = true) {
  await loc.evaluate((e, v) => { e.style.outline = v ? '3px solid #ef4444' : ''; e.style.outlineOffset = '4px'; e.style.borderRadius = '12px'; }, on);
}
async function hoverTo(page, loc) {
  await loc.scrollIntoViewIfNeeded();
  const b = await loc.boundingBox();
  await page.mouse.move(b.x + b.width / 2, b.y + b.height / 2, { steps: 18 });
}
const GA = [
  {
    id: 'x-pemohon-ajukan',
    user: 'pemohon',
    offCamera: true,
    async run(page, r) {
      await page.goto(`${BASE}/admin/item-requests/create`);
      await page.waitForLoadState('networkidle');
      await r.select('Tipe Pengajuan', 'ATK');
      await r.select('Barang', 'KERTAS A4 80GR');
      await r.fill('Jumlah', '5');
      await r.fill('Keterangan', 'Kebutuhan cetak laporan bulanan');
      await r.click(page.getByRole('button', { name: 'Tambah barang' }).first());
      await page.locator('.fi-fo-repeater-item').nth(1).waitFor();
      await r.select('Barang', 'PULPEN HITAM');
      await r.fill('Jumlah', '10');
      await r.click(page.locator('button[type=submit]:visible').first());
      await waitPage(page, /item-requests\/\d+$/);
      const foto = await r.sampleImage('ac-bocor.png', '<div style="background:#e0f2fe;height:352px;padding:24px;box-sizing:border-box"><b>FOTO CONTOH</b><br>AC ruang rapat lantai 2 meneteskan air</div>');
      await page.goto(`${BASE}/admin/problem-reports/create`);
      await page.waitForLoadState('networkidle');
      await r.select('Jenis Gangguan', 'AC');
      await r.fill('Judul (opsional)', 'AC ruang rapat bocor');
      await r.fill('Keterangan Gangguan', 'AC di ruang rapat lantai 2 meneteskan air sejak pagi.');
      await r.upload('Foto Gangguan / Kerusakan', foto);
      await r.click(page.locator('button[type=submit]').getByText('Buat', { exact: true }));
      await waitPage(page, /problem-reports\/\d+$/);
    },
  },
  {
    id: '01-ga-dasbor',
    user: 'executor',
    async run(page, r, say) {
      await titleCard(page, 'Pengajuan Barang &amp; Lapor Gangguan<br>dari sisi Tim GA',
        'Fitur dari aplikasi Sumo sudah pindah ke GAIS.<br>Video ini dari sudut pandang <b>petugas GA</b> yang memproses permintaan karyawan.', 5500);
      await page.goto(`${BASE}/admin`);
      await page.waitForLoadState('networkidle');
      await say('Login sama seperti karyawan, lewat <b>WhatsApp</b> atau <b>Email</b>. Setelah masuk, Anda tiba di <b>Dasbor</b>', 3500);
      const box = page.locator('.fi-section, .fi-wi-widget').filter({ hasText: 'Perlu Tindakan' }).first();
      await hoverTo(page, box);
      await highlight(page, box);
      await say('Bagian <b>Perlu Tindakan</b> menunjukkan antrean yang harus dikerjakan', 3500);
      await highlight(page, box, false);
      for (const [card, text] of [
        ['Pengajuan Menunggu Executor', 'Ada <b>1 pengajuan barang</b> yang menunggu diproses'],
        ['Gangguan Terbuka', 'Ada <b>1 laporan gangguan</b> yang belum ditutup'],
      ]) {
        const c = page.getByText(card, { exact: true }).first();
        await hoverTo(page, c);
        await highlight(page, c);
        await say(text, 3000);
        await highlight(page, c, false);
      }
      const nav = page.locator('aside').first();
      const link = nav.getByRole('link', { name: 'Pengajuan Barang' }).first();
      await hoverTo(page, link);
      await say('Angka di menu samping juga menunjukkan jumlah yang menunggu', 3000);
      await say('');
    },
  },
  {
    id: '02-ga-pengajuan',
    user: 'executor',
    async run(page, r, say) {
      await titleCard(page, 'Bagian 1 · Memproses Pengajuan Barang', 'Cek pengajuan → tandai Diproses → tandai Selesai → pemohon mengonfirmasi penerimaan');
      await page.goto(`${BASE}/admin`);
      await page.waitForLoadState('networkidle');
      await say('Klik kartu <b>Pengajuan Menunggu Executor</b> untuk membuka antreannya');
      await r.click(page.getByText('Pengajuan Menunggu Executor', { exact: true }).first());
      await waitPage(page, /item-requests/);
      await r.pause(1000);
      await say('Daftar sudah tersaring: hanya pengajuan yang belum diproses. Klik <b>Lihat</b>', 2500);
      await openFirstRow(page, r, 'item-requests');
      await say('Cek pemohon, divisi, tipe pengajuan, dan total biaya', 3500);
      await smoothScroll(page, 450);
      await say('Cek barang yang diminta beserta jumlah, harga, dan subtotal', 3500);
      await smoothScroll(page, -450);
      const header = page.locator('.fi-header');
      if (!(await header.getByRole('button', { name: 'Proses (Executor)', exact: true }).count())
        && (await header.getByRole('button', { name: 'Setujui Jumlah (Manager)', exact: true }).count())) {
        await say('Kalau divisi Anda bertugas menyetujui jumlah, klik <b>Setujui Jumlah (Manager)</b> dulu');
        await r.action('Setujui Jumlah (Manager)');
        await r.submitModal();
      }
      await say('Klik <b>Proses (Executor)</b>');
      await r.action('Proses (Executor)');
      await say('Barang sedang disiapkan? Pilih <b>Diproses</b> dan tulis catatan untuk pemohon');
      await r.radio('Diproses');
      await refill(r, 'Catatan', 'Sedang disiapkan, bisa diambil besok siang');
      await r.pause(600);
      await r.submitModal();
      await say('Status menjadi <b>Diproses</b>. Pemohon bisa melihat catatan Anda', 3500);
      await say('Setelah barang siap diserahkan, klik <b>Proses (Executor)</b> lagi');
      await r.action('Proses (Executor)');
      await say('Pilih <b>Selesai</b> dan perbarui catatannya');
      await r.radio('Selesai');
      await refill(r, 'Catatan', 'Barang sudah bisa diambil di ruang GA');
      await r.pause(600);
      await r.submitModal();
      await say('Status <b>Selesai</b>. Sekarang giliran pemohon mengonfirmasi penerimaan', 3500);
      await say('Pilihan lain: <b>Dibatalkan</b> kalau tidak bisa dipenuhi, atau <b>Menunggu</b> untuk mengembalikan ke antrean', 4500);
      await say('');
    },
  },
  {
    id: '03-ga-gangguan',
    user: 'executor',
    async run(page, r, say) {
      const foto = await r.sampleImage('ac-beres.png', '<div style="background:#dcfce7;height:352px;padding:24px;box-sizing:border-box"><b>FOTO CONTOH</b><br>Selang pembuangan AC sudah diganti</div>');
      await titleCard(page, 'Bagian 2 · Menangani Lapor Gangguan', 'Cek laporan → isi jadwal pengerjaan → perbaiki → tandai Selesai dengan foto hasil');
      await page.goto(`${BASE}/admin`);
      await page.waitForLoadState('networkidle');
      await say('Buka menu <b>Laporan Gangguan</b>');
      await openMenu(page, r, 'Laporan Gangguan', 'problem-reports');
      const tab = page.locator('.fi-tabs-item', { hasText: 'Menunggu' }).first();
      if (await tab.count()) {
        await say('Tab <b>Menunggu</b> berisi laporan yang belum ditutup');
        await r.click(tab);
        await page.waitForLoadState('networkidle');
        await r.pause(1200);
      }
      await openFirstRow(page, r, 'problem-reports');
      await say('Cek jenis gangguan, keterangan, dan foto kerusakan dari pelapor', 4000);
      await say('Belum bisa langsung dikerjakan? Klik <b>Proses (Executor)</b>');
      await r.action('Proses (Executor)');
      await say('Biarkan status <b>Menunggu</b>, lalu isi <b>Jadwal Pengerjaan</b>');
      await r.date('Jadwal Pengerjaan', tomorrow);
      await r.pause(600);
      await r.submitModal();
      await say('Jadwal tersimpan dan terlihat oleh pelapor', 3500);
      await say('Setelah diperbaiki, klik <b>Proses (Executor)</b> lagi');
      await r.action('Proses (Executor)');
      await say('Pilih <b>Selesai</b>, lampirkan <b>Foto Hasil</b>, dan tulis <b>Hasil Pengerjaan</b>');
      await r.radio('Selesai');
      await r.upload('Foto Hasil', foto);
      await r.fill('Hasil Pengerjaan', 'Selang pembuangan diganti, AC normal kembali.');
      await r.pause(600);
      await r.submitModal();
      await say('Laporan <b>Selesai</b> dan nama Anda tercatat sebagai petugas yang menutup', 3500);
      await smoothScroll(page, 500);
      await say('Pelapor bisa membandingkan foto <b>sebelum</b> dan <b>sesudah</b>, lalu mengonfirmasi', 4000);
      await say('Kalau ternyata belum beres, ubah status kembali ke <b>Menunggu</b> lewat Proses (Executor)', 4000);
      await say('');
    },
  },
  {
    id: 'x-pemohon-konfirmasi',
    user: 'pemohon',
    offCamera: true,
    async run(page, r) {
      await page.goto(`${BASE}/admin/item-requests`);
      await page.waitForLoadState('networkidle');
      await openFirstRow(page, r, 'item-requests');
      await r.action('Konfirmasi Penerimaan');
      await r.fill('Catatan', 'Sudah diterima lengkap, terima kasih');
      await r.submitModal();
      await page.goto(`${BASE}/admin/problem-reports`);
      await page.waitForLoadState('networkidle');
      await openFirstRow(page, r, 'problem-reports');
      await r.action('Konfirmasi Selesai');
      await r.fill('Ulasan (opsional)', 'Terima kasih, sudah tidak bocor.');
      await r.submitModal();
    },
  },
  {
    id: '04-ga-pantau',
    user: 'executor',
    async run(page, r, say) {
      await titleCard(page, 'Pemohon sudah mengonfirmasi', 'Pengajuan <b>Diterima</b> dan laporan gangguan sudah dikonfirmasi pelapor.<br>Petugas GA bisa memantau hasilnya.', 4000);
      await page.goto(`${BASE}/admin`);
      await page.waitForLoadState('networkidle');
      await say('Antrean di <b>Perlu Tindakan</b> sudah kosong', 3000);
      await openMenu(page, r, 'Pengajuan Barang', 'item-requests');
      await say('Status akhir pengajuan: <b>Diterima</b>', 3000);
      await openFirstRow(page, r, 'item-requests');
      await say('Riwayat persetujuan dan catatan pemohon tersimpan lengkap', 4000);
      await openMenu(page, r, 'Laporan Gangguan', 'problem-reports');
      await say('Laporan gangguan: konfirmasi pelapor <b>Selesai</b>', 3000);
      const exp = page.locator('.fi-header').getByRole('button', { name: /Ekspor/ }).first();
      if (await exp.count()) {
        await hoverTo(page, exp);
        await say('Rekap laporan bisa diunduh lewat <b>Ekspor Laporan</b>', 3500);
      }
      await say('');
      await r.pause(1500);
    },
  },
];
const DEMO = process.env.DEMO || 'pemohon';
const scenes = { pemohon: PEMOHON, ga: GA }[DEMO];
if (!scenes) throw new Error(`DEMO tidak dikenal: ${DEMO} (pilih pemohon atau ga)`);

// ---------- Main ----------
const only = process.argv[2]?.split(',');
const waSrv = startFakeWhatsApp();
const smtpSrv = startFakeSmtp();
Object.assign(process.env, {
  WAG_URL: `http://127.0.0.1:${WA_PORT}`, WAG_TOKEN: 'demo',
  MAIL_MAILER: 'smtp', MAIL_SCHEME: 'smtp', MAIL_HOST: '127.0.0.1', MAIL_PORT: String(SMTP_PORT),
  MAIL_USERNAME: '', MAIL_PASSWORD: '', MAIL_FROM_ADDRESS: 'gais@capture.test', MAIL_FROM_NAME: 'GAIS',
});
resetData(DB);
const srv = await startServer(DB);
const browser = await chromium.launch();
const tmp = mkdtempSync(join(tmpdir(), 'gais-demo-'));
const clips = [];
try {
  const states = {};
  const sessionFor = async (username) => {
    if (states[username]) return states[username];
    const login = await browser.newContext({ viewport: VIEW });
    const lp = await login.newPage();
    await lp.goto(`${BASE}/admin/login`);
    await lp.fill('input[type=text]', username);
    await lp.fill('input[type=password]', 'password');
    await lp.keyboard.press('Enter');
    await lp.waitForURL((u) => !u.pathname.includes('/login'));
    states[username] = await login.storageState();
    await login.close();
    return states[username];
  };

  for (const s of scenes) {
    if (only && !only.includes(s.id)) continue;
    console.log(`> ${s.id}${s.offCamera ? ' (di luar kamera)' : ''}`);
    const ctx = await browser.newContext({
      viewport: VIEW,
      ...(s.anonymous ? {} : { storageState: await sessionFor(s.user) }),
      ...(s.offCamera ? {} : { recordVideo: { dir: tmp, size: VIEW } }),
    });
    await ctx.addInitScript(CURSOR_JS);
    const t0 = Date.now();
    const page = await ctx.newPage();
    let firstPaint = 0;
    page.once('domcontentloaded', () => { firstPaint = (Date.now() - t0) / 1000; });
    const video = page.video();
    const r = new Recorder(page, s.id);
    r.shot = async () => {}; // video saja, tanpa screenshot ke folder panduan
    narr = video && NARRATION ? newNarrator(t0) : null;
    try {
      await s.run(page, r, keepCaption(page));
      if (narr) await narr.waitFree(page);
      await page.waitForTimeout(800);
    } catch (e) {
      await page.screenshot({ path: join(import.meta.dirname, 'fail.png') });
      console.error('GAGAL di', s.id, '- screenshot: fail.png');
      throw e;
    } finally {
      await ctx.close();
    }
    const total = (Date.now() - t0) / 1000;
    if (video) {
      const out = join(tmp, `${s.id}.mp4`);
      const trim = Math.max(0, firstPaint - 0.1);
      // Audio: hening sebagai dasar + tiap narasi digeser ke waktunya, lalu dicampur.
      const cues = narr ? narr.cues : [];
      const len = (total - trim).toFixed(2);
      const inputs = ['-ss', String(trim), '-i', await video.path(), '-f', 'lavfi', '-t', len, '-i', 'anullsrc=r=44100:cl=stereo'];
      cues.forEach((c) => inputs.push('-i', c.file));
      const fmt = 'aresample=44100,aformat=sample_fmts=fltp:channel_layouts=stereo';
      const parts = cues.map((c, i) => `[${i + 2}:a]${fmt},adelay=delays=${Math.max(0, Math.round((c.at - trim) * 1000))}:all=1[a${i}]`);
      const mix = `[1:a]${fmt}[base];${parts.length ? parts.join(';') + ';' : ''}[base]${cues.map((_, i) => `[a${i}]`).join('')}amix=inputs=${cues.length + 1}:normalize=0:duration=first[aout]`;
      execFileSync(ffmpeg, ['-y', ...inputs, '-filter_complex', `[0:v]fps=30,format=yuv420p[v];${mix}`,
        '-map', '[v]', '-map', '[aout]', '-c:v', 'libx264', '-preset', 'slow', '-crf', '18', '-c:a', 'aac', '-b:a', '160k', '-t', len, out], { stdio: 'ignore' });
      clips.push(out);
    }
  }

  const list = join(tmp, 'list.txt');
  writeFileSync(list, clips.map((c) => `file '${c}'`).join('\n'));
  const final = join(OUTDIR, only ? `demo-${DEMO}-${only.join('_')}.mp4` : `demo-${DEMO}.mp4`);
  const joined = join(tmp, 'joined.mp4');
  execFileSync(ffmpeg, ['-y', '-f', 'concat', '-safe', '0', '-i', list, '-vf', 'fps=30,format=yuv420p', '-c:v', 'libx264', '-preset', 'slow', '-crf', '18', '-c:a', 'aac', '-b:a', '160k', joined], { stdio: 'ignore' });
  if (NARRATION) {
    execFileSync(ffmpeg, ['-y', '-i', joined, '-c', 'copy', '-movflags', '+faststart', final], { stdio: 'ignore' });
  } else {
    // Musik latar: MUSIC_FILE (lagu sendiri, diulang bila kurang panjang) atau musik orisinal dari music.mjs.
    const { stdout } = await execFileP(ffprobe, ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', joined]);
    const dur = Number(stdout.trim());
    let music = process.env.MUSIC_FILE;
    if (!music) { music = join(tmp, 'music.wav'); makeMusic(music, dur + 1); }
    const vol = process.env.MUSIC_VOLUME || '0.6';
    execFileSync(ffmpeg, ['-y', '-i', joined, '-stream_loop', '-1', '-i', music, '-filter_complex',
      `[1:a]volume=${vol},afade=t=in:d=2,afade=t=out:st=${Math.max(0, dur - 4).toFixed(2)}:d=4[m]`,
      '-map', '0:v', '-map', '[m]', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '192k', '-t', dur.toFixed(2), '-movflags', '+faststart', final], { stdio: 'ignore' });
  }
  console.log('Selesai:', final);
} finally {
  await browser.close();
  srv.kill();
  waSrv.close();
  smtpSrv.close();
  resetPermissionCache(DB);
  if (process.env.KEEP) console.log('tmp:', tmp); else rmSync(tmp, { recursive: true, force: true });
}
