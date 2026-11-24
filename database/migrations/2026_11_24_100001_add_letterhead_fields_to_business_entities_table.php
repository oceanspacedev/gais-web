<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data kop surat per badan usaha. Semua kolom nullable: record lama tetap bisa
 * disimpan, kelengkapannya cukup ditandai lewat badge di daftar Badan Usaha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_entities', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('legal_form', 32)->nullable()->after('legal_name');
            $table->string('short_name', 64)->nullable()->after('legal_form');
            $table->foreignId('legal_entity_id')->nullable()->after('short_name')
                ->constrained('business_entities')->nullOnDelete();
            $table->text('address')->nullable()->after('letterhead');
            $table->string('city')->nullable()->after('address');
            $table->string('phone')->nullable()->after('city');
            $table->string('email')->nullable()->after('phone');
            $table->string('npwp', 16)->nullable()->after('email');
            $table->string('nib', 13)->nullable()->after('npwp');
            $table->string('legal_registration_number')->nullable()->after('nib');
            $table->string('logo')->nullable()->after('legal_registration_number');
        });
    }

    public function down(): void
    {
        Schema::table('business_entities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('legal_entity_id');
            $table->dropColumn([
                'legal_name', 'legal_form', 'short_name', 'address', 'city', 'phone',
                'email', 'npwp', 'nib', 'legal_registration_number', 'logo',
            ]);
        });
    }
};
