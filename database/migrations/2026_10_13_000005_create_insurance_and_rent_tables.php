<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polis asuransi dan perjanjian sewa (sumo: insurances, rents) beserta riwayat
 * pembaharuan dan perpanjangannya. Status BERJALAN, PEMBAHARUAN, TUTUP, REFUND.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurances', function (Blueprint $table) {
            $table->id();
            $table->string('policy_number');
            $table->string('insured_name');
            $table->string('warehouse_code')->nullable();
            $table->string('insured_address')->nullable();
            $table->string('insured_detail')->nullable();
            $table->string('risk_address')->nullable();
            $table->foreignId('stock_provider_id')->nullable()->constrained('insurance_providers')->nullOnDelete();
            $table->foreignId('building_provider_id')->nullable()->constrained('insurance_providers')->nullOnDelete();
            $table->bigInteger('stock_worth')->nullable();
            $table->bigInteger('building_worth')->nullable();
            $table->bigInteger('actual_stock_worth')->nullable();
            $table->bigInteger('stock_premium')->nullable();
            $table->bigInteger('building_premium')->nullable();
            $table->foreignId('insurance_category_id')->nullable()->constrained('insurance_categories')->nullOnDelete();
            $table->foreignId('insurance_scope_id')->nullable()->constrained('insurance_scopes')->nullOnDelete();
            $table->string('extension_of_policy')->nullable();
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

        Schema::create('insurance_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_id')->constrained('insurances')->cascadeOnDelete();
            $table->string('policy_number');
            $table->foreignId('stock_provider_id')->nullable()->constrained('insurance_providers')->nullOnDelete();
            $table->foreignId('building_provider_id')->nullable()->constrained('insurance_providers')->nullOnDelete();
            $table->bigInteger('stock_worth')->nullable();
            $table->bigInteger('building_worth')->nullable();
            $table->bigInteger('actual_stock_worth')->nullable();
            $table->bigInteger('stock_premium')->nullable();
            $table->bigInteger('building_premium')->nullable();
            $table->string('extension_of_policy')->nullable();
            $table->date('join_date')->nullable();
            $table->date('expired_date')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('BERJALAN');
            $table->string('payment_evidence')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('rents', function (Blueprint $table) {
            $table->id();
            $table->string('rent_code');
            $table->string('rented_address')->nullable();
            $table->string('rented_detail');
            $table->string('first_party');
            $table->string('second_party');
            $table->bigInteger('rent_per_year')->nullable();
            $table->bigInteger('cvcs_fund')->nullable();
            $table->bigInteger('online_fund')->nullable();
            $table->date('join_date')->nullable();
            $table->date('expired_date')->nullable();
            $table->string('deduction_evidence')->nullable();
            $table->string('deduction_evidence_file')->nullable();
            $table->string('document')->nullable();
            $table->string('document_file')->nullable();
            $table->string('payment_evidence_file')->nullable();
            $table->string('status', 20)->default('BERJALAN');
            $table->string('month_before_reminder')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('business_entity_id')->nullable()->constrained('business_entities')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('reminder_phones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('rent_code');
            $table->index(['status', 'expired_date']);
        });

        Schema::create('rent_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rent_id')->constrained('rents')->cascadeOnDelete();
            $table->string('rent_code');
            $table->string('first_party');
            $table->string('second_party');
            $table->bigInteger('rent_per_year')->nullable();
            $table->bigInteger('cvcs_fund')->nullable();
            $table->bigInteger('online_fund')->nullable();
            $table->date('join_date')->nullable();
            $table->date('expired_date')->nullable();
            $table->string('deduction_evidence')->nullable();
            $table->string('deduction_evidence_file')->nullable();
            $table->string('document')->nullable();
            $table->string('document_file')->nullable();
            $table->string('payment_evidence_file')->nullable();
            $table->string('status', 20)->default('BERJALAN');
            $table->string('month_before_reminder')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_updates');
        Schema::dropIfExists('rents');
        Schema::dropIfExists('insurance_updates');
        Schema::dropIfExists('insurances');
    }
};
