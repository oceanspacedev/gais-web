<?php

namespace App\Models;

use App\Enums\ProblemReportStatus;
use App\Support\ProblemReportVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Laporan gangguan (sumo: problem_report). Executor mengubah status dan mengisi
 * hasil; pelapor mengonfirmasi (client_status 1), atau otomatis 24 jam setelah ditutup.
 */
class ProblemReport extends Model
{
    use HasFactory, SoftDeletes;

    public const CLIENT_WAITING = 0;

    public const CLIENT_CONFIRMED = 1;

    protected $fillable = [
        'code',
        'user_id',
        'problem_report_category_id',
        'business_entity_id',
        'reported_at',
        'title',
        'description',
        'photo_before',
        'photo_after',
        'status',
        'scheduled_at',
        'client_status',
        'review_user',
        'closed_by_user_id',
        'closed_at',
        'result_desc',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'scheduled_at' => 'date',
        'closed_at' => 'datetime',
        'status' => ProblemReportStatus::class,
        'client_status' => 'integer',
    ];

    protected $attributes = [
        'status' => 'PENDING',
        'client_status' => 0,
    ];

    protected static function booted(): void
    {
        static::created(function (self $report): void {
            if (blank($report->code)) {
                $report->forceFill(['code' => config('item-request.problem_report_code_prefix', 'PR').$report->id])->saveQuietly();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProblemReportCategory::class, 'problem_report_category_id')->withTrashed();
    }

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return ProblemReportVisibility::apply($query, $user);
    }

    /**
     * Belum dikonfirmasi pelapor; membatasi satu laporan terbuka per pelapor.
     */
    public function scopeAwaitingClient(Builder $query): Builder
    {
        return $query->where('client_status', self::CLIENT_WAITING);
    }

    public function isConfirmed(): bool
    {
        return (int) $this->client_status === self::CLIENT_CONFIRMED;
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && (int) $this->user_id === (int) $user->id;
    }

    public function isAccessibleBy(User $user): bool
    {
        return $this->business_entity_id === null || $user->canAccessBusinessEntity($this->business_entity_id);
    }
}
