// Membuat screenshot + gambar bergerak (GIF) untuk halaman Panduan (/admin/panduan) dan docs/.
// Jalankan: npm run capture   (DB: db_gais_capture, lihat README.md di folder ini)
import { chromium } from 'playwright';
import { mkdtempSync, rmSync, readdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, BASE, CURSOR_JS, Recorder, startServer, resetData, resetPermissionCache, toGif, cleanOld, OUT } from './lib.mjs';

const DB = process.env.CAPTURE_DB || 'db_gais_capture';
// Contoh: node capture.mjs setup   atau   node capture.mjs --reset pengajuan-buat,pengajuan-proses
const args = process.argv.slice(2);
const only = args.find((a) => !a.startsWith('--'))?.split(',');
const sections = (await import('./sections.mjs')).default;

if (!only || args.includes('--reset')) resetData(DB);
const srv = await startServer(DB);
const browser = await chromium.launch();
const tmp = mkdtempSync(join(tmpdir(), 'gais-capture-'));
try {
  // Login sekali per akun, simpan sesinya (tidak direkam). Bagian memilih akun lewat `user` (default admin).
  const states = {};
  const sessionFor = async (username) => {
    if (states[username]) return states[username];
    const login = await browser.newContext({ viewport: { width: 1366, height: 768 } });
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

  for (const s of sections) {
    if (only && !only.includes(s.id)) continue;
    console.log(`> ${s.id}`);
    cleanOld(s.id);
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 768 }, ...(s.anonymous ? {} : { storageState: await sessionFor(s.user || 'admin') }), recordVideo: { dir: tmp, size: { width: 1366, height: 768 } } });
    await ctx.addInitScript(CURSOR_JS);
    const t0 = Date.now();
    const page = await ctx.newPage();
    let loadedAt = 0; // detik sejak video mulai sampai halaman pertama termuat
    page.once('load', () => { loadedAt = (Date.now() - t0) / 1000; });
    const video = page.video();
    const rec = new Recorder(page, s.id);
    try { await s.run(page, rec); } catch (e) {
      const fail = join(import.meta.dirname, 'fail.png');
      await page.screenshot({ path: fail });
      console.error('GAGAL, screenshot:', fail);
      console.error(await page.locator('.fi-fo-field').evaluateAll((x) => x.map((e) => e.innerText.split('\n')[0] + ' :: ' + e.querySelector('button,input,select')?.outerHTML.slice(0, 160))));
      throw e;
    } finally {
      await ctx.close();
      toGif(await video.path(), join(OUT, `${s.id}.gif`), Math.max(0, loadedAt - 0.2));
    }
    console.log(`  ${rec.shots.length} screenshot + ${s.id}.gif`);
  }
} finally {
  await browser.close();
  srv.kill();
  resetPermissionCache(DB);
  if (!process.exitCode) rmSync(tmp, { recursive: true, force: true });
}
