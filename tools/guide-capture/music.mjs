// Musik latar instrumental orisinal (dibuat dari nol, bebas hak cipta) untuk video demo.
// Pemakaian: import { makeMusic } from './music.mjs'; makeMusic('out.wav', detik)
// atau CLI: node music.mjs out.wav 60
import { writeFileSync } from 'node:fs';

const SR = 44100;
const BPM = 104;
const BEAT = 60 / BPM;
const midi = (n) => 440 * 2 ** ((n - 69) / 12);

// Progresi C – G – Am – F, tiap akor 2 birama (8 ketukan).
const CHORDS = [
  [48, 60, 64, 67], // C
  [43, 59, 62, 67], // G
  [45, 60, 64, 69], // Am
  [41, 60, 65, 69], // F
];
// Melodi pendek (nada MIDI per 1/8 ketukan, null = diam), diulang tiap 4 akor.
const MELODY = [
  72, null, 76, null, 79, null, 76, 74, 72, null, null, null, 74, null, 76, null,
  74, null, 71, null, 74, null, 79, null, 77, null, 76, null, 74, null, null, null,
  76, null, 72, null, 76, null, 81, null, 79, null, 76, null, 72, null, null, null,
  77, null, 76, null, 74, null, 72, null, 69, null, 72, null, 72, null, null, null,
];

export function makeMusic(file, seconds) {
  const n = Math.ceil(seconds * SR);
  const L = new Float32Array(n);
  const R = new Float32Array(n);
  const add = (i, l, r = l) => { if (i >= 0 && i < n) { L[i] += l; R[i] += r; } };

  // Not dengan selubung ADSR sederhana + sedikit harmonik.
  const note = (t, dur, f, vol, { attack = 0.01, release = 0.25, harm = [1, 0.3, 0.12], pan = 0 } = {}) => {
    const s = Math.floor(t * SR);
    const len = Math.floor((dur + release) * SR);
    for (let k = 0; k < len; k++) {
      const tt = k / SR;
      let env = tt < attack ? tt / attack : tt < dur ? 1 : Math.max(0, 1 - (tt - dur) / release);
      env *= Math.exp(-tt * 1.6);
      let v = 0;
      harm.forEach((a, h) => { v += a * Math.sin(2 * Math.PI * f * (h + 1) * tt); });
      v *= env * vol;
      add(s + k, v * (1 - pan) , v * (1 + pan));
    }
  };
  const pad = (t, dur, f, vol) => {
    const s = Math.floor(t * SR);
    const len = Math.floor(dur * SR);
    for (let k = 0; k < len; k++) {
      const tt = k / SR;
      const env = Math.min(1, tt / 0.6, (dur - tt) / 0.6);
      const v = vol * env * (Math.sin(2 * Math.PI * f * tt) + 0.5 * Math.sin(2 * Math.PI * f * 1.004 * tt));
      add(s + k, v * 0.9, v * 1.1);
    }
  };
  let seed = 7;
  const rnd = () => ((seed = (seed * 16807) % 2147483647) / 2147483647) * 2 - 1;
  const kick = (t) => {
    const s = Math.floor(t * SR);
    for (let k = 0; k < 0.25 * SR; k++) {
      const tt = k / SR;
      add(s + k, 0.32 * Math.exp(-tt * 18) * Math.sin(2 * Math.PI * (45 + 90 * Math.exp(-tt * 30)) * tt));
    }
  };
  const hat = (t, vol) => {
    const s = Math.floor(t * SR);
    let prev = 0;
    for (let k = 0; k < 0.05 * SR; k++) {
      const x = rnd();
      const hp = x - prev; prev = x; // high-pass kasar
      add(s + k, vol * hp * Math.exp(-(k / SR) * 90) * 0.7, vol * hp * Math.exp(-(k / SR) * 90));
    }
  };

  const chordLen = 8 * BEAT;
  for (let c = 0, t = 0; t < seconds; c++, t += chordLen) {
    const ch = CHORDS[c % 4];
    const intro = t < chordLen * 2; // 2 akor pertama: pad + arpeggio saja
    ch.slice(1).forEach((m) => pad(t, chordLen, midi(m), 0.035));
    // Bass: akar akor, pola ketukan 1 dan 3½.
    if (!intro) for (const b of [0, 2.5, 4, 6.5]) note(t + b * BEAT, BEAT * 1.2, midi(ch[0]), 0.11, { harm: [1, 0.15], release: 0.1 });
    // Arpeggio 1/8.
    for (let i = 0; i < 16; i++) {
      const m = ch[1 + (i % 3)] + (i % 6 >= 3 ? 12 : 0);
      note(t + i * BEAT / 2, BEAT / 2, midi(m), 0.035, { harm: [1, 0.4, 0.2, 0.1], release: 0.3, pan: i % 2 ? 0.35 : -0.35 });
    }
    if (!intro) {
      for (let b = 0; b < 8; b++) { if (b % 2 === 0) kick(t + b * BEAT); hat(t + b * BEAT + BEAT / 2, 0.08); }
      // Melodi muncul setelah intro, bergantian 8 akor ada / 4 akor istirahat supaya tidak membosankan.
      if (Math.floor(c / 4) % 3 !== 2) {
        for (let i = 0; i < 16; i++) {
          const m = MELODY[(c % 4) * 16 + i];
          if (m) note(t + i * BEAT / 2, BEAT * 0.9, midi(m), 0.06, { harm: [1, 0.5, 0.25, 0.12, 0.06], release: 0.35, attack: 0.005 });
        }
      }
    }
  }

  // Gema sederhana (delay stereo) + normalisasi + fade in/out.
  const d = Math.floor(BEAT * 0.75 * SR);
  for (let i = d; i < n; i++) { L[i] += R[i - d] * 0.22; R[i] += L[i - d] * 0.22; }
  let peak = 0;
  for (let i = 0; i < n; i++) peak = Math.max(peak, Math.abs(L[i]), Math.abs(R[i]));
  const g = 0.85 / (peak || 1);
  const fadeIn = 2 * SR; const fadeOut = 4 * SR;
  const buf = Buffer.alloc(44 + n * 4);
  buf.write('RIFF', 0); buf.writeUInt32LE(36 + n * 4, 4); buf.write('WAVE', 8); buf.write('fmt ', 12);
  buf.writeUInt32LE(16, 16); buf.writeUInt16LE(1, 20); buf.writeUInt16LE(2, 22); buf.writeUInt32LE(SR, 24);
  buf.writeUInt32LE(SR * 4, 28); buf.writeUInt16LE(4, 32); buf.writeUInt16LE(16, 34); buf.write('data', 36); buf.writeUInt32LE(n * 4, 40);
  for (let i = 0; i < n; i++) {
    const f = Math.min(1, i / fadeIn, (n - i) / fadeOut);
    buf.writeInt16LE(Math.round(Math.max(-1, Math.min(1, L[i] * g * f)) * 32767), 44 + i * 4);
    buf.writeInt16LE(Math.round(Math.max(-1, Math.min(1, R[i] * g * f)) * 32767), 46 + i * 4);
  }
  writeFileSync(file, buf);
}

if (process.argv[1] && import.meta.filename === process.argv[1]) {
  makeMusic(process.argv[2] || 'music.wav', Number(process.argv[3] || 60));
}
