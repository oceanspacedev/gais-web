<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master data modul yang dipindahkan dari sumo-web (Web Gais): area, barang,
 * kategori barang, tipe unit, tipe pengajuan, jenis gangguan, dan master
 * asuransi. Divisi mendapat area, dan cakupan pengajuan per divisi menggantikan
 * aturan area yang dulu hard-coded di sumo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('name')->constrained('areas')->nullOnDelete();
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('unit_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('item_code')->nullable();
            $table->string('name');
            $table->foreignId('product_category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignId('unit_type_id')->nullable()->constrained('unit_types')->nullOnDelete();
            $table->decimal('price', 15, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('stock', 12, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('item_request_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('pic_division_id')->nullable()->constrained('divisions')->nullOnDelete();
            // Wajib lampiran persetujuan atasan (sumo: tipe ASSET/NONASSET dan NOTA).
            $table->boolean('requires_attachment')->default(false);
            // Wajib daftar barang (sumo: tipe ATK).
            $table->boolean('requires_products')->default(false);
            // Lewat tahap Accounting / status PO (sumo: tipe ASSET/NONASSET).
            $table->boolean('requires_accounting_approval')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Periode cutoff pengajuan ATK bulanan (ditampilkan di beranda dan reminder WA).
        Schema::create('item_request_settings', function (Blueprint $table) {
            $table->id();
            $table->date('request_month')->nullable();
            $table->date('open_date')->nullable();
            $table->date('closed_date')->nullable();
            $table->string('request_detail')->nullable();
            $table->timestamps();
        });

        // Cakupan yang boleh dilihat/diproses anggota divisi (executor): tipe
        // dan/atau area pemohon. Baris tanpa tipe = semua tipe, tanpa area = semua area.
        Schema::create('item_request_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained('divisions')->cascadeOnDelete();
            $table->foreignId('item_request_type_id')->nullable()->constrained('item_request_types')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['division_id', 'item_request_type_id', 'area_id'], 'item_request_scopes_unique');
        });

        Schema::create('problem_report_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        foreach (['insurance_providers', 'insurance_categories', 'insurance_scopes'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_scopes');
        Schema::dropIfExists('insurance_categories');
        Schema::dropIfExists('insurance_providers');
        Schema::dropIfExists('problem_report_categories');
        Schema::dropIfExists('item_request_scopes');
        Schema::dropIfExists('item_request_settings');
        Schema::dropIfExists('item_request_types');
        Schema::dropIfExists('products');
        Schema::dropIfExists('unit_types');
        Schema::dropIfExists('product_categories');

        Schema::table('divisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
        });

        Schema::dropIfExists('areas');
    }
};
