<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asuransi kendaraan sebelumnya dicatat sebagai atribut dokumen "Asuransi" di aset
 * MOBIL/MOTOR (nomor polis, masa berlaku, catatan "PROVIDER | JENIS | TERTANGGUNG |
 * Join: tanggal" dari audit kendaraan). Tiap nilai dipindah menjadi satu polis di
 * vehicle_insurances. Nilai atribut lama tidak dihapus; atributnya dinonaktifkan
 * supaya tidak tampil di form aset dan pengingatnya tidak terkirim dobel. Penerima
 * pengingat atribut ikut disalin ke nomor WA pengingat polis.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('custom_asset_attributes') || ! Schema::hasTable('asset_attributes')) {
            return;
        }

        $attribute = DB::table('custom_asset_attributes')
            ->where('name', config('vehicle-asset-reconciliation.insurance_attribute_name', 'Asuransi'))
            ->where('type', 'document_expiry')
            ->first();

        if ($attribute === null) {
            return;
        }

        $reminderPhones = $this->reminderPhones($attribute);

        DB::table('asset_attributes')
            ->join('assets', 'assets.id', '=', 'asset_attributes.asset_id')
            ->where('asset_attributes.custom_attribute_id', $attribute->id)
            ->orderBy('asset_attributes.id')
            ->get(['asset_attributes.asset_id', 'asset_attributes.attribute_value', 'asset_attributes.created_at', 'assets.business_entity_id'])
            ->each(function (object $row) use ($reminderPhones): void {
                $value = json_decode((string) $row->attribute_value, true);

                if (! is_array($value) || (blank($value['document_number'] ?? null) && blank($value['expires_at'] ?? null))) {
                    return;
                }

                $policyNumber = trim((string) ($value['document_number'] ?? '')) ?: '-';

                $exists = DB::table('vehicle_insurances')
                    ->where('asset_id', $row->asset_id)
                    ->where('policy_number', $policyNumber)
                    ->exists();

                if ($exists) {
                    return;
                }

                $parsed = $this->parseNotes($value['notes'] ?? null);
                $notes = array_filter([
                    $parsed['notes'],
                    filled($value['document_path'] ?? null) ? 'Dokumen: '.$value['document_path'] : null,
                ]);

                DB::table('vehicle_insurances')->insert([
                    'asset_id' => $row->asset_id,
                    'policy_number' => $policyNumber,
                    'insured_name' => $parsed['insured'],
                    'insurance_provider_id' => $this->providerId($parsed['provider']),
                    'extension_of_policy' => $parsed['type'],
                    'join_date' => $parsed['join_date'],
                    'expired_date' => $this->date($value['expires_at'] ?? null),
                    'business_entity_id' => $row->business_entity_id,
                    'status' => 'BERJALAN',
                    'notes' => $notes === [] ? null : implode("\n", $notes),
                    'reminder_phones' => $reminderPhones,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => now(),
                ]);
            });

        DB::table('custom_asset_attributes')->where('id', $attribute->id)->update([
            'is_active' => false,
            'is_notifiable' => false,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Tabel vehicle_insurances dihapus oleh migrasi sebelumnya; atribut lama diaktifkan lagi.
        DB::table('custom_asset_attributes')
            ->where('name', config('vehicle-asset-reconciliation.insurance_attribute_name', 'Asuransi'))
            ->where('type', 'document_expiry')
            ->update(['is_active' => true, 'is_notifiable' => true, 'updated_at' => now()]);
    }

    /**
     * Catatan audit kendaraan: "PROVIDER | JENIS | TERTANGGUNG | Join: YYYY-MM-DD".
     * Catatan lain disimpan apa adanya.
     *
     * @return array{provider: ?string, type: ?string, insured: ?string, join_date: ?string, notes: ?string}
     */
    private function parseNotes(?string $notes): array
    {
        $result = ['provider' => null, 'type' => null, 'insured' => null, 'join_date' => null, 'notes' => null];
        $notes = trim((string) $notes);

        if ($notes === '') {
            return $result;
        }

        $parts = array_map('trim', explode('|', $notes));
        $join = null;

        foreach ($parts as $index => $part) {
            if (preg_match('/^join\s*:\s*(.+)$/i', $part, $match) === 1) {
                $join = $this->date($match[1]);
                unset($parts[$index]);
            }
        }

        $parts = array_values(array_filter($parts, fn (string $part): bool => $part !== ''));

        if (count($parts) < 3) {
            return ['join_date' => $join, 'notes' => $notes] + $result;
        }

        return [
            'provider' => $parts[0],
            'type' => $parts[1],
            'insured' => implode(' | ', array_slice($parts, 2)),
            'join_date' => $join,
            'notes' => null,
        ];
    }

    /**
     * Cocokkan ke master provider (tanpa beda huruf besar, dan tanpa akhiran
     * "ASURANSI"/"INSURANCE", mis. "BCA INSURANCE" = "BCA"); buat baru bila tidak ada.
     */
    private function providerId(?string $name): ?int
    {
        if (blank($name)) {
            return null;
        }

        $normalize = fn (string $value): string => trim((string) preg_replace('/\s+(asuransi|insurance)$/i', '', strtoupper(trim($value))));
        $wanted = $normalize($name);

        $match = DB::table('insurance_providers')
            ->orderByRaw('deleted_at IS NOT NULL')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->first(fn (object $provider): bool => $normalize($provider->name) === $wanted);

        if ($match !== null) {
            return (int) $match->id;
        }

        return (int) DB::table('insurance_providers')->insertGetId([
            'name' => strtoupper(trim($name)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function reminderPhones(object $attribute): ?string
    {
        $userIds = json_decode((string) ($attribute->notification_recipient_user_ids ?? '[]'), true);
        $numbers = json_decode((string) ($attribute->notification_recipient_whatsapp_numbers ?? '[]'), true);

        $phones = collect(is_array($numbers) ? $numbers : [])
            ->merge(is_array($userIds) && $userIds !== [] && Schema::hasColumn('users', 'whatsapp_number')
                ? DB::table('users')->whereIn('id', $userIds)->pluck('whatsapp_number')
                : [])
            ->map(fn (mixed $phone): string => (string) preg_replace('/[^0-9]/', '', (string) $phone))
            ->filter()
            ->unique()
            ->values();

        return $phones->isEmpty() ? null : $phones->implode(', ');
    }

    private function date(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
};
