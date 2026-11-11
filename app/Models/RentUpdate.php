<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Models\Concerns\ComputesRentBilling;
use App\Models\Concerns\GeneratesSequentialCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Perpanjangan perjanjian sewa (sumo: rent_updates). Kode RENTUP#### dibuat
 * otomatis bila kosong, seperti di sumo.
 */
class RentUpdate extends Model
{
    use ComputesRentBilling, GeneratesSequentialCode, HasFactory, SoftDeletes;

    public const CODE_PREFIX = 'RENTUP';

    protected $fillable = [
        'rent_id', 'rent_code', 'first_party', 'second_party', 'rent_per_year', 'cvcs_fund', 'online_fund',
        'join_date', 'expired_date', 'deduction_evidence', 'deduction_evidence_file', 'document', 'document_file',
        'payment_evidence_file', 'status', 'month_before_reminder', 'user_id', 'notes',
    ];

    protected $casts = [
        'join_date' => 'date',
        'expired_date' => 'date',
        'status' => ContractStatus::class,
        'rent_per_year' => 'integer',
        'cvcs_fund' => 'integer',
        'online_fund' => 'integer',
    ];

    protected $attributes = ['status' => 'BERJALAN'];

    public function rent(): BelongsTo
    {
        return $this->belongsTo(Rent::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
