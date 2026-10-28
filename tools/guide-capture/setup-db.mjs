// Membuat/menyegarkan DB capture: salin struktur (tanpa data) dari DB utama + isi seed minimum.
// Tidak pernah menulis ke DB utama. Jalankan: npm run setup-db
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { join, ROOT } from './lib.mjs';

const env = Object.fromEntries(readFileSync(join(ROOT, '.env'), 'utf8').split(/\r?\n/).filter((l) => /^[A-Z_]+=/.test(l)).map((l) => [l.split('=')[0], l.slice(l.indexOf('=') + 1).replace(/^"|"$/g, '')]));
const SRC = env.DB_DATABASE, DST = process.env.CAPTURE_DB || 'db_gais_capture';
if (!DST.includes('capture') || DST === SRC) throw new Error('DB tujuan harus berbeda dari DB utama dan bernama *capture*');

const bin = process.env.MYSQL_BIN || 'C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin';
const PHP = process.env.PHP_BIN || 'C:/laragon/bin/php/php-8.4.25-Win32-vs17-x64/php.exe';
const conn = ['-h', env.DB_HOST, '-P', env.DB_PORT, '-u', env.DB_USERNAME, ...(env.DB_PASSWORD ? [`-p${env.DB_PASSWORD}`] : [])];
const run = (exe, args, input) => execFileSync(join(bin, exe), [...conn, ...args], { input, maxBuffer: 1 << 28, stdio: ['pipe', 'pipe', 'ignore'] });
const dump = (args) => run('mysqldump.exe', ['--skip-comments', ...args]);

run('mysql.exe', ['-e', `DROP DATABASE IF EXISTS \`${DST}\`; CREATE DATABASE \`${DST}\``]);
// migrate:fresh gagal di DB kosong (dua migrasi 'settings'), jadi struktur disalin dari DB utama.
run('mysql.exe', [DST], dump(['--no-data', SRC]));
// Role dan permission ikut disalin: migrasi sudah tercatat 'jalan', jadi role dari migrasi (requester, executor, ...) tidak dibuat ulang.
run('mysql.exe', [DST], dump(['--no-create-info', SRC, 'migrations', 'settings', 'permissions', 'roles', 'role_has_permissions']));

const artisan = (...a) => execFileSync(PHP, ['artisan', ...a, '--force'], { cwd: ROOT, env: { ...process.env, DB_DATABASE: DST, SEED_SUPER_ADMIN_PASSWORD: '' }, stdio: 'inherit' });
// Cache permission dipakai bersama DB utama; id permission di DB capture bisa beda.
execFileSync(PHP, ['artisan', 'permission:cache-reset'], { cwd: ROOT, env: { ...process.env, DB_DATABASE: DST }, stdio: 'inherit' });
artisan('migrate');
artisan('db:seed');
artisan('db:seed', '--class=GuideCaptureSeeder');
console.log(`DB ${DST} siap.`);
