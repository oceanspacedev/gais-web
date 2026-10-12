<?php

namespace App\Models;

use App\Enums\ItemRequestApprovalType;
use App\Enums\ItemRequestClientStatus;
use App\Support\ItemRequestVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pengajuan barang (sumo: requests). Alur: pemohon membuat, Accounting mengisi
 * status PO (tipe tertentu), Manager menyetujui jumlah, Executor memproses dan
 * menyelesaikan, lalu pemohon mengonfirmasi penerimaan.
 */
class ItemRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'request_code',
        'user_id',
        'item_request_type_id',
        'business_entity_id',
        'requested_at',
        'total_cost',
        'po_status',
        'po_number',
        'client_status',
        'closed_by_user_id',
        'closed_at',
        'request_file',
        'request_file_2',
        'approved_file',
        'notes',
        'user_notes',
        'audit_notes',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'closed_at' => 'datetime',
        'total_cost' => 'decimal:2',
        'po_status' => 'boolean',
        'client_status' => ItemRequestClientStatus::class,
    ];

    protected $attributes = [
        'client_status' => 0,
        'po_status' => false,
        'total_cost' => 0,
    ];

    protected static function booted(): void
    {
        static::created(function (self $request): void {
            if (blank($request->request_code)) {
                $request->forceFill(['request_code' => config('item-request.code_prefix', 'REQ').$request->id])->saveQuietly();
            }

            $request->ensureApprovalRows();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ItemRequestType::class, 'item_request_type_id')->withTrashed();
    }

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ItemRequestDetail::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ItemRequestApproval::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ItemRequestLog::class)->latest();
    }

    /**
     * Pengajuan yang boleh dilihat user (lihat ItemRequestVisibility).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return ItemRequestVisibility::apply($query, $user);
    }

    /**
     * Masih menghitung kuota pengajuan terbuka per tipe.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('client_status', ItemRequestClientStatus::openValues());
    }

    /**
     * Semua tahap approval selalu punya barisnya, diisi saat tahap itu lewat.
     */
    public function ensureApprovalRows(): void
    {
        foreach (ItemRequestApprovalType::cases() as $type) {
            $this->approvals()->firstOrCreate(['approval_type' => $type->value]);
        }
    }

    public function approval(ItemRequestApprovalType $type): ?ItemRequestApproval
    {
        return $this->approvals->firstWhere('approval_type', $type);
    }

    public function isApproved(ItemRequestApprovalType $type): bool
    {
        return $this->approval($type)?->isApproved() ?? false;
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->user_id !== null && (int) $this->user_id === (int) $user->id;
    }

    /**
     * Total memakai jumlah disetujui bila sudah diisi, kalau belum jumlah diminta (sumo: show).
     */
    public function grandTotal(): float
    {
        $this->loadMissing('details.product');

        return (float) $this->details->sum(fn (ItemRequestDetail $detail): float => $detail->lineTotal());
    }

    public function isAccessibleBy(User $user): bool
    {
        return $this->business_entity_id === null || $user->canAccessBusinessEntity($this->business_entity_id);
    }
}
