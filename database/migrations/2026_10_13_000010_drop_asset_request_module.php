<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan Aset dihapus: pengajuan barang kini hanya lewat modul Pengajuan
 * Barang (item_requests) dari sumo-web.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assets') && Schema::hasColumn('assets', 'asset_request_id')) {
            Schema::table('assets', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('asset_request_id');
            });
        }

        Schema::dropIfExists('asset_request_approvals');
        Schema::dropIfExists('asset_request_items');
        Schema::dropIfExists('asset_requests');

        $permissionIds = DB::table('permissions')->where('name', 'like', '%asset::request%')->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }

    public function down(): void
    {
        // Penghapusan tidak bisa dibatalkan.
    }
};
