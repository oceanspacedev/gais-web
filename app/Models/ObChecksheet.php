<?php

namespace App\Models;

use App\Access\HasVisibility;
use App\Access\ObChecksheets\UnclaimedPoolGrant;
use App\Access\Rules\OwnerGrant;
use App\Access\Rules\PermissionGrant;
use App\Access\Visibility;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ObChecksheet extends Model
{
    use HasFactory, HasVisibility, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const SOURCE_ASSIGNED = 'assigned';

    public const SOURCE_INITIATIVE = 'initiative';

    protected $fillable = [
        'reference_number',
        'user_id',
        'assigned_by',
        'source',
        'scheduled_date',
        'shift_label',
        'ob_shift_id',
        'room',
        'ob_room_id',
        'status',
        'duration_minutes',
        'cleaned_at',
        'started_at',
        'finished_at',
        'before_photo',
        'after_photo',
        'notes',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'cleaned_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function obRoom(): BelongsTo
    {
        return $this->belongsTo(ObRoom::class)->withTrashed();
    }

    public function obShift(): BelongsTo
    {
        return $this->belongsTo(ObShift::class)->withTrashed();
    }

    /**
     * Tugas kolam bersama: ditugaskan atasan tanpa petugas, belum diambil.
     *
     * @param  Builder<ObChecksheet>  $query
     * @return Builder<ObChecksheet>
     */
    public function scopeUnclaimed(Builder $query): Builder
    {
        return $query->whereNull('user_id')->where('source', self::SOURCE_ASSIGNED);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Belum Dikerjakan',
            self::STATUS_IN_PROGRESS => 'Sedang Dikerjakan',
            self::STATUS_COMPLETED => 'Selesai',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sourceOptions(): array
    {
        return [
            self::SOURCE_ASSIGNED => 'Ditugaskan Atasan',
            self::SOURCE_INITIATIVE => 'Inisiatif OB',
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return self::statusOptions()[$status] ?? ($status ?? '-');
    }

    public static function sourceLabel(?string $source): string
    {
        return self::sourceOptions()[$source] ?? ($source ?? '-');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAssigned(): bool
    {
        return $this->source === self::SOURCE_ASSIGNED;
    }

    /**
     * Tugas kolam bersama yang belum punya petugas.
     */
    public function isUnclaimed(): bool
    {
        return blank($this->user_id);
    }

    /**
     * OB yang lebih dulu mengirim foto sebelum menjadi petugasnya. Update bersyarat
     * `user_id is null` menjamin hanya satu OB yang berhasil bila dua OB mengambil bersamaan.
     */
    public function claim(User $user): bool
    {
        if (filled($this->user_id)) {
            return $this->user_id === $user->id;
        }

        $claimed = static::query()
            ->whereKey($this->getKey())
            ->whereNull('user_id')
            ->where('status', self::STATUS_PENDING)
            ->update(['user_id' => $user->id]);

        if ($claimed === 0) {
            $this->refresh();

            return $this->user_id === $user->id;
        }

        $this->user_id = $user->id;
        $this->syncOriginalAttribute('user_id');
        $this->unsetRelation('user');

        return true;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED || filled($this->after_photo);
    }

    /**
     * Foto sebelum/sesudah tugas atasan sering diambil sekaligus, jadi selisih
     * waktunya bukan durasi kerja per ruangan; angkanya hanya tampil untuk inisiatif OB.
     */
    public function showsDuration(): bool
    {
        return ! $this->isAssigned();
    }

    /**
     * Tugas pending dimulai saat OB mengirim foto sebelum.
     */
    public function startWork(string $beforePhoto, ?string $notes = null): void
    {
        $this->before_photo = $beforePhoto;
        $this->started_at = now();
        $this->status = self::STATUS_IN_PROGRESS;

        if ($notes !== null) {
            $this->notes = $notes;
        }

        $this->save();
    }

    public function markAsCompleted(string $afterPhoto, ?string $notes = null): void
    {
        $finishTime = now();
        $this->after_photo = $afterPhoto;
        $this->finished_at = $finishTime;
        $this->status = self::STATUS_COMPLETED;

        if ($notes !== null) {
            $this->notes = $notes;
        }

        if ($this->started_at) {
            $this->duration_minutes = max(0, $this->started_at->diffInMinutes($finishTime));
        }

        $this->save();
    }

    protected static function booted(): void
    {
        static::creating(function (ObChecksheet $checksheet) {
            if (blank($checksheet->reference_number)) {
                $checksheet->reference_number = static::generateReferenceNumber();
            }

            // Tugas atasan tanpa petugas sengaja dibiarkan kosong (kolam bersama).
            if (blank($checksheet->user_id) && $checksheet->source !== self::SOURCE_ASSIGNED && auth()->check()) {
                $checksheet->user_id = auth()->id();
            }

            if (blank($checksheet->source)) {
                $checksheet->source = self::SOURCE_INITIATIVE;
            }

            if (blank($checksheet->status)) {
                $checksheet->status = match (true) {
                    $checksheet->source === self::SOURCE_ASSIGNED && blank($checksheet->before_photo) => self::STATUS_PENDING,
                    filled($checksheet->after_photo) => self::STATUS_COMPLETED,
                    default => self::STATUS_IN_PROGRESS,
                };
            }

            // Tugas pending baru punya waktu mulai saat OB mengirim foto sebelum.
            if (blank($checksheet->started_at) && $checksheet->status !== self::STATUS_PENDING) {
                $checksheet->started_at = now();
            }

            if (blank($checksheet->scheduled_date)) {
                $checksheet->scheduled_date = ($checksheet->started_at ?? now())->toDateString();
            }

            if (blank($checksheet->cleaned_at)) {
                $checksheet->cleaned_at = $checksheet->started_at ?? now();
            }

            if ($checksheet->status === self::STATUS_COMPLETED && blank($checksheet->finished_at)) {
                $checksheet->finished_at = now();
            }

            if ($checksheet->started_at && $checksheet->finished_at && is_null($checksheet->duration_minutes)) {
                $checksheet->duration_minutes = max(0, $checksheet->started_at->diffInMinutes($checksheet->finished_at));
            }
        });

        static::saving(function (ObChecksheet $checksheet) {
            $checksheet->syncMasterData();

            if ($checksheet->finished_at && $checksheet->started_at) {
                $checksheet->duration_minutes = max(0, $checksheet->started_at->diffInMinutes($checksheet->finished_at));
            }

            if (filled($checksheet->after_photo) && $checksheet->status !== self::STATUS_COMPLETED) {
                $checksheet->status = self::STATUS_COMPLETED;
                if (blank($checksheet->finished_at)) {
                    $checksheet->finished_at = now();
                }
            }
        });
    }

    /**
     * Nama ruangan/shift disimpan sebagai salinan teks di samping id masternya.
     * Memilih master mengisi teksnya; mengetik nama ruangan dicocokkan ke master
     * (nama di luar master tetap boleh, id-nya kosong).
     */
    protected function syncMasterData(): void
    {
        $shift = filled($this->ob_shift_id) ? ObShift::withTrashed()->find($this->ob_shift_id) : null;

        if ($this->isDirty('ob_shift_id') && (! $this->isDirty('shift_label') || blank($this->shift_label))) {
            $this->shift_label = $shift?->name;
        }

        if ($this->isDirty('room')) {
            $this->room = filled($this->room) ? ObRoom::normalizeName($this->room) : $this->room;
        }

        if ($this->isDirty('ob_room_id') && filled($this->ob_room_id)) {
            if (! $this->isDirty('room') || blank($this->room)) {
                $this->room = ObRoom::withTrashed()->find($this->ob_room_id)?->name ?? $this->room;
            }

            return;
        }

        if ($this->isDirty('room')) {
            $this->ob_room_id = ObRoom::findByName($this->room, $shift?->asset_location_id)?->id;
        }
    }

    public static function generateReferenceNumber(): string
    {
        $today = Carbon::now()->format('Ymd');
        $prefix = "OBC-{$today}-";

        $lastRecord = static::withTrashed()
            ->where('reference_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        $sequence = 1;
        if ($lastRecord && preg_match('/-(\d+)$/', $lastRecord->reference_number, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Petugas melihat tugasnya sendiri dan tugas kolam bersama yang belum
     * diambil; pemegang "Lihat Data Semua User" melihat semua checksheet.
     */
    public static function visibility(): Visibility
    {
        return Visibility::make()->grant(
            new PermissionGrant('view_all_ob::checksheet'),
            new OwnerGrant,
            new UnclaimedPoolGrant,
        );
    }
}
