<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asuransi kendaraan dipisah dari asuransi bangunan (tabel insurances, yang isinya
 * polis gudang: stok dan bangunan). Satu polis tertaut ke satu aset kendaraan dan
 * punya riwayat pembaharuan sendiri, sama seperti asuransi bangunan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_insurances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('policy_number');
            $table->string('insured_name')->nullable();
            $table->foreignId('insurance_provider_id')->nullable()->constrained('insurance_providers')->nullOnDelete();
            $table->foreignId('insurance_category_id')->nullable()->constrained('insurance_categories')->nullOnDelete();
            $table->string('extension_of_policy')->nullable();
            $table->bigInteger('insured_worth')->nullable();
            $table->bigInteger('premium')->nullable();
            $table->date('join_date')->nullable();
            $table->date('expired_date')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('business_entity_id')->nullable()->constrained('business_entities')->nullOnDelete();
            $table->string('payment_evidence')->nullable();
            $table->string('status', 20)->default('BERJALAN');
            $table->text('notes')->nullable();
            $table->string('reminder_phones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('policy_number');
            $table->index(['status', 'expired_date']);
        });

        Schema::create('vehicle_insurance_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_insurance_id')->constrained('vehicle_insurances')->cascadeOnDelete();
            $table->string('policy_number');
            $table->foreignId('insurance_provider_id')->nullable()->constrained('insurance_providers')->nullOnDelete();
            $table->string('extension_of_policy')->nullable();
            $table->bigInteger('insured_worth')->nullable();
            $table->bigInteger('premium')->nullable();
            $table->date('join_date')->nullable();
            $table->date('expired_date')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('BERJALAN');
            $table->string('payment_evidence')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_insurance_updates');
        Schema::dropIfExists('vehicle_insurances');
    }
};
