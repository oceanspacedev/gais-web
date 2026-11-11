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

    /**
     * Isi checklist: bagian => [judul, butir => label]. Jawaban tersimpan dengan
     * kunci yang sama di kolom `checklist`, jadi kunci yang sudah dipakai jangan diganti.
     *
     * @var array<string, array{title: string, items: array<string, string>}>
     */
    public const CHECKLIST = [
        'keamanan' => [
            'title' => 'Keamanan',
            'items' => [
                'pintu_utama' => 'Pintu utama / gerbang',
                'ruangan_kantor' => 'Ruangan kantor',
                'gudang_ruangan' => 'Gudang & ruangan',
            ],
        ],
        'kelistrikan' => [
            'title' => 'Kelistrikan',
            'items' => [
                'lampu_parkir' => 'Lampu area parkir',
                'lampu_gudang' => 'Lampu gudang',
                'ac_ruangan' => 'AC ruangan',
            ],
        ],
        'kondisi_aset' => [
            'title' => 'Kondisi Aset',
            'items' => [
                'mobil' => 'Mobil',
                'motor' => 'Motor',
                'apar' => 'APAR',
            ],
        ],
        'kebersihan' => [
            'title' => 'Kebersihan',
            'items' => [
                'halaman_parkir' => 'Halaman parkir',
                'area_toko' => 'Area toko / ruangan',
            ],
        ],
    ];

    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'm4v', 'webm', '3gp', 'mkv', 'avi'];

    protected $fillable = [
        'reference_number',
        'user_id',
        'location',
        'report_date',
        'checked_at',
        'checklist',
        'findings',
        'attachments',
    ];

    protected $casts = [
        'report_date' => 'date',
        'checked_at' => 'datetime',
        'checklist' => 'array',
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

    /**
     * Checklist siap tampil: bagian dan butir sesuai urutan form, ditambah
     * jawaban lama yang butirnya sudah tidak ada di form.
     *
     * @return list<array{title: string, items: list<array{label: string, value: string}>}>
     */
    public function checklistSections(): array
    {
        $answers = is_array($this->checklist) ? $this->checklist : [];
        $sections = [];

        foreach (self::CHECKLIST as $sectionKey => $section) {
            $sections[$sectionKey] = ['title' => $section['title'], 'items' => []];

            foreach ($section['items'] as $itemKey => $label) {
                $sections[$sectionKey]['items'][$itemKey] = [
                    'label' => $label,
                    'value' => (string) ($answers[$sectionKey][$itemKey] ?? ''),
                ];
            }
        }

        foreach ($answers as $sectionKey => $items) {
            foreach (is_array($items) ? $items : [] as $itemKey => $value) {
                $sections[$sectionKey] ??= ['title' => Str::headline((string) $sectionKey), 'items' => []];
                $sections[$sectionKey]['items'][$itemKey] ??= ['label' => Str::headline((string) $itemKey), 'value' => (string) $value];
            }
        }

        return array_values(array_map(
            fn (array $section): array => ['title' => $section['title'], 'items' => array_values($section['items'])],
            $sections,
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
