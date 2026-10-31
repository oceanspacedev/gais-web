<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cakupan jenis gangguan yang dilihat pemegang "Lihat Data Semua User" di
 * sebuah divisi: jenis yang disebut (excluded = false) atau semua jenis kecuali
 * yang disebut (excluded = true). Divisi tanpa baris melihat semua jenis.
 */
class ProblemReportScope extends Model
{
    use HasFactory;

    protected $fillable = ['division_id', 'problem_report_category_id', 'excluded'];

    protected $casts = [
        'excluded' => 'boolean',
    ];

    protected $attributes = [
        'excluded' => false,
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class)->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProblemReportCategory::class, 'problem_report_category_id')->withTrashed();
    }

    /**
     * Cakupan sebuah divisi, dimemo selama satu request/job: dibaca untuk badge
     * navigasi, tabel, widget, dan setiap baris.
     *
     * @return Collection<int, self>
     */
    public static function forDivision(int $divisionId): Collection
    {
        return once(fn (): Collection => self::query()->where('division_id', $divisionId)->get());
    }
}
