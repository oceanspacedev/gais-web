<?php

namespace App\Imports;

use App\Exceptions\AssetSaleException;
use App\Filament\Resources\AssetResource;
use App\Imports\Concerns\ImportsRenewalRows;
use App\Models\Asset;
use App\Models\AssetSale;
use App\Models\BusinessEntity;
use App\Models\CustomAssetAttribute;
use App\Models\User;
use EightyNine\ExcelImport\Exceptions\ImportStoppedException;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Impor massal penjualan aset antar badan usaha: satu baris = satu aset yang
 * dijual, dicatat lewat AssetSale::record() sehingga aturannya (aset di stok,
 * pembeli beda dari pemilik, akses badan usaha) dan pemindahan kepemilikannya
 * sama persis dengan aksi di panel. Aset dikenali dari `id_aset` atau
 * `serial_number`; badan usaha dicocokkan dengan nama di master (abaikan huruf
 * besar-kecil). Baris boleh berantai (A→B lalu B→C untuk aset yang sama) karena
 * diproses berurutan. Satu kesalahan membatalkan seluruh file: tidak ada baris
 * yang tersimpan sebagian.
 *
 * Memakai helper baris ImportsRenewalRows (nomor baris, tolak, sel kosong).
 */
class AssetSaleImport implements ToCollection, WithHeadingRow
{
    use ImportsRenewalRows;

    /** Jumlah penjualan yang dicatat pada impor terakhir. */
    public int $recorded = 0;

    /**
     * Baris contoh template. `nama_aset` hanya keterangan (tidak dibaca);
     * `badan_usaha_penjual` opsional, bila diisi harus sama dengan pemilik saat ini.
     *
     * @return array<string, mixed>
     */
    public static function sampleRow(): array
    {
        return [
            'id_aset' => 123,
            'serial_number' => '',
            'nama_aset' => 'Laptop Dell Latitude (keterangan, tidak dibaca)',
            'badan_usaha_penjual' => 'PT Penjual',
            'badan_usaha_pembeli' => 'PT Pembeli',
            'tanggal_penjualan' => '03/11/2026',
            'harga_jual' => 7500000,
            'catatan' => 'BAST-001',
        ];
    }

    public function collection(Collection $rows): void
    {
        $actor = Auth::user();

        if (! $actor instanceof User || ! $actor->can('create', AssetSale::class)) {
            throw new ImportStoppedException('Anda tidak punya izin mencatat penjualan aset.');
        }

        $this->recorded = 0;

        DB::transaction(function () use ($rows, $actor): void {
            foreach ($rows as $index => $row) {
                if ($this->isBlankRow($row)) {
                    continue;
                }

                $number = $this->rowNumber($index);
                $asset = $this->asset($row, $number, $actor);
                $this->ensureSellerMatches($row, $asset, $number);
                $buyer = $this->buyer($row['badan_usaha_pembeli'] ?? null, $number);
                $soldAt = $this->dateCell($row, 'tanggal_penjualan', $number, required: true);

                try {
                    AssetSale::record($asset, [
                        'to_business_entity_id' => $buyer->getKey(),
                        'sold_at' => $soldAt,
                        'sold_price' => $this->money($row['harga_jual'] ?? null) ?? 0,
                        'notes' => $this->text($row['catatan'] ?? null),
                    ], $actor);
                } catch (AssetSaleException $exception) {
                    $this->reject($number, $exception->getMessage());
                }

                $this->recorded++;
            }
        });

        if ($this->recorded === 0) {
            throw new ImportStoppedException('Tidak ada baris penjualan yang terisi di file ini.', 'warning');
        }

        Notification::make()
            ->title($this->recorded.' penjualan dicatat')
            ->body('Kepemilikan aset sudah berpindah ke badan usaha pembeli dan masuk stoknya.')
            ->success()
            ->send();
    }

    /**
     * Aset dari id_aset (diutamakan) atau serial_number, dibatasi ke aset yang
     * boleh dilihat pencatat. Bila keduanya diisi, serial number harus cocok.
     *
     * @param  Collection<string, mixed>|array<string, mixed>  $row
     */
    private function asset(Collection|array $row, int $number, User $actor): Asset
    {
        $id = $this->text($row['id_aset'] ?? null);
        $serial = $this->text($row['serial_number'] ?? null);

        if ($id === null && $serial === null) {
            $this->reject($number, 'isi kolom id_aset atau serial_number.');
        }

        if ($id !== null) {
            if (! ctype_digit($id)) {
                $this->reject($number, "id_aset \"{$id}\" bukan angka.");
            }

            $asset = AssetResource::scopeToViewer(Asset::query(), $actor)->find((int) $id)
                ?? $this->reject($number, "aset #{$id} tidak ditemukan atau di luar akses Anda.");

            if ($serial !== null && strcasecmp((string) $asset->serial_number, $serial) !== 0) {
                $this->reject($number, sprintf(
                    'serial_number "%s" tidak cocok dengan aset #%d (%s).',
                    $serial,
                    $asset->id,
                    $asset->serial_number ?? 'tanpa serial number',
                ));
            }

            return $asset;
        }

        $matches = AssetResource::scopeToViewer(Asset::query(), $actor)
            ->whereIdentity(CustomAssetAttribute::KEY_SERIAL_NUMBER, $serial, exact: true)
            ->limit(2)
            ->get();

        return match ($matches->count()) {
            0 => $this->reject($number, "aset dengan serial_number \"{$serial}\" tidak ditemukan atau di luar akses Anda."),
            1 => $matches->first(),
            default => $this->reject($number, "serial_number \"{$serial}\" dimiliki lebih dari satu aset; pakai kolom id_aset."),
        };
    }

    /**
     * Pengaman salah aset: bila badan_usaha_penjual diisi, harus sama dengan
     * pemilik aset saat ini.
     *
     * @param  Collection<string, mixed>|array<string, mixed>  $row
     */
    private function ensureSellerMatches(Collection|array $row, Asset $asset, int $number): void
    {
        $seller = $this->text($row['badan_usaha_penjual'] ?? null);

        if ($seller === null) {
            return;
        }

        $asset->loadMissing('businessEntity');
        $owner = $asset->businessEntity?->name;

        if ($owner === null || strcasecmp($seller, $owner) !== 0) {
            $this->reject($number, sprintf(
                'badan_usaha_penjual "%s" tidak sama dengan pemilik aset #%d saat ini (%s).',
                $seller,
                $asset->id,
                $owner ?? 'belum ada badan usaha',
            ));
        }
    }

    /**
     * Pembeli dicocokkan dengan master Badan Usaha yang ada (abaikan huruf
     * besar-kecil); nama yang tidak dikenal menolak baris, bukan membuat master baru.
     */
    private function buyer(mixed $cell, int $number): BusinessEntity
    {
        $name = $this->text($cell) ?? $this->reject($number, 'kolom badan_usaha_pembeli wajib diisi.');

        return BusinessEntity::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? $this->reject($number, "badan_usaha_pembeli \"{$name}\" tidak ada di master Badan Usaha; tambahkan dulu lewat menu Badan Usaha.");
    }
}
