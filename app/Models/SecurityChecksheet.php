<?php

namespace App\Models;

use App\Access\HasVisibility;
use App\Access\Rules\OwnerGrant;
use App\Access\Rules\PermissionGrant;
use App\Access\Visibility;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SecurityChecksheet extends Model
{
    use HasFactory, HasVisibility, SoftDeletes;

    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'm4v', 'webm', '3gp', 'mkv', 'avi'];

    protected $fillable = [
        'reference_number',
        'user_id',
        'location',
        'report_date',
        'checked_at',
        'checklist',
        'mismatch_count',
        'findings',
        'attachments',
    ];

    protected $casts = [
        'report_date' => 'date',
        'checked_at' => 'datetime',
        'checklist' => 'array',
        'mismatch_count' => 'integer',
        'attachments' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasFindings(): bool
    {
        return filled($this->findings);
    }

    public function hasMismatch(): bool
    {
        return $this->mismatch_count > 0;
    }

    /**
     * Snapshot checklist: satu entri per pertanyaan berisi salinan pertanyaan,
     * jawaban, dan hasil pencocokan dengan kunci (lihat SecurityChecklist).
     *
     * @return list<array<string, mixed>>
     */
    public function checklistEntries(): array
    {
        $entries = is_array($this->checklist) ? $this->checklist : [];

        return array_is_list($entries) ? array_values(array_filter($entries, 'is_array')) : [];
    }

    /**
     * Checklist siap tampil per bagian, sesuai urutan saat laporan dibuat.
     *
     * @return list<array{title: string, items: list<array{label: string, value: string, matches: bool|null, answer_key: string|null}>}>
     */
    public function checklistSections(): array
    {
        $sections = [];

        foreach ($this->checklistEntries() as $entry) {
            $title = (string) ($entry['section'] ?? '-');
            $sections[$title] ??= ['title' => $title, 'items' => []];
            $sections[$title]['items'][] = [
                'label' => (string) ($entry['question'] ?? '-'),
                'value' => SecurityChecklistItem::formatAnswer($entry['answer'] ?? null) ?? '',
                'matches' => $entry['matches'] ?? null,
                'answer_key' => SecurityChecklistItem::formatAnswer($entry['answer_key'] ?? null),
            ];
        }

        return array_values($sections);
    }

    /**
     * Jawaban untuk satu pertanyaan master, mis. untuk kolom export.
     */
    public function answerFor(int $itemId): ?string
    {
        foreach ($this->checklistEntries() as $entry) {
            if ((int) ($entry['item_id'] ?? 0) === $itemId) {
                return SecurityChecklistItem::formatAnswer($entry['answer'] ?? null);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function mismatchedQuestions(): array
    {
        return array_values(array_map(
            fn (array $entry): string => (string) ($entry['question'] ?? '-'),
            array_filter($this->checklistEntries(), fn (array $entry): bool => ($entry['matches'] ?? null) === false),
        ));
    }

    /**
     * @return list<string>
     */
    public function attachmentPaths(): array
    {
        return array_values(array_filter(
            is_array($this->attachments) ? $this->attachments : [],
            fn (mixed $path): bool => is_string($path) && $path !== '',
        ));
    }

    public static function isVideo(string $path): bool
    {
        return in_array(Str::lower(pathinfo($path, PATHINFO_EXTENSION)), self::VIDEO_EXTENSIONS, true);
    }

    protected static function booted(): void
    {
        static::creating(function (SecurityChecksheet $checksheet) {
            if (blank($checksheet->reference_number)) {
                $checksheet->reference_number = static::generateReferenceNumber();
            }

            if (blank($checksheet->user_id) && auth()->check()) {
                $checksheet->user_id = auth()->id();
            }

            if (blank($checksheet->checked_at)) {
                $checksheet->checked_at = now();
            }

            if (blank($checksheet->report_date)) {
                $checksheet->report_date = $checksheet->checked_at->toDateString();
            }
        });
    }

    public static function generateReferenceNumber(): string
    {
        $prefix = 'SEC-'.Carbon::now()->format('Ymd').'-';

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
     * Petugas melihat laporannya sendiri; pemegang "Lihat Data Semua User"
     * melihat semua laporan.
     */
    public static function visibility(): Visibility
    {
        return Visibility::make()->grant(
            new PermissionGrant('view_all_security::checksheet'),
            new OwnerGrant,
        );
    }
}
