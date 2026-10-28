<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aplikasi berganti nama dari Shelf ke GAIS (General Affair Information System).
 * Kolom qty rekonsiliasi, kunci summary batch kendaraan, dan nama situs/PWA yang
 * tersimpan di settings ikut diganti. Setting yang sudah diubah admin ke nama lain
 * tidak disentuh.
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['sites', 'site_name'],
        ['pwa', 'pwa_app_name'],
        ['pwa', 'pwa_short_name'],
    ];

    public function up(): void
    {
        $this->renameQtyColumn('shelf_qty', 'gais_qty');
        $this->renameSummaryKey('orphan_shelf_plates', 'orphan_gais_plates');
        $this->renameSettings('Shelf', 'GAIS');
    }

    public function down(): void
    {
        $this->renameSettings('GAIS', 'Shelf');
        $this->renameSummaryKey('orphan_gais_plates', 'orphan_shelf_plates');
        $this->renameQtyColumn('gais_qty', 'shelf_qty');
    }

    private function renameQtyColumn(string $from, string $to): void
    {
        if (! Schema::hasColumn('asset_reconciliation_items', $from)) {
            return;
        }

        Schema::table('asset_reconciliation_items', function (Blueprint $table) use ($from, $to): void {
            $table->renameColumn($from, $to);
        });
    }

    private function renameSummaryKey(string $from, string $to): void
    {
        if (! Schema::hasTable('asset_reconciliations')) {
            return;
        }

        DB::table('asset_reconciliations')
            ->where('summary', 'like', '%"'.$from.'"%')
            ->orderBy('id')
            ->each(function (object $row) use ($from, $to): void {
                $summary = json_decode((string) $row->summary, true);

                if (! is_array($summary) || ! array_key_exists($from, $summary)) {
                    return;
                }

                $summary[$to] = $summary[$from];
                unset($summary[$from]);

                DB::table('asset_reconciliations')->where('id', $row->id)->update(['summary' => json_encode($summary)]);
            });
    }

    private function renameSettings(string $from, string $to): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach (self::SETTINGS as [$group, $name]) {
            $setting = DB::table('settings')->where('group', $group)->where('name', $name)->first(['id', 'payload']);

            // payload kolom JSON: dibandingkan setelah decode, bukan sebagai string.
            if ($setting !== null && json_decode((string) $setting->payload) === $from) {
                DB::table('settings')->where('id', $setting->id)->update(['payload' => json_encode($to)]);
            }
        }
    }
};
