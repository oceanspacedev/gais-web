<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan barang (sumo: requests). Satu pengajuan punya detail barang,
 * empat baris approval (ACCOUNTING, MANAGER, EXECUTOR, ENDUSER) yang diisi
 * per tahap, dan log perubahan pemohon. client_status mengikuti angka lama:
 * 0 menunggu, 1 diterima pemohon, 2 dibatalkan, 3 diproses, 4 selesai executor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_code')->nullable()->unique();
            // Nullable: beberapa pengajuan lama pemohonnya sudah dihapus di sumo.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('item_request_type_id')->constrained('item_request_types')->restrictOnDelete();
            $table->foreignId('business_entity_id')->nullable()->constrained('business_entities')->nullOnDelete();
            $table->dateTime('requested_at');
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->boolean('po_status')->default(false);
            $table->string('po_number')->nullable();
            $table->unsignedTinyInteger('client_status')->default(0);
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->string('request_file')->nullable();
            $table->string('request_file_2')->nullable();
            $table->string('approved_file')->nullable();
            $table->text('notes')->nullable();
            $table->text('user_notes')->nullable();
            $table->text('audit_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('requested_at');
            $table->index(['item_request_type_id', 'client_status']);
        });

        Schema::create('item_request_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_request_id')->constrained('item_requests')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('qty_request', 12, 2)->default(0);
            $table->decimal('qty_remaining', 12, 2)->nullable();
            $table->decimal('qty_approved', 12, 2)->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('item_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_request_id')->constrained('item_requests')->cascadeOnDelete();
            $table->string('approval_type', 20);
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['item_request_id', 'approval_type']);
        });

        Schema::create('item_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_request_id')->constrained('item_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('activity');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_request_logs');
        Schema::dropIfExists('item_request_approvals');
        Schema::dropIfExists('item_request_details');
        Schema::dropIfExists('item_requests');
    }
};
