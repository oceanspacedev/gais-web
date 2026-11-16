<?php

namespace App\Models;

use App\Access\HasVisibility;
use App\Access\Rules\EveryoneGrant;
use App\Access\Visibility;
use App\Enums\SecurityAnswerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pertanyaan Security Checksheet yang diatur admin: bagian, judul, tipe
 * jawaban, opsi, dan kunci jawaban. Laporan menyimpan salinan pertanyaannya
 * (lihat SecurityChecklist), jadi mengubah pertanyaan tidak mengubah laporan lama.
 */
class SecurityChecklistItem extends Model
{
    use HasVisibility, SoftDeletes;

    protected $fillable = [
        'section',
        'question',
        'answer_type',
        'options',
        'answer_key',
        'sort',
        'is_active',
    ];

    protected $casts = [
        'answer_type' => SecurityAnswerType::class,
        'options' => 'array',
        'answer_key' => 'json',
        'sort' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (SecurityChecklistItem $item): void {
            if (blank($item->sort)) {
                $item->sort = ((int) static::withTrashed()->max('sort')) + 1;
            }
        });

        // Opsi dan kunci yang tidak dipakai tipe ini tidak disimpan.
        static::saving(function (SecurityChecklistItem $item): void {
            $type = $item->answer_type instanceof SecurityAnswerType ? $item->answer_type : SecurityAnswerType::tryFrom((string) $item->answer_type);

            if (! $type?->hasOptions()) {
                $item->options = null;
            }

            if (! $type?->hasAnswerKey() || blank($item->answer_key)) {
                $item->answer_key = null;
            }
        });
    }

    /**
     * @param  Builder<SecurityChecklistItem>  $query
     * @return Builder<SecurityChecklistItem>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<SecurityChecklistItem>  $query
     * @return Builder<SecurityChecklistItem>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('id');
    }

    /**
     * Pertanyaan apa adanya untuk disalin ke laporan.
     *
     * @return array{item_id: int, section: string, question: string, type: string, options: list<string>|null, answer_key: mixed}
     */
    public function definition(): array
    {
        return [
            'item_id' => $this->id,
            'section' => $this->section,
            'question' => $this->question,
            'type' => $this->answer_type->value,
            'options' => $this->answer_type->hasOptions() ? array_values($this->options ?? []) : null,
            'answer_key' => $this->answer_key,
        ];
    }

    public function answerKeyLabel(): ?string
    {
        return self::formatAnswer($this->answer_key);
    }

    public static function formatAnswer(mixed $answer): ?string
    {
        if (is_array($answer)) {
            $answer = implode(', ', array_filter($answer, fn (mixed $value): bool => filled($value)));
        }

        return filled($answer) ? (string) $answer : null;
    }

    /**
     * Semua record terlihat; pembatasan hanya lewat izin Shield di policy.
     */
    public static function visibility(): Visibility
    {
        return Visibility::make()->grant(new EveryoneGrant);
    }
}
