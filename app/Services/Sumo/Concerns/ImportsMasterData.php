<?php

namespace App\Services\Sumo\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Area, divisi, dan master (barang, tipe pengajuan, jenis gangguan, master
 * asuransi, pengaturan). Id sumo dipertahankan.
 */
trait ImportsMasterData
{
    protected function importAreas(): void
    {
        $rows = [];

        foreach ($this->source('areas')->orderBy('id')->get() as $area) {
            $rows[] = ['id' => (int) $area->id, 'name' => $this->str($area->area) ?? "Area {$area->id}"] + $this->timestamps($area);
        }

        $this->upsert('areas', $rows, ['name', 'updated_at', 'deleted_at'], 'areas');
    }

    protected function importDivisions(): void
    {
        $rows = [];
        $taken = DB::table('divisions')->pluck('id', 'name')->map(fn ($id): int => (int) $id)->all();

        foreach ($this->source('divisions')->orderBy('id')->get() as $division) {
            $id = (int) $division->id;
            $name = $this->str($division->division) ?? "Divisi {$id}";

            // Nama divisi unik di shelf; nama yang sudah dipakai record lain diberi penanda.
            if (isset($taken[$name]) && $taken[$name] !== $id) {
                $this->report->warn("Divisi sumo #{$id} \"{$name}\" bentrok dengan divisi shelf #{$taken[$name]}; disimpan sebagai \"{$name} (sumo)\".");
                $name .= ' (sumo)';
            }

            $rows[] = [
                'id' => $id,
                'name' => $name,
                'area_id' => $this->idOrNull('areas', $division->area_id ?? null),
            ] + $this->timestamps($division);
        }

        $this->upsert('divisions', $rows, ['name', 'area_id', 'updated_at', 'deleted_at'], 'divisions');
    }

    protected function importMasters(): void
    {
        $this->importNamedMaster('categories', 'category', 'product_categories');
        $this->importNamedMaster('unit_types', 'unit_type', 'unit_types');
        $this->importNamedMaster('problem_report_categories', 'problem_report_category', 'problem_report_categories');
        $this->importNamedMaster('insurance_providers', 'insurance_provider', 'insurance_providers');
        $this->importNamedMaster('insurance_categories', 'insurance_category', 'insurance_categories');
        $this->importNamedMaster('insurance_scopes', 'insurance_scope', 'insurance_scopes');
        $this->importProducts();
        $this->importItemRequestTypes();
        $this->importItemRequestSettings();
        $this->importWaNotificationSettings();
    }

    private function importNamedMaster(string $sourceTable, string $nameColumn, string $targetTable): void
    {
        $rows = [];

        foreach ($this->source($sourceTable)->orderBy('id')->get() as $row) {
            $rows[] = ['id' => (int) $row->id, 'name' => $this->str($row->{$nameColumn}) ?? "#{$row->id}"] + $this->timestamps($row);
        }

        $this->upsert($targetTable, $rows, ['name', 'updated_at', 'deleted_at'], $targetTable);
    }

    private function importProducts(): void
    {
        $rows = [];

        foreach ($this->source('products')->orderBy('id')->get() as $product) {
            $rows[] = [
                'id' => (int) $product->id,
                'item_code' => $this->str($product->item_code ?? null),
                'name' => $this->str($product->product) ?? "Barang {$product->id}",
                'product_category_id' => $this->idOrNull('product_categories', $product->category_id ?? null),
                'unit_type_id' => $this->idOrNull('unit_types', $product->unit_type_id ?? null),
                'price' => $product->price ?? null,
                'description' => $this->str($product->description ?? null),
                'image' => $this->str($product->product_image ?? null),
                'stock' => $product->stock ?? null,
            ] + $this->timestamps($product);
        }

        $this->upsert('products', $rows, ['item_code', 'name', 'product_category_id', 'unit_type_id', 'price', 'description', 'image', 'stock', 'updated_at', 'deleted_at'], 'products');
    }

    private function importItemRequestTypes(): void
    {
        $flags = config('sumo-import.type_flags', []);
        $rows = [];

        foreach ($this->source('request_type')->orderBy('id')->get() as $type) {
            $name = $this->str($type->request_type) ?? "Tipe {$type->id}";
            $flag = $flags[strtoupper($name)] ?? [];

            $rows[] = [
                'id' => (int) $type->id,
                'name' => $name,
                'pic_division_id' => $this->idOrNull('divisions', $type->pic_division_id ?? null),
                'requires_attachment' => (bool) ($flag['requires_attachment'] ?? false),
                'requires_products' => (bool) ($flag['requires_products'] ?? false),
                'requires_accounting_approval' => (bool) ($flag['requires_accounting_approval'] ?? false),
                'is_active' => true,
            ] + $this->timestamps($type);
        }

        $this->upsert('item_request_types', $rows, ['name', 'pic_division_id', 'requires_attachment', 'requires_products', 'requires_accounting_approval', 'updated_at', 'deleted_at'], 'item_request_types');
    }

    private function importItemRequestSettings(): void
    {
        $rows = [];

        foreach ($this->source('request_settings')->orderBy('id')->get() as $setting) {
            $rows[] = [
                'id' => (int) $setting->id,
                'request_month' => $this->dateOrNull($setting->request_month ?? null, true),
                'open_date' => $this->dateOrNull($setting->open_date ?? null, true),
                'closed_date' => $this->dateOrNull($setting->closed_date ?? null, true),
                'request_detail' => $this->str($setting->request_detail ?? null),
            ] + $this->timestamps($setting, false);
        }

        $this->upsert('item_request_settings', $rows, ['request_month', 'open_date', 'closed_date', 'request_detail', 'updated_at'], 'item_request_settings');
    }

    private function importWaNotificationSettings(): void
    {
        $columns = ['is_enabled', 'target_phones', 'send_time', 'remind_rent', 'rent_days_before', 'remind_insurance', 'insurance_days_before', 'remind_request_cutoff', 'request_cutoff_days_before'];
        $rows = [];

        foreach ($this->source('wa_notification_settings')->orderBy('id')->get() as $setting) {
            $row = ['id' => (int) $setting->id];

            foreach ($columns as $column) {
                $row[$column] = $setting->{$column} ?? null;
            }

            $rows[] = $row + $this->timestamps($setting, false);
        }

        $this->upsert('wa_notification_settings', $rows, [...$columns, 'updated_at'], 'wa_notification_settings');
    }
}
