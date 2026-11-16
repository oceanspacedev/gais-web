<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Karyawan login dengan akun pribadi, bukan akun bersama toko/divisi:
 *
 *  - divisions.talenta_organization: nama organisasi di Talenta yang sama dengan
 *    divisi ini, supaya import Talenta bisa mengisi divisi karyawan;
 *  - item_requests.division_id dan problem_reports.division_id: divisi/toko saat
 *    pengajuan atau laporan dibuat. Riwayat toko tetap utuh walau pemohonnya
 *    pindah divisi, dan rekan sedivisi bisa melihatnya. Baris lama diisi dari
 *    divisi pemohonnya (akun toko);
 *  - users.login_disabled_at: akun yang tidak boleh login lagi (akun toko yang
 *    sudah digantikan akun pribadi karyawannya). Riwayatnya tetap ada.
 */
return new class extends Migration
{
    /**
     * Organisasi Talenta yang jelas padanannya dengan satu divisi. Yang lain
     * (mis. RETAIL STORE = banyak toko) diisi GA lewat menu Divisi atau User.
     */
    private const ORGANIZATION_DIVISIONS = [
        'HUMAN CAPITAL' => 'HCM',
        'TOKO MAS AN AN' => 'TKANAN',
        'INTERNAL AUDIT' => 'AUDIT',
        'ONLINE' => 'ONLINE',
        'MARKOM' => 'MARCOMM',
        'MOMOYO' => 'MOMOYO',
        'COMPLETE MULIA' => 'CMULIA',
    ];

    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table): void {
            $table->string('talenta_organization')->nullable()->unique()->after('area_id');
        });

        foreach (self::ORGANIZATION_DIVISIONS as $organization => $division) {
            DB::table('divisions')
                ->where('name', $division)
                ->whereNull('deleted_at')
                ->update(['talenta_organization' => $organization]);
        }

        foreach (['item_requests', 'problem_reports'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('division_id')->nullable()->after('user_id')->constrained('divisions')->nullOnDelete();
            });

            DB::table($name)->whereNull('division_id')->update([
                'division_id' => DB::table('users')->select('division_id')->whereColumn('users.id', "{$name}.user_id"),
            ]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('login_disabled_at')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('login_disabled_at');
        });

        foreach (['item_requests', 'problem_reports'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('division_id');
            });
        }

        Schema::table('divisions', function (Blueprint $table): void {
            $table->dropUnique(['talenta_organization']);
            $table->dropColumn('talenta_organization');
        });
    }
};
