import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, rmSync, readdirSync, renameSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import ffmpeg from 'ffmpeg-static';

export const ROOT = resolve(import.meta.dirname, '../..');
export const OUT = join(ROOT, 'public/images/guide');
export const PORT = 8099;
export const BASE = `http://127.0.0.1:${PORT}`;
export const PHP = process.env.PHP_BIN || 'C:/laragon/bin/php/php-8.4.25-Win32-vs17-x64/php.exe';

// Server Laravel khusus capture: DB terpisah, queue sync, tidak menyentuh DB utama.
export async function startServer(db) {
  if (!db.includes('capture')) throw new Error(`DB "${db}" bukan DB capture; nama harus mengandung "capture".`);
  resetPermissionCache(db);
  const srv = spawn(PHP, ['artisan', 'serve', `--port=${PORT}`, '--no-reload'], {
    cwd: ROOT,
    env: { ...process.env, DB_DATABASE: db, APP_URL: BASE, QUEUE_CONNECTION: 'sync', SEED_SUPER_ADMIN_PASSWORD: '' },
    stdio: 'ignore',
  });
  for (let i = 0; i < 40; i++) {
    try { if ((await fetch(`${BASE}/up`)).ok) return srv; } catch {}
    await new Promise((r) => setTimeout(r, 500));
  }
  srv.kill();
  throw new Error('Server artisan tidak mau start');
}

// Cache permission (Spatie) dipakai bersama DB utama: kosongkan sebelum dan sesudah capture.
export function resetPermissionCache(db) {
  execFileSync(PHP, ['artisan', 'permission:cache-reset'], { cwd: ROOT, env: { ...process.env, DB_DATABASE: db }, stdio: 'pipe' });
}

// Kursor + efek klik palsu: video Playwright tidak merekam kursor asli.
export const CURSOR_JS = `
(() => {
  if (window.__cur) return; window.__cur = 1;
  const add = () => {
    const c = document.createElement('div');
    c.style.cssText = 'position:fixed;z-index:2147483647;pointer-events:none;width:22px;height:22px;border-radius:50%;background:rgba(239,68,68,.35);border:2px solid #ef4444;transform:translate(-50%,-50%);transition:left .12s,top .12s;left:-50px;top:-50px';
    document.documentElement.appendChild(c);
    addEventListener('mousemove', e => { c.style.left = e.clientX + 'px'; c.style.top = e.clientY + 'px'; }, true);
    addEventListener('mousedown', e => {
      const r = document.createElement('div');
      r.style.cssText = 'position:fixed;z-index:2147483647;pointer-events:none;border-radius:50%;border:3px solid #ef4444;width:10px;height:10px;transform:translate(-50%,-50%);transition:all .45s ease-out;opacity:1;left:' + e.clientX + 'px;top:' + e.clientY + 'px';
      document.documentElement.appendChild(r);
      requestAnimationFrame(() => { r.style.width = '56px'; r.style.height = '56px'; r.style.opacity = '0'; });
      setTimeout(() => r.remove(), 600);
    }, true);
  };
  document.documentElement ? add() : addEventListener('DOMContentLoaded', add);
})();`;

// Hapus data contoh hasil capture sebelumnya supaya hasilnya selalu bisa diulang.
export function resetData(db) {
  if (!db.includes('capture')) throw new Error(`DB "${db}" bukan DB capture.`);
  // Hapus data buatan bagian-bagian capture, lalu seed ulang: aset stok kembali Tersedia, akun contoh lengkap lagi.
  const tables = ['item_request_logs', 'item_request_approvals', 'item_request_details', 'item_requests', 'problem_reports',
    'asset_transfer_details', 'asset_transfers', 'asset_service_items', 'asset_services', 'ob_checksheets', 'vehicle_checksheets',
    'insurance_updates', 'insurances', 'rent_updates', 'rents', 'asset_reconciliation_items', 'asset_reconciliations', 'tasks'];
  const code = String.raw`App\Models\Asset::withoutGlobalScopes()->where(fn ($q) => $q->where('name', 'like', 'Toyota Avanza%')->orWhere('name', 'like', '%GRAN MAX%')->orWhere('name', 'TINTA PRINTER EPSON 003'))->get()->each->forceDelete();`
    + String.raw` App\Models\CustomAssetAttribute::where('name', 'Dokumen STNK Mobil')->delete();`
    + String.raw` App\Models\User::where('email', 'like', '%@contoh.test')->delete();`
    + ` foreach (['${tables.join("', '")}'] as $t) ` + String.raw`Illuminate\Support\Facades\DB::table($t)->delete();`;
  const env = { ...process.env, DB_DATABASE: db, SEED_SUPER_ADMIN_PASSWORD: '' };
  execFileSync(PHP, ['artisan', 'tinker', '--execute', code], { cwd: ROOT, env, stdio: 'pipe' });
  execFileSync(PHP, ['artisan', 'db:seed', '--class=GuideCaptureSeeder', '--force'], { cwd: ROOT, env, stdio: 'pipe' });
}

export class Recorder {
  constructor(page, section) {
    this.page = page; this.section = section; this.n = 0; this.shots = [];
    mkdirSync(OUT, { recursive: true });
  }
  async pause(ms = 700) { await this.page.waitForTimeout(ms); }

  // Gerakkan kursor ke elemen lalu klik (terlihat di video).
  async click(loc) {
    await loc.scrollIntoViewIfNeeded();
    const b = await loc.boundingBox();
    await this.page.mouse.move(b.x + b.width / 2, b.y + b.height / 2, { steps: 18 });
    await this.pause(250);
    await this.page.mouse.down(); await this.page.mouse.up();
    await this.pause(500);
  }
  async type(loc, text) {
    await this.click(loc);
    await this.page.keyboard.type(text, { delay: 90 });
    await this.pause(400);
  }
  // Field terdalam dengan label persis (repeater membungkus field lain, jadi ambil yang terakhir).
  field(label) {
    const escaped = label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const exact = new RegExp(`^\\s*${escaped}\\s*\\*?\\s*$`);
    return this.page.locator('.fi-fo-field:visible').filter({ has: this.page.locator('.fi-fo-field-label', { hasText: exact }) }).last();
  }
  async fill(label, text) { await this.type(this.field(label).locator('input,textarea').first(), text); }
  async select(label, option) {
    await this.field(label).locator('select, .fi-select-input-btn').first().waitFor();
    const native = this.field(label).locator('select');
    if (await native.count()) { // <select> biasa: dropdown native tidak terekam, jadi cukup kursor + pilih
      const b = await native.boundingBox();
      await this.page.mouse.move(b.x + b.width / 2, b.y + b.height / 2, { steps: 18 });
      await this.pause(400);
      await native.selectOption(await native.locator('option', { hasText: option }).first().getAttribute('value'));
      await this.pause(500);
      return;
    }
    // Select Filament kadang menutup sebelum pilihan terekam (re-render Livewire); ulangi sampai nilainya terpasang.
    for (let attempt = 0; attempt < 3; attempt++) {
      await this.click(this.field(label).locator('.fi-select-input-btn').first());
      // Cari pilihan di dalam field ini dulu: di repeater, dropdown baris lain bisa ikut 'visible'.
      const inField = this.field(label).locator('[role=option]:visible', { hasText: option });
      const opt = (await inField.count()) ? inField.first() : this.page.locator('[role=option]:visible', { hasText: option }).first();
      // Select tanpa preload baru memuat pilihan setelah diketik di kotak pencariannya.
      if (!(await opt.waitFor({ timeout: 2500 }).then(() => true, () => false))) {
        await this.field(label).locator('input:visible').first().pressSequentially(option.slice(0, 8), { delay: 90 });
      }
      await opt.waitFor();
      await this.pause(600); // tunggu daftar selesai dimuat/diposisikan
      const b = await opt.boundingBox();
      await this.page.mouse.move(b.x + b.width / 2, b.y + b.height / 2, { steps: 15 });
      await this.pause(250);
      await opt.click();
      await this.pause(1200); // beri waktu roundtrip Livewire sebelum mengetik lagi
      if ((await this.field(label).innerText()).includes(option)) break;
      await this.closeDropdown(label);
    }
    await this.closeDropdown(label);
    await this.pause(300);
  }
  // Escape hanya bila dropdown masih terbuka: di dalam modal, Escape juga menutup modalnya.
  async closeDropdown(label) {
    if (await this.field(label).locator('[aria-expanded=true]').count()) await this.page.keyboard.press('Escape').catch(() => {});
    await this.pause(300);
  }

  // Tanggal: input native (type=date / datetime-local) diisi langsung; picker JS Filament diklik pada angka harinya
  // (hanya untuk tanggal di bulan yang sedang tampil, mis. hari ini).
  async date(label, iso) {
    const native = this.field(label).locator('input[type=date], input[type=datetime-local]');
    if (await native.count()) {
      await this.click(native);
      await native.fill(iso);
    } else {
      await this.click(this.field(label).locator('button:visible, input:visible').first());
      // Pindah bulan/tahun lewat kontrol di kepala kalender bila tanggalnya bukan di bulan yang sedang tampil.
      const month = this.field(label).locator('.fi-fo-date-time-picker-month-select:visible');
      const year = this.field(label).locator('.fi-fo-date-time-picker-year-input:visible');
      if (await year.inputValue() !== iso.slice(0, 4)) {
        await year.fill(iso.slice(0, 4));
        await this.pause(400);
      }
      await month.selectOption(String(Number(iso.slice(5, 7)) - 1));
      await this.pause(400);
      const day = String(Number(iso.slice(8, 10)));
      await this.click(this.field(label).locator('.fi-fo-date-time-picker-calendar:visible').getByText(day, { exact: true }).first());
      const stillOpen = await this.field(label).locator('.fi-fo-date-time-picker-calendar:visible').count();
      if (stillOpen && !(await this.page.locator('.fi-modal.fi-modal-open').count())) await this.page.keyboard.press('Escape').catch(() => {});
    }
    await this.pause(1500); // field tanggal 'live': tunggu roundtrip Livewire
  }
  // Foto lewat komponen kamera (CameraCapture): pakai tombol "Pilih dari Galeri", lalu "Gunakan Foto Ini".
  async camera(label, file, field = this.field(label)) {
    await field.scrollIntoViewIfNeeded();
    const chooser = this.page.waitForEvent('filechooser');
    await this.click(field.getByRole('button', { name: 'Pilih dari Galeri' }));
    await (await chooser).setFiles(file);
    const use = this.page.getByRole('button', { name: 'Gunakan Foto Ini' }).filter({ visible: true }).last();
    await use.waitFor({ timeout: 15000 });
    await this.pause(1200);
    await this.click(use);
    await field.getByText('Tersimpan').first().waitFor({ timeout: 30000 });
    await this.pause(1000);
  }
  async upload(label, file) {
    const f = this.field(label);
    await f.scrollIntoViewIfNeeded();
    await this.pause(400);
    await f.locator('input[type=file]').setInputFiles(file);
    // Tunggu unggahan sementara selesai; re-render Livewire setelahnya bisa memotong ketikan berikutnya.
    await f.locator('.filepond--item[data-filepond-item-state*="complete"]').waitFor({ timeout: 30000 });
    await this.pause(1500);
  }
  // Tombol aksi di header halaman (mis. "Proses (Executor)") lalu tunggu modalnya.
  async action(label) {
    await this.click(this.page.locator('.fi-header').getByRole('button', { name: label, exact: true }).first());
    await this.modal().waitFor();
    await this.pause(600);
  }
  modal() { return this.page.locator('.fi-modal.fi-modal-open .fi-modal-window').last(); }
  async radio(option) { await this.click(this.modal().getByRole('radio', { name: option, exact: true })); }
  // Tombol kirim modal ("Kirim" untuk form, "Konfirmasi" untuk konfirmasi); tunggu modal tertutup.
  async submitModal() {
    const modal = this.modal();
    await this.click(modal.getByRole('button', { name: /^(Kirim|Konfirmasi|Simpan.*|Tugaskan)$/ }).last());
    await modal.waitFor({ state: 'hidden', timeout: 30000 });
    await this.pause(1500);
  }
  // Gambar contoh untuk diunggah (dibuat on-the-fly, tidak disimpan di repo).
  async sampleImage(name, html) {
    const file = join(mkdtempSync(join(tmpdir(), 'sample-')), name);
    const p2 = await this.page.context().newPage();
    await p2.setContent(`<body style="margin:0;width:640px;height:400px;font:28px sans-serif;padding:24px">${html}</body>`);
    await p2.screenshot({ path: file, clip: { x: 0, y: 0, width: 640, height: 400 } });
    await p2.close();
    return file;
  }
  async toggle(label) { await this.click(this.field(label).locator('button[role=switch], button').first()); }

  // Screenshot bernomor; elemen target diberi bingkai merah supaya jelas.
  async shot(slug, target) {
    if (target) await target.evaluate((e) => { e.dataset.o = e.style.outline; e.style.outline = '3px solid #ef4444'; e.style.outlineOffset = '4px'; });
    await this.pause(300);
    const file = `${this.section}-${String(++this.n).padStart(2, '0')}-${slug}.png`;
    await this.page.screenshot({ path: join(OUT, file) });
    if (target) await target.evaluate((e) => { e.style.outline = e.dataset.o || ''; });
    this.shots.push(file);
    return file;
  }
}

// webm -> GIF animasi (palet dioptimalkan supaya ukuran wajar, loop terus)
export function toGif(webm, out, start = 0) {
  const vf = 'fps=6,scale=800:-1:flags=lanczos,split[a][b];[a]palettegen=max_colors=48:stats_mode=diff[p];[b][p]paletteuse=dither=none:diff_mode=rectangle';
  execFileSync(ffmpeg, ['-y', '-ss', String(start), '-i', webm, '-vf', vf, '-loop', '0', out], { stdio: 'ignore' });
}
export function cleanOld(section) {
  mkdirSync(OUT, { recursive: true });
  for (const f of readdirSync(OUT)) if (f.startsWith(section + '-') || f === section + '.gif') rmSync(join(OUT, f));
}
export { join, renameSync };
