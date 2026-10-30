<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Models\Concerns\ComputesInsuranceStock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pembaharuan polis (sumo: insurance_updates).
 */
class InsuranceUpdate extends Model
{
    use ComputesInsuranceStock, HasFactory, SoftDeletes;

    protected $fillable = [
        'insurance_id', 'policy_number', 'stock_provider_id', 'building_provider_id', 'stock_worth', 'building_worth',
        'actual_stock_worth', 'stock_premium', 'building_premium', 'extension_of_policy', 'join_date', 'expired_date',
        'user_id', 'status', 'payment_evidence', 'notes',
    ];

    protected $casts = [
        'join_date' => 'date',
        'expired_date' => 'date',
        'status' => ContractStatus::class,
        'stock_worth' => 'integer',
        'building_worth' => 'integer',
        'actual_stock_worth' => 'integer',
        'stock_premium' => 'integer',
        'building_premium' => 'integer',
    ];

    protected $attributes = ['status' => 'BERJALAN'];

    public function insurance(): BelongsTo
    {
        return $this->belongsTo(Insurance::class);
    }

    public function stockProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class, 'stock_provider_id')->withTrashed();
    }

    public function buildingProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class, 'building_provider_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
