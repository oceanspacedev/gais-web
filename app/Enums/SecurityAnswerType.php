<?php

namespace App\Enums;

/**
 * Tipe jawaban pertanyaan Security Checksheet, dipilih admin per pertanyaan.
 */
enum SecurityAnswerType: string
{
    case Choice = 'choice';
    case YesNo = 'yes_no';
    case Checkboxes = 'checkboxes';
    case Text = 'text';

    public const YES = 'Ya';

    public const NO = 'Tidak';

    public function label(): string
    {
        return match ($this) {
            self::Choice => 'Pilih satu',
            self::YesNo => 'Ya / Tidak',
            self::Checkboxes => 'Pilih beberapa',
            self::Text => 'Isian teks',
        };
    }

    /** Penjelasan singkat di bawah tombol tipe, ditulis dari sisi petugas. */
    public function description(): string
    {
        return match ($this) {
            self::Choice => 'Security memilih satu jawaban, mis. Aman / Rusak.',
            self::YesNo => 'Security cukup menjawab Ya atau Tidak.',
            self::Checkboxes => 'Security boleh mencentang lebih dari satu.',
            self::Text => 'Security mengetik sendiri. Tidak dinilai.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Choice => 'heroicon-o-stop-circle',
            self::YesNo => 'heroicon-o-hand-thumb-up',
            self::Checkboxes => 'heroicon-o-check-badge',
            self::Text => 'heroicon-o-pencil',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Choice => 'primary',
            self::YesNo => 'info',
            self::Checkboxes => 'warning',
            self::Text => 'gray',
        };
    }

    /** Admin mengisi daftar opsi sendiri. */
    public function hasOptions(): bool
    {
        return in_array($this, [self::Choice, self::Checkboxes], true);
    }

    /** Teks bebas tidak bisa dicocokkan dengan kunci jawaban. */
    public function hasAnswerKey(): bool
    {
        return $this !== self::Text;
    }

    public function isMultiple(): bool
    {
        return $this === self::Checkboxes;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        $descriptions = [];
        foreach (self::cases() as $case) {
            $descriptions[$case->value] = $case->description();
        }

        return $descriptions;
    }

    /**
     * @return array<string, string>
     */
    public static function icons(): array
    {
        $icons = [];
        foreach (self::cases() as $case) {
            $icons[$case->value] = $case->icon();
        }

        return $icons;
    }

    /**
     * @return array<string, string>
     */
    public static function yesNoOptions(): array
    {
        return [self::YES => self::YES, self::NO => self::NO];
    }
}
