<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\InsuranceProvider;
use App\Models\VehicleInsurance;
use App\Support\VehicleAuditDate;

/**
 * Data asuransi dari workbook audit kendaraan (kolom EXPIRED DATE, NO. POLIS,
 * NAMA ASURANSI, JENIS ASURANSI, NAMA TERTANGGUNG, JOIN DATE) dicatat ke menu
 * Asuransi Kendaraan, bukan lagi ke atribut dokumen "Asuransi" di aset.
 */
class VehicleInsuranceAuditSync
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function needsSync(Asset $asset, array $payload): bool
    {
        $expiresAt = VehicleAuditDate::toDateString($payload['insurance_expires_at'] ?? null);

        if ($expiresAt === null) {
            return false;
        }

        $current = $this->latestFor($asset)?->effectiveExpiredDate()?->toDateString();

        return $current !== $expiresAt;
    }

    /**
     * Nomor polis yang sudah ada (polis atau salah satu pembaharuannya) diperbarui;
     * nomor baru pada kendaraan yang sudah punya polis dicatat sebagai pembaharuan,
     * dan kendaraan tanpa polis mendapat polis baru.
     *
     * @param  array<string, mixed>  $payload
     */
    public function sync(Asset $asset, array $payload): ?VehicleInsurance
    {
        $expiresAt = VehicleAuditDate::toDateString($payload['insurance_expires_at'] ?? null);

        if ($expiresAt === null) {
            return null;
        }

        $policyNumber = trim((string) ($payload['insurance_policy_number'] ?? '')) ?: ($this->latestFor($asset)?->effective('policy_number') ?? '-');
        $values = array_filter([
            'policy_number' => $policyNumber,
            'insurance_provider_id' => $this->providerId($payload['insurance_name'] ?? null),
            'extension_of_policy' => $this->text($payload['insurance_type'] ?? null),
            'join_date' => VehicleAuditDate::toDateString($payload['insurance_join_date'] ?? null),
            'expired_date' => $expiresAt,
        ], fn (mixed $value): bool => $value !== null);
        $insured = $this->text($payload['insurance_insured'] ?? null);

        $insurance = $this->latestFor($asset);

        if ($insurance === null) {
            return VehicleInsurance::create($values + [
                'asset_id' => $asset->id,
                'insured_name' => $insured,
                'business_entity_id' => $asset->business_entity_id,
            ]);
        }

        $update = $insurance->updates->firstWhere('policy_number', $policyNumber);

        if ($update !== null) {
            $update->update($values);
        } elseif ($insurance->policy_number === $policyNumber) {
            $insurance->update($values + ($insured !== null ? ['insured_name' => $insured] : []));
        } else {
            $insurance->updates()->create($values);
        }

        return $insurance->refresh();
    }

    private function latestFor(Asset $asset): ?VehicleInsurance
    {
        return VehicleInsurance::query()
            ->where('asset_id', $asset->id)
            ->with('updates')
            ->latest('id')
            ->first();
    }

    private function providerId(mixed $name): ?int
    {
        $name = $this->text($name);

        if ($name === null) {
            return null;
        }

        $normalize = fn (string $value): string => trim((string) preg_replace('/\s+(asuransi|insurance)$/i', '', strtoupper(trim($value))));
        $wanted = $normalize($name);

        $match = InsuranceProvider::query()
            ->orderBy('id')
            ->get(['id', 'name'])
            ->first(fn (InsuranceProvider $provider): bool => $normalize($provider->name) === $wanted);

        return (int) ($match?->id ?? InsuranceProvider::create(['name' => strtoupper($name)])->id);
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
