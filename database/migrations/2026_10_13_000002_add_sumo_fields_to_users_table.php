<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan barang dan laporan gangguan memakai divisi pemohon (cakupan
 * executor) dan atasan yang menyetujui. legacy_sumo_user_id menandai akun
 * yang dibuat atau dicocokkan oleh perintah sumo:import supaya impor bisa diulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable()->after('job_title_id')->constrained('divisions')->nullOnDelete();
            $table->foreignId('approver_user_id')->nullable()->after('division_id')->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_sumo_user_id')->nullable()->unique()->after('approver_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['legacy_sumo_user_id']);
            $table->dropColumn('legacy_sumo_user_id');
            $table->dropConstrainedForeignId('approver_user_id');
            $table->dropConstrainedForeignId('division_id');
        });
    }
};
