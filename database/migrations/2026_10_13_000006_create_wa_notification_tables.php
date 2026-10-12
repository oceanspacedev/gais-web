<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan dan log pengingat WhatsApp jatuh tempo sewa, asuransi, dan cutoff
 * pengajuan (sumo: wa_notification_settings, wa_notification_logs). Log dipakai
 * supaya satu pengingat tidak terkirim dua kali di hari yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->string('target_phones')->nullable();
            $table->string('send_time', 5)->default('08:00');
            $table->boolean('remind_rent')->default(true);
            $table->string('rent_days_before')->default('30,14,7,1');
            $table->boolean('remind_insurance')->default(true);
            $table->string('insurance_days_before')->default('30,14,7,1');
            $table->boolean('remind_request_cutoff')->default(true);
            $table->string('request_cutoff_days_before')->default('3,1');
            $table->timestamps();
        });

        Schema::create('wa_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('notif_type', 30);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('phone', 30);
            $table->date('sent_date');
            $table->string('status', 20);
            $table->text('message_preview')->nullable();
            $table->text('response_payload')->nullable();
            $table->timestamps();

            $table->index(['notif_type', 'reference_id', 'sent_date', 'phone'], 'wa_notification_logs_dedupe_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_notification_logs');
        Schema::dropIfExists('wa_notification_settings');
    }
};
