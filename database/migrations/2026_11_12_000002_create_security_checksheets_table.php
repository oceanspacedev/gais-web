<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_checksheets', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('location')->index();
            $table->date('report_date')->index();
            $table->dateTime('checked_at');
            // Jawaban checklist per bagian: {keamanan: {pintu_utama: "aman terkunci"}}.
            $table->json('checklist');
            $table->text('findings')->nullable();
            // Path foto/video bukti di disk public.
            $table->json('attachments')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_checksheets');
    }
};
