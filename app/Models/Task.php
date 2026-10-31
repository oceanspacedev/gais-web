<?php

namespace App\Models;

use App\Access\HasVisibility;
use App\Access\Rules\BusinessEntityRule;
use App\Access\Rules\EveryoneGrant;
use App\Access\Visibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, HasVisibility, SoftDeletes;

    protected $fillable = [
        'code',
        'business_entity_id',
        'work_timestamp',
        'name',
        'description',
        'vendor_id',
        'cost',
        'location',
        'status',
        'attachment',
        'document_upload',
    ];

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    // Use this to automatically handle the 'code' generation before creating a task
    protected static function booted()
    {
        // Handle saat Task dibuat
        static::creating(function ($task) {
            $task->code = static::generateTaskCode($task);
        });

        // Handle saat Task diubah
        static::updating(function ($task) {
            // Cek apakah business_entity_id diubah
            if ($task->isDirty('business_entity_id')) {
                // Jika business_entity_id berubah, generate kode baru
                $task->code = static::generateTaskCode($task);
            }
        });
    }

    protected static function generateTaskCode($task)
    {
        // Ambil tahun saat ini
        $year = now()->year;

        // Ambil entitas bisnis terkait
        $businessEntityCode = strtoupper($task->businessEntity->name); // Asumsikan kolom 'name' di 'business_entities'

        // Cari task terakhir berdasarkan tahun dan business entity yang sama
        // Termasuk pekerjaan di Sampah: kode unik dan tidak boleh terpakai ulang.
        $lastTaskForYear = Task::withTrashed()->where('business_entity_id', $task->business_entity_id)
            ->whereYear('created_at', $year)
            ->orderBy('code', 'desc')
            ->first();

        // Jika ada task dengan business entity dan tahun yang sama, ambil urutan terakhir, jika tidak mulai dari 1
        if ($lastTaskForYear) {
            // Ambil urutan terakhir dari kode (misalnya: 002/BAP/MAJU/GA/2024, ambil 002)
            $lastOrder = intval(explode('/', $lastTaskForYear->code)[0]); // Mengambil bagian urutan dari kode
            $nextOrder = $lastOrder + 1;
        } else {
            $nextOrder = 1;
        }

        // Buat format kode dengan nomor urut 3 digit
        return sprintf('%03d/BAP/%s/GA/%s', $nextOrder, $businessEntityCode, $year);
    }

    /**
     * Semua pemegang izin melihat record di badan usaha yang bisa ia akses;
     * record tanpa badan usaha terlihat oleh semua.
     */
    public static function visibility(): Visibility
    {
        return Visibility::make()
            ->require(new BusinessEntityRule)
            ->grant(new EveryoneGrant);
    }
}
