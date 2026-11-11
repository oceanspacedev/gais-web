<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cakupan yang juga menyetujui jumlah (tahap Manager). Executor baru boleh
 * memproses pengajuan yang masuk cakupan seperti ini setelah tahap Manager
 * disetujui (sumo: ATK area TOP/MKLI menunggu WHM/MKLI-HO, NOTA menunggu AUDIT).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_request_scopes', function (Blueprint $table): void {
            $table->boolean('approves_quantity')->default(false)->after('area_id');
        });
    }

    public function down(): void
    {
        Schema::table('item_request_scopes', function (Blueprint $table): void {
            $table->dropColumn('approves_quantity');
        });
    }
};
