<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Serial number, IMEI 1/2, dan tipe aset hanya disimpan sebagai atribut kustom.
 * Dulu datanya terpecah: sebagian di kolom assets, sebagian di atribut dengan
 * nama sama. Atribut diberi `key` tetap supaya kode tidak bergantung pada nama
 * yang boleh diganti admin, nilai kolom dipindah ke atribut yang masih kosong,
 * lalu kolomnya dihapus.
 *
 * Bila kolom dan atribut sama-sama terisi tetapi berbeda, nilai atribut dipakai
 * dan nilai kolom dicatat ke storage/app/asset-identity-conflicts-*.csv untuk
 * diperiksa manual.
 */
return new class extends Migration
{
    /** key => [nama atribut, tipe] */
    private const IDENTITY = [
        'serial_number' => ['Serial Number', 'text'],
        'imei1' => ['IMEI1', 'text'],
        'imei2' => ['IMEI2', 'text'],
        'type' => ['Type', 'text'],
    ];

    public function up(): void
    {
        Schema::table('custom_asset_attributes', function (Blueprint $table) {
            $table->string('key', 50)->nullable()->unique()->after('id');
        });

        $ids = [];

        foreach (self::IDENTITY as $key => [$name, $type]) {
            $ids[$key] = $this->attributeFor($key, $name, $type);
        }

        $conflicts = [];
        $columns = array_values(array_filter(array_keys(self::IDENTITY), fn (string $column): bool => Schema::hasColumn('assets', $column)));

        if ($columns !== []) {
            DB::table('assets')->select(['id', ...$columns])->orderBy('id')->chunkById(500, function ($assets) use ($columns, $ids, &$conflicts): void {
                $existing = DB::table('asset_attributes')
                    ->whereIn('asset_id', $assets->pluck('id'))
                    ->whereIn('custom_attribute_id', $ids)
                    ->get(['id', 'asset_id', 'custom_attribute_id', 'attribute_value'])
                    ->keyBy(fn ($row): string => $row->asset_id.':'.$row->custom_attribute_id);

                foreach ($assets as $asset) {
                    foreach ($columns as $key) {
                        $value = trim((string) $asset->{$key});

                        if ($value === '') {
                            continue;
                        }

                        $row = $existing->get($asset->id.':'.$ids[$key]);
                        $current = trim((string) $row?->attribute_value);

                        if ($row !== null && $current === '') {
                            DB::table('asset_attributes')->where('id', $row->id)->update(['attribute_value' => $value, 'updated_at' => now()]);
                        } elseif ($row === null) {
                            DB::table('asset_attributes')->insert([
                                'asset_id' => $asset->id,
                                'custom_attribute_id' => $ids[$key],
                                'attribute_value' => $value,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        } elseif ($current !== $value) {
                            $conflicts[] = [$asset->id, $key, $value, $current];
                        }
                    }
                }
            });
        }

        // Baris identitas tanpa nilai tidak berarti apa-apa.
        DB::table('asset_attributes')
            ->whereIn('custom_attribute_id', $ids)
            ->where(fn ($query) => $query->whereNull('attribute_value')->orWhereRaw("TRIM(attribute_value) = ''"))
            ->delete();

        $this->writeConflicts($conflicts);

        if ($columns !== []) {
            Schema::table('assets', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('type')->nullable()->after('brand_id');
            $table->string('serial_number')->nullable()->after('type');
            $table->string('imei1')->nullable()->after('serial_number');
            $table->string('imei2')->nullable()->after('imei1');
        });

        $ids = DB::table('custom_asset_attributes')->whereIn('key', array_keys(self::IDENTITY))->pluck('id', 'key');

        foreach ($ids as $key => $id) {
            DB::table('asset_attributes')->where('custom_attribute_id', $id)->orderBy('id')->chunkById(500, function ($rows) use ($key): void {
                foreach ($rows as $row) {
                    DB::table('assets')->where('id', $row->asset_id)->update([$key => $row->attribute_value]);
                }
            });
        }

        Schema::table('custom_asset_attributes', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn('key');
        });
    }

    /**
     * Atribut yang sudah ada (dicocokkan dari nama) diberi key; bila tidak ada, dibuat.
     */
    private function attributeFor(string $key, string $name, string $type): int
    {
        $existing = DB::table('custom_asset_attributes')
            ->whereNull('key')
            ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])
            ->orderBy('id')
            ->first();

        if ($existing !== null) {
            DB::table('custom_asset_attributes')->where('id', $existing->id)->update(['key' => $key, 'updated_at' => now()]);

            return (int) $existing->id;
        }

        return (int) DB::table('custom_asset_attributes')->insertGetId([
            'key' => $key,
            'name' => $name,
            'type' => $type,
            'required' => false,
            'is_active' => true,
            'category_id' => '[]',
            'is_notifiable' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<array{0: int, 1: string, 2: string, 3: string}>  $conflicts
     */
    private function writeConflicts(array $conflicts): void
    {
        if ($conflicts === []) {
            return;
        }

        $path = storage_path('app/asset-identity-conflicts-'.now()->format('Ymd-His').'.csv');
        $handle = fopen($path, 'w');
        fputcsv($handle, ['asset_id', 'key', 'nilai_kolom_lama', 'nilai_atribut_dipakai']);

        foreach ($conflicts as $conflict) {
            fputcsv($handle, $conflict);
        }

        fclose($handle);

        if (app()->runningInConsole()) {
            fwrite(STDOUT, '  '.count($conflicts)." nilai kolom berbeda dengan atribut; atribut dipakai, kolom dicatat di {$path}\n");
        }
    }
};
