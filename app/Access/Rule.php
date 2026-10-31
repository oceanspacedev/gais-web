<?php

namespace App\Access;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu aturan "siapa melihat apa" dengan dua wajah yang harus selalu sepakat:
 * apply() membatasi query daftar, allows() memutuskan satu record dari atribut
 * dan relasi yang sudah dimuat. Keduanya dirangkai oleh Visibility; test kontrak
 * (tests/Support/AssertsVisibilityContract) membandingkan hasil keduanya.
 *
 * Sebagai pembatas (Visibility::require) aturan dipakai dengan AND; sebagai
 * pemberi akses (Grant, Visibility::grant) dengan OR.
 */
interface Rule
{
    /**
     * Apakah aturan ini berlaku untuk $user. Harus murah (tanpa query berat):
     * dipanggil untuk badge navigasi, tabel, widget, dan setiap baris.
     */
    public function appliesTo(User $user): bool;

    /**
     * Batasi $query ke record yang dikenai aturan. Pakai $query->qualifyColumn()
     * supaya aman dipakai bersama join. Untuk Grant, $query adalah grup OR
     * tersendiri dan wajib menambah minimal satu klausa.
     */
    public function apply(Builder $query, User $user): void;

    /**
     * Putuskan satu record; null bila tidak bisa diputuskan dari data yang sudah
     * dimuat (relasi belum di-load), sehingga Visibility jatuh ke query.
     */
    public function allows(User $user, Model $record): ?bool;
}
