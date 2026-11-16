<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel Asuransi Kendaraan untuk tes yang membangun skema SQLite sendiri: audit
 * kendaraan membaca dan menulis polis asuransi di sini, bukan di atribut aset.
 */
final class VehicleInsuranceSchema
{
    public static function ensure(): void
    {
        if (! Schema::hasTable('insurance_providers')) {
            Schema::create('insurance_providers', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('vehicle_insurances')) {
            Schema::create('vehicle_insurances', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('asset_id')->nullable();
                $table->string('policy_number');
                $table->string('insured_name')->nullable();
                $table->unsignedBigInteger('insurance_provider_id')->nullable();
                $table->unsignedBigInteger('insurance_category_id')->nullable();
                $table->string('extension_of_policy')->nullable();
                $table->bigInteger('insured_worth')->nullable();
                $table->bigInteger('premium')->nullable();
                $table->date('join_date')->nullable();
                $table->date('expired_date')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('business_entity_id')->nullable();
                $table->string('payment_evidence')->nullable();
                $table->string('status', 20)->default('BERJALAN');
                $table->text('notes')->nullable();
                $table->string('reminder_phones')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('vehicle_insurance_updates')) {
            Schema::create('vehicle_insurance_updates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('vehicle_insurance_id');
                $table->string('policy_number');
                $table->unsignedBigInteger('insurance_provider_id')->nullable();
                $table->string('extension_of_policy')->nullable();
                $table->bigInteger('insured_worth')->nullable();
                $table->bigInteger('premium')->nullable();
                $table->date('join_date')->nullable();
                $table->date('expired_date')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('status', 20)->default('BERJALAN');
                $table->string('payment_evidence')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }
}
