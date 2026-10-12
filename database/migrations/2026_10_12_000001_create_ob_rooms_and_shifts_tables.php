<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ruangan dan shift OB menjadi master data. Shift membawa daftar ruangan bawaan,
 * menggantikan Template Tugas OB. Checksheet tetap menyimpan nama ruangan/shift
 * sebagai salinan supaya riwayat tidak berubah saat master diganti namanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ob_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_location_id')->nullable()->constrained('asset_locations')->nullOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ob_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_location_id')->nullable()->constrained('asset_locations')->nullOnDelete();
            $table->string('name');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ob_shift_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ob_shift_id')->constrained('ob_shifts')->cascadeOnDelete();
            $table->foreignId('ob_room_id')->constrained('ob_rooms')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['ob_shift_id', 'ob_room_id']);
        });

        Schema::table('ob_checksheets', function (Blueprint $table) {
            $table->foreignId('ob_room_id')->nullable()->after('room')->constrained('ob_rooms')->nullOnDelete();
            $table->foreignId('ob_shift_id')->nullable()->after('shift_label')->constrained('ob_shifts')->nullOnDelete();
        });

        Schema::dropIfExists('ob_task_template_items');
        Schema::dropIfExists('ob_task_templates');
    }

    public function down(): void
    {
        Schema::create('ob_task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('shift_label')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ob_task_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ob_task_template_id')->constrained('ob_task_templates')->cascadeOnDelete();
            $table->string('room');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('ob_checksheets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ob_shift_id');
            $table->dropConstrainedForeignId('ob_room_id');
        });

        Schema::dropIfExists('ob_shift_rooms');
        Schema::dropIfExists('ob_shifts');
        Schema::dropIfExists('ob_rooms');
    }
};
