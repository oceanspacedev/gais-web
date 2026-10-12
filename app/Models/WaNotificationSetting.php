<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan tunggal pengingat WhatsApp (satu baris, id 1).
 */
class WaNotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_enabled', 'target_phones', 'send_time', 'remind_rent', 'rent_days_before',
        'remind_insurance', 'insurance_days_before', 'remind_request_cutoff', 'request_cutoff_days_before',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'remind_rent' => 'boolean',
        'remind_insurance' => 'boolean',
        'remind_request_cutoff' => 'boolean',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'is_enabled' => true,
            'target_phones' => '',
            'send_time' => '08:00',
            'remind_rent' => true,
            'rent_days_before' => '30,14,7,1',
            'remind_insurance' => true,
            'insurance_days_before' => '30,14,7,1',
            'remind_request_cutoff' => true,
            'request_cutoff_days_before' => '3,1',
        ]);
    }

    /**
     * @return list<string>
     */
    public function phoneList(): array
    {
        return self::parsePhoneList($this->target_phones);
    }

    /**
     * Nomor dipisah koma, hanya angka yang disimpan.
     *
     * @return list<string>
     */
    public static function parsePhoneList(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $phones = array_map(fn (string $phone): string => preg_replace('/[^0-9]/', '', trim($phone)) ?? '', explode(',', $raw));

        return array_values(array_unique(array_filter($phones)));
    }

    /**
     * @return list<int>
     */
    public function daysBefore(string $column): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $this->{$column}))));
    }
}
