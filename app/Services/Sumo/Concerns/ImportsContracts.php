<?php

namespace App\Services\Sumo\Concerns;

/**
 * Polis asuransi dan perjanjian sewa beserta riwayat pembaharuannya, plus log
 * pengingat WhatsApp. Id sumo dipertahankan.
 */
trait ImportsContracts
{
    private const INSURANCE_MONEY = ['stock_worth', 'building_worth', 'actual_stock_worth', 'stock_premium', 'building_premium'];

    private const RENT_MONEY = ['rent_per_year', 'cvcs_fund', 'online_fund'];

    private const RENT_TEXT = ['rent_code', 'first_party', 'second_party', 'deduction_evidence', 'deduction_evidence_file', 'document', 'document_file', 'payment_evidence_file', 'month_before_reminder', 'notes'];

    protected function importInsurances(): void
    {
        $rows = [];

        foreach ($this->source('insurances')->orderBy('id')->get() as $insurance) {
            $rows[] = [
                'id' => (int) $insurance->id,
                'policy_number' => $this->str($insurance->policy_number ?? null) ?? '-',
                'insured_name' => $this->str($insurance->insured_name ?? null) ?? '-',
                'warehouse_code' => $this->str($insurance->warehouse_code ?? null),
                'insured_address' => $this->str($insurance->insured_address ?? null),
                'insured_detail' => $this->str($insurance->insured_detail ?? null),
                'risk_address' => $this->str($insurance->risk_address ?? null),
                'stock_provider_id' => $this->idOrNull('insurance_providers', $insurance->stock_inprov_id ?? null),
                'building_provider_id' => $this->idOrNull('insurance_providers', $insurance->building_inprov_id ?? null),
                'insurance_category_id' => $this->idOrNull('insurance_categories', $insurance->insurance_category_id ?? null),
                'insurance_scope_id' => $this->idOrNull('insurance_scopes', $insurance->insurance_scope_id ?? null),
                'extension_of_policy' => $this->str($insurance->extension_of_policy ?? null),
                'join_date' => $this->dateOrNull($insurance->join_date ?? null, true),
                'expired_date' => $this->dateOrNull($insurance->expired_date ?? null, true),
                'user_id' => $this->mapUser($insurance->user_id ?? null),
                'business_entity_id' => $this->entityOfSumoUser($insurance->user_id ?? null),
                'payment_evidence' => $this->str($insurance->payment_evidence ?? null),
                'status' => strtoupper($this->str($insurance->status ?? null) ?? 'BERJALAN'),
                'notes' => $this->str($insurance->notes ?? null),
                'reminder_phones' => $this->str($insurance->reminder_phones ?? null),
            ] + $this->money($insurance, self::INSURANCE_MONEY) + $this->timestamps($insurance);
        }

        $this->upsert('insurances', $rows, $this->updatable($rows), 'insurances');

        $updates = [];

        foreach ($this->source('insurance_updates')->orderBy('id')->get() as $update) {
            if (! $this->has('insurances', $update->insurance_id ?? null)) {
                $this->report->add('insurance_updates.orphan_skipped');

                continue;
            }

            $updates[] = [
                'id' => (int) $update->id,
                'insurance_id' => (int) $update->insurance_id,
                'policy_number' => $this->str($update->policy_number ?? null) ?? '-',
                'stock_provider_id' => $this->idOrNull('insurance_providers', $update->stock_inprov_id ?? null),
                'building_provider_id' => $this->idOrNull('insurance_providers', $update->building_inprov_id ?? null),
                'extension_of_policy' => $this->str($update->extension_of_policy ?? null),
                'join_date' => $this->dateOrNull($update->join_date ?? null, true),
                'expired_date' => $this->dateOrNull($update->expired_date ?? null, true),
                'user_id' => $this->mapUser($update->user_id ?? null),
                'status' => strtoupper($this->str($update->status ?? null) ?? 'BERJALAN'),
                'payment_evidence' => $this->str($update->payment_evidence ?? null),
                'notes' => $this->str($update->notes ?? null),
            ] + $this->money($update, self::INSURANCE_MONEY) + $this->timestamps($update);
        }

        $this->upsert('insurance_updates', $updates, $this->updatable($updates), 'insurance_updates');
    }

    protected function importRents(): void
    {
        $rows = [];

        foreach ($this->source('rents')->orderBy('id')->get() as $rent) {
            $rows[] = [
                'id' => (int) $rent->id,
                'rented_address' => $this->str($rent->rented_address ?? null),
                'rented_detail' => $this->str($rent->rented_detail ?? null) ?? '-',
                'join_date' => $this->dateOrNull($rent->join_date ?? null, true),
                'expired_date' => $this->dateOrNull($rent->expired_date ?? null, true),
                'status' => strtoupper($this->str($rent->status ?? null) ?? 'BERJALAN'),
                'user_id' => $this->mapUser($rent->user_id ?? null),
                'business_entity_id' => $this->entityOfSumoUser($rent->user_id ?? null),
                'reminder_phones' => $this->str($rent->reminder_phones ?? null),
            ] + $this->text($rent, self::RENT_TEXT) + $this->money($rent, self::RENT_MONEY) + $this->timestamps($rent);
        }

        $this->upsert('rents', $rows, $this->updatable($rows), 'rents');

        $updates = [];

        foreach ($this->source('rent_updates')->orderBy('id')->get() as $update) {
            if (! $this->has('rents', $update->rent_id ?? null)) {
                $this->report->add('rent_updates.orphan_skipped');

                continue;
            }

            $updates[] = [
                'id' => (int) $update->id,
                'rent_id' => (int) $update->rent_id,
                'join_date' => $this->dateOrNull($update->join_date ?? null, true),
                'expired_date' => $this->dateOrNull($update->expired_date ?? null, true),
                'status' => strtoupper($this->str($update->status ?? null) ?? 'BERJALAN'),
                'user_id' => $this->mapUser($update->user_id ?? null),
            ] + $this->text($update, self::RENT_TEXT) + $this->money($update, self::RENT_MONEY) + $this->timestamps($update);
        }

        $this->upsert('rent_updates', $updates, $this->updatable($updates), 'rent_updates');
    }

    protected function importWaLogs(): void
    {
        $rows = [];

        foreach ($this->source('wa_notification_logs')->orderBy('id')->get() as $log) {
            $rows[] = [
                'id' => (int) $log->id,
                'notif_type' => strtoupper($this->str($log->notif_type ?? null) ?? 'TEST'),
                'reference_id' => $this->intOrNull($log->reference_id ?? null),
                'phone' => $this->str($log->phone ?? null) ?? '-',
                'sent_date' => $this->dateOrNull($log->sent_date ?? null, true) ?? now()->toDateString(),
                'status' => strtoupper($this->str($log->status ?? null) ?? 'FAILED'),
                'message_preview' => $this->str($log->message_preview ?? null),
                'response_payload' => $this->str($log->response_payload ?? null),
            ] + $this->timestamps($log, false);
        }

        $this->upsert('wa_notification_logs', $rows, ['notif_type', 'reference_id', 'phone', 'sent_date', 'status', 'message_preview', 'response_payload', 'updated_at'], 'wa_notification_logs');
    }

    /**
     * Semua kolom baris pertama selain id dan created_at diperbarui saat impor ulang.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function updatable(array $rows): array
    {
        return array_values(array_diff(array_keys($rows[0] ?? ['id' => 1]), ['id', 'created_at']));
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, int|null>
     */
    private function money(object $row, array $columns): array
    {
        $values = [];

        foreach ($columns as $column) {
            $values[$column] = $this->intOrNull($row->{$column} ?? null);
        }

        return $values;
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, string|null>
     */
    private function text(object $row, array $columns): array
    {
        $values = [];

        foreach ($columns as $column) {
            $values[$column] = $this->str($row->{$column} ?? null);
        }

        $values['rent_code'] ??= '-';
        $values['first_party'] ??= '-';
        $values['second_party'] ??= '-';

        return $values;
    }
}
