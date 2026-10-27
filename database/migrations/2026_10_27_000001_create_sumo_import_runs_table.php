<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak impor data sumo dari panel: file dump yang diunggah, tabel yang
 * dimuat ke staging, laporan uji coba dan impor sungguhan, serta log-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sumo_import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name');
            $table->string('file_disk', 32);
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->char('file_sha256', 64)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32)->index();
            // Dump run ini yang sedang ada di database staging; hanya satu run yang true.
            $table->boolean('staged')->default(false);
            $table->json('source_tables')->nullable();
            $table->json('dry_run_report')->nullable();
            $table->json('import_report')->nullable();
            $table->longText('log')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('loaded_at')->nullable();
            $table->timestamp('dry_run_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->foreignId('imported_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sumo_import_runs');
    }
};
