<?php

namespace App\Models;

use App\Access\Assets\AssetSalePartyRule;
use App\Access\HasVisibility;
use App\Access\Rules\EveryoneGrant;
use App\Access\Visibility;
use App\Enums\AssetCondition;
use App\Exceptions\AssetSaleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Penjualan aset antar badan usaha di dalam grup: satu baris per perpindahan
 * kepemilikan, sehingga riwayat aset yang berpindah lebih dari sekali tetap
 * utuh. record() mencatat barisnya sekaligus memindahkan
 * assets.business_entity_id ke pembeli; aset masuk stok pembeli (Tersedia,
 * tanpa pemegang) dan pemilik lama tidak lagi melihatnya sebagai aset aktif.
 *
 * Berbeda dengan kolom assets.sold_* yang berarti aset keluar dari grup
 * (status Dijual, pembeli teks bebas): penjualan internal tidak menyentuh
 * kolom itu dan aset tetap aktif di pemilik barunya.
 *
 * Menghapus baris (Sampah) hanya menghapus riwayat; kepemilikan aset tidak
 * dibalik otomatis, sama seperti BA transfer. Perbaiki lewat form aset.
 */
class AssetSale extends Model
{
    use HasFactory, HasVisibility, SoftDeletes;

    protected $fillable = [
        'asset_id',
        'from_business_entity_id',
        'to_business_entity_id',
        'sold_at',
        'sold_price',
        'document_path',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'sold_at' => 'date',
        'sold_price' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    public function fromBusinessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class, 'from_business_entity_id')->withTrashed();
    }

    public function toBusinessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class, 'to_business_entity_id')->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Catat penjualan dan pindahkan kepemilikan dalam satu transaksi. Aset
     * harus di stok (Tersedia, tanpa pemegang) seperti syarat BA Serah Terima,
     * supaya pemegang di badan usaha lama tidak hilang diam-diam dan status
     * validasi aset tetap cocok dengan BA terakhirnya.
     *
     * $actor adalah akun yang mencatat; bila diberikan, badan usaha penjual
     * dan pembeli harus termasuk aksesnya. Tanpa aktor (proses latar) aturan
     * akses tidak dicek.
     *
     * @param  array{to_business_entity_id: mixed, sold_at: mixed, sold_price?: mixed, document_path?: mixed, notes?: mixed}  $data
     *
     * @throws AssetSaleException
     */
    public static function record(Asset $asset, array $data, ?User $actor = null): self
    {
        return DB::transaction(function () use ($asset, $data, $actor): self {
            // Kunci baris aset: dua penjualan bersamaan tidak boleh sama-sama lolos.
            $locked = Asset::withTrashed()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();

            $buyer = static::ensureCanSell($locked, $data, $actor);

            $sale = static::create([
                'asset_id' => $locked->getKey(),
                'from_business_entity_id' => $locked->business_entity_id,
                'to_business_entity_id' => $buyer->getKey(),
                'sold_at' => $data['sold_at'],
                'sold_price' => max(0, (int) ($data['sold_price'] ?? 0)),
                'document_path' => static::normalizeUploadPath($data['document_path'] ?? null),
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'created_by' => $actor?->getKey(),
            ]);

            // Pemilik baru. recipient_business_entity_id menjadi penanda stok
            // pembeli (tampil "Stok · <badan usaha>"), seperti setelah BA Pengembalian.
            $locked->business_entity_id = $buyer->getKey();
            $locked->recipient_id = null;
            $locked->recipient_business_entity_id = $buyer->getKey();
            $locked->condition_status = AssetCondition::Available;
            $locked->save();

            // Instance pemanggil ikut mencerminkan hasilnya tanpa refresh().
            $asset->setRawAttributes($locked->getAttributes(), true);
            $asset->unsetRelations();

            return $sale;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AssetSaleException
     */
    protected static function ensureCanSell(Asset $asset, array $data, ?User $actor): BusinessEntity
    {
        if ($asset->trashed()) {
            throw new AssetSaleException('Aset di Sampah tidak bisa dijual; pulihkan dulu.');
        }

        if ($asset->business_entity_id === null) {
            throw new AssetSaleException(sprintf('Aset "%s" belum punya badan usaha pemilik; isi dulu lewat form aset.', $asset->name));
        }

        if ($actor !== null && ! $actor->canAccessBusinessEntity($asset->business_entity_id)) {
            throw new AssetSaleException('Badan usaha pemilik aset berada di luar akses badan usaha Anda.');
        }

        $buyerId = $data['to_business_entity_id'] ?? null;

        if (blank($buyerId) || (int) $buyerId === (int) $asset->business_entity_id) {
            throw new AssetSaleException('Badan usaha pembeli harus berbeda dari pemilik aset saat ini.');
        }

        $buyer = BusinessEntity::query()->find($buyerId);

        if (! $buyer) {
            throw new AssetSaleException('Badan usaha pembeli tidak ditemukan atau sudah dihapus.');
        }

        if ($actor !== null && ! $actor->canAccessBusinessEntity($buyer)) {
            throw new AssetSaleException(sprintf('Badan usaha "%s" berada di luar akses badan usaha Anda.', $buyer->name));
        }

        if (blank($data['sold_at'] ?? null)) {
            throw new AssetSaleException('Tanggal penjualan wajib diisi.');
        }

        if ($asset->condition_status === AssetCondition::Transferred || $asset->recipient_id) {
            $asset->loadMissing('recipient');

            throw new AssetSaleException(sprintf(
                'Aset "%s" masih dipegang %s. Buat BA Pengembalian lebih dulu agar aset kembali ke stok.',
                $asset->name,
                $asset->recipient?->name ?? 'pengguna lain',
            ));
        }

        if ($asset->condition_status !== AssetCondition::Available) {
            throw new AssetSaleException(sprintf(
                'Aset "%s" berstatus %s; hanya aset Tersedia (stok) yang bisa dijual ke badan usaha lain.',
                $asset->name,
                $asset->condition_status_label,
            ));
        }

        return $buyer;
    }

    protected static function normalizeUploadPath(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = reset($path) ?: null;
        }

        return filled($path) ? (string) $path : null;
    }

    /**
     * Terlihat bila badan usaha penjual atau pembeli termasuk akses user
     * (AssetSalePartyRule); izin per aksi diperiksa policy lewat Shield.
     */
    public static function visibility(): Visibility
    {
        return Visibility::make()
            ->require(new AssetSalePartyRule)
            ->grant(new EveryoneGrant);
    }
}
