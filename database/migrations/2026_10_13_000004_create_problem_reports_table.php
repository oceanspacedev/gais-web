<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan gangguan (sumo: problem_report). status PENDING/CLOSED/CANCELLED
 * diisi executor; client_status 0/1 adalah konfirmasi pelapor, diisi otomatis
 * 24 jam setelah ditutup bila pelapor tidak merespons.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('problem_reports', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('problem_report_category_id')->nullable()->constrained('problem_report_categories')->nullOnDelete();
            $table->foreignId('business_entity_id')->nullable()->constrained('business_entities')->nullOnDelete();
            $table->dateTime('reported_at');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('photo_before')->nullable();
            $table->string('photo_after')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->date('scheduled_at')->nullable();
            $table->unsignedTinyInteger('client_status')->default(0);
            $table->text('review_user')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->text('result_desc')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'client_status']);
            $table->index('reported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('problem_reports');
    }
};
