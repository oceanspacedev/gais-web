<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Isi master ruangan dan shift dari teks yang sudah diketik OB. Hanya spasi dan
 * huruf besar/kecil yang disamakan; salah ketik ("Toliet") sengaja dibiarkan
 * jadi ruangan terpisah dan digabungkan admin lewat menu Master Ruangan.
 * Lokasi belum diketahui, jadi dibiarkan kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->backfill('room', 'ob_room_id', 'ob_rooms', fn (string $name): array => [
            'name' => $name,
            'is_active' => true,
        ]);

        $this->backfill('shift_label', 'ob_shift_id', 'ob_shifts', fn (string $name): array => [
            'name' => $name,
            'is_active' => true,
        ]);
    }

    public function down(): void
    {
        // Kolom dan tabel master dihapus oleh migrasi sebelumnya.
    }

    /**
     * @param  callable(string): array<string, mixed>  $attributes
     */
    private function backfill(string $textColumn, string $foreignKey, string $masterTable, callable $attributes): void
    {
        $groups = DB::table('ob_checksheets')
            ->whereNotNull($textColumn)
            ->whereNull($foreignKey)
            ->select($textColumn, DB::raw('count(*) as total'))
            ->groupBy($textColumn)
            ->get()
            ->map(fn (object $row): array => [
                'raw' => $row->{$textColumn},
                'name' => trim((string) preg_replace('/\s+/u', ' ', $row->{$textColumn})),
                'total' => (int) $row->total,
            ])
            ->filter(fn (array $row): bool => $row['name'] !== '')
            ->groupBy(fn (array $row): string => mb_strtolower($row['name']));

        $now = now();

        foreach ($groups as $variants) {
            // Ejaan yang paling sering dipakai menjadi nama master.
            $name = $variants
                ->groupBy('name')
                ->map(fn ($rows, string $name): array => ['name' => $name, 'total' => $rows->sum('total')])
                ->sortBy([['total', 'desc'], ['name', 'asc']])
                ->first()['name'];

            $id = DB::table($masterTable)->insertGetId([
                ...$attributes($name),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('ob_checksheets')
                ->whereIn($textColumn, $variants->pluck('raw')->all())
                ->whereNull($foreignKey)
                ->update([$foreignKey => $id]);
        }
    }
};
