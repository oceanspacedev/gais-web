<?php

namespace App\Support;

use App\Enums\LetterheadLayout;
use App\Models\BusinessEntity;
use Illuminate\Support\Arr;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
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
        public readonly LetterheadLayout $layout = LetterheadLayout::Right,
    ) {}

    /** Perataan teks di kop; tanpa logo selalu di tengah. */
    public function textAlign(): string
    {
        return $this->logo === null ? 'center' : $this->layout->value;
    }

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
            layout: self::layout($entity->letterhead_layout),
        );
    }

    /**
     * Kop dari isian form Badan Usaha yang belum disimpan, untuk pratinjau langsung.
     * Gambar boleh berupa path tersimpan atau upload sementara Livewire.
     *
     * @param  array<string, mixed>  $state
     */
    public static function fromFormState(array $state): self
    {
        if ($image = self::uploadedImage($state['letterhead'] ?? null)) {
            return new self(image: $image);
        }

        $parentId = $state['legal_entity_id'] ?? null;
        if (filled($parentId) && ($parent = BusinessEntity::find($parentId)) !== null) {
            return self::fromOwnData($parent)->withOutlet(trim((string) ($state['name'] ?? '')));
        }

        $own = self::fromOwnData(new BusinessEntity(Arr::only($state, ['name', 'legal_name', 'address', 'phone', 'email'])));

        return new self(
            logo: self::uploadedImage($state['logo'] ?? null),
            name: $own->name,
            address: $own->address,
            contact: $own->contact,
            layout: self::layout($state['letterhead_layout'] ?? null),
        );
    }

    private static function uploadedImage(mixed $state): ?string
    {
        $file = is_array($state) ? Arr::first($state) : $state;

        if ($file instanceof TemporaryUploadedFile) {
            try {
                return $file->isPreviewable() ? $file->temporaryUrl() : null;
            } catch (Throwable) {
                return null;
            }
        }

        return is_string($file) ? self::image($file) : null;
    }

    private function withOutlet(string $outlet): self
    {
        return new self($this->image, $this->logo, $this->name, $this->address, $this->contact, $outlet, $this->layout);
    }

    private static function layout(mixed $value): LetterheadLayout
    {
        if ($value instanceof LetterheadLayout) {
            return $value;
        }

        return LetterheadLayout::tryFrom((string) $value) ?? LetterheadLayout::Right;
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
