<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Badge navigasi dan statistik beranda menghitung pengajuan per client_status
 * di setiap halaman panel. Index (item_request_type_id, client_status) tidak
 * bisa dipakai untuk filter client_status saja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_requests', function (Blueprint $table) {
            $table->index(['client_status', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::table('item_requests', function (Blueprint $table) {
            $table->dropIndex(['client_status', 'requested_at']);
        });
    }
};
