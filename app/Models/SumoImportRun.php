<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Satu kali impor data sumo dari panel: dump diunggah, dimuat ke database
 * staging, diuji coba, lalu (bila laporannya benar) diimpor sungguhan.
 */
class SumoImportRun extends Model
{
    public const PERMISSION = 'Manage:SumoImport';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_LOADING = 'loading';

    public const STATUS_LOADED = 'loaded';

    public const STATUS_DRY_RUNNING = 'dry_running';

    public const STATUS_READY = 'ready';

    public const STATUS_IMPORTING = 'importing';

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    /** Status yang menandakan job sedang antre atau berjalan. */
    public const BUSY_STATUSES = [self::STATUS_QUEUED, self::STATUS_LOADING, self::STATUS_DRY_RUNNING, self::STATUS_IMPORTING];

    public const STATUS_LABELS = [
        self::STATUS_QUEUED => 'Menunggu antrean',
        self::STATUS_LOADING => 'Memuat ke staging',
        self::STATUS_LOADED => 'Dimuat',
        self::STATUS_DRY_RUNNING => 'Uji coba berjalan',
        self::STATUS_READY => 'Siap diimpor',
        self::STATUS_IMPORTING => 'Mengimpor',
        self::STATUS_IMPORTED => 'Sudah diimpor',
        self::STATUS_FAILED => 'Gagal',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    public const STATUS_COLORS = [
        self::STATUS_QUEUED => 'gray',
        self::STATUS_LOADING => 'info',
        self::STATUS_LOADED => 'info',
        self::STATUS_DRY_RUNNING => 'info',
        self::STATUS_READY => 'warning',
        self::STATUS_IMPORTING => 'info',
        self::STATUS_IMPORTED => 'success',
        self::STATUS_FAILED => 'danger',
        self::STATUS_CANCELLED => 'gray',
    ];

    protected $fillable = [
        'user_id',
        'original_name',
        'file_disk',
        'file_path',
        'file_size',
        'file_sha256',
        'notes',
        'status',
        'staged',
        'source_tables',
        'dry_run_report',
        'import_report',
        'log',
        'error',
        'loaded_at',
        'dry_run_at',
        'imported_at',
        'imported_by_id',
        'failed_at',
    ];

    protected $casts = [
        'staged' => 'boolean',
        'source_tables' => 'array',
        'dry_run_report' => 'array',
        'import_report' => 'array',
        'loaded_at' => 'datetime',
        'dry_run_at' => 'datetime',
        'imported_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_id');
    }

    /**
     * Ada run lain yang antre atau berjalan; staging dipakai bergantian.
     */
    public static function anyBusy(): bool
    {
        return static::query()->whereIn('status', self::BUSY_STATUSES)->exists();
    }

    public function isBusy(): bool
    {
        return in_array($this->status, self::BUSY_STATUSES, true);
    }

    public function canDryRun(): bool
    {
        return $this->staged && ! static::anyBusy();
    }

    public function canImport(): bool
    {
        return $this->staged && $this->status === self::STATUS_READY && $this->dry_run_report !== null && ! static::anyBusy();
    }

    public function canReload(): bool
    {
        return ! $this->staged && ! $this->isBusy() && ! static::anyBusy();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Tambah baris log. Selama job berjalan baris disimpan di cache (impor berjalan
     * di dalam transaksi GAIS, jadi tulisan ke tabel baru terlihat setelah commit);
     * flushLog() memindahkannya ke kolom `log`.
     */
    public function appendLog(string $line): void
    {
        $lines = Cache::get($this->logCacheKey(), []);
        $lines[] = now()->format('H:i:s').'  '.$line;

        Cache::put($this->logCacheKey(), $lines, now()->addDay());
    }

    public function flushLog(): void
    {
        $lines = Cache::pull($this->logCacheKey(), []);

        if ($lines !== []) {
            $this->forceFill(['log' => ltrim(($this->log ?? '')."\n".implode("\n", $lines))])->save();
        }
    }

    /**
     * Log tersimpan ditambah baris yang belum dipindah dari cache.
     */
    public function displayLog(): string
    {
        return trim(($this->log ?? '')."\n".implode("\n", Cache::get($this->logCacheKey(), [])));
    }

    public function markFailed(Throwable $exception): void
    {
        $this->appendLog('GAGAL: '.$exception->getMessage());
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'error' => mb_strimwidth($exception->getMessage(), 0, 4000, '...'),
            'failed_at' => now(),
        ])->save();
    }

    private function logCacheKey(): string
    {
        return "sumo-import-run:{$this->getKey()}:log";
    }
}
