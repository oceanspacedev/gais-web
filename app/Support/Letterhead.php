<?php

namespace App\Support;

use App\Models\BusinessEntity;
use Throwable;

/**
 * Isi kop surat sebuah badan usaha, siap dirender oleh
 * resources/views/pdf/partials/letterhead.blade.php.
 *
 * Urutan sumber: kop gambar (override) → PT induk (untuk brand/outlet) →
 * data kop badan usaha itu sendiri. Field yang kosong tidak dicetak; gambar
 * yang gagal dibaca dilewati sehingga PDF tetap bisa dibuat dengan kop teks.
 *
 * Kop sengaja ringkas (nama, alamat maks. 2 baris, satu baris kontak) supaya
 * tingginya setara logo. NPWP/NIB/SK disimpan sebagai data, tidak dicetak di kop.
 */
final class Letterhead
{
    public function __construct(
        public readonly ?string $image = null,
        public readonly ?string $logo = null,
        public readonly string $name = '',
        public readonly ?string $address = null,
        public readonly ?string $contact = null,
        public readonly ?string $outlet = null,
    ) {}

    public static function for(?BusinessEntity $entity): self
    {
        if ($entity === null) {
            return new self;
        }

        if ($image = self::image($entity->letterhead)) {
            return new self(image: $image);
        }

        $parent = $entity->legal_entity_id ? $entity->legalEntity : null;
        if ($parent !== null && $parent->isNot($entity)) {
            return self::fromOwnData($parent)->withOutlet($entity->name);
        }

        return self::fromOwnData($entity);
    }

    public function isImage(): bool
    {
        return $this->image !== null;
    }

    /** Data kop milik $entity sendiri (PT induknya tidak diikuti lagi). */
    private static function fromOwnData(BusinessEntity $entity): self
    {
        if ($image = self::image($entity->letterhead)) {
            return new self(image: $image);
        }

        $contact = array_filter([
            filled($entity->phone) ? 'Telp. '.trim($entity->phone) : null,
            filled($entity->email) ? trim($entity->email) : null,
        ]);

        return new self(
            logo: self::image($entity->logo),
            name: trim((string) (filled($entity->legal_name) ? $entity->legal_name : $entity->name)),
            address: filled($entity->address) ? trim($entity->address) : null,
            contact: $contact === [] ? null : implode(' | ', $contact),
        );
    }

    private function withOutlet(string $outlet): self
    {
        return new self($this->image, $this->logo, $this->name, $this->address, $this->contact, $outlet);
    }

    private static function image(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        try {
            return StoredFile::imageDataUri('public', $path);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
