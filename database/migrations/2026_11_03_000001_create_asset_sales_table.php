<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Penjualan aset antar badan usaha (OCE-60): satu baris per perpindahan
 * kepemilikan di dalam grup, sehingga aset yang berpindah lebih dari sekali
 * riwayatnya tetap utuh. Berbeda dengan kolom assets.sold_* (aset keluar dari
 * grup, status Dijual, pembeli teks bebas), penjualan internal memindahkan
 * assets.business_entity_id ke pembeli dan aset masuk stok pembeli.
 *
 * Izin Shield asset::sale mengikuti izin aset yang sudah ada: pemegang
 * view_any_asset ikut melihat riwayat, pemegang manage_condition_asset boleh
 * mencatat, mengubah, menghapus, dan memulihkan; hapus permanen hanya
 * super_admin (sama seperti 2026_10_31_000003).
 */
return new class extends Migration
{
    private const SUBJECT = 'asset::sale';

    private const PREFIXES = [
        'view_any', 'view', 'create', 'update', 'delete', 'delete_any',
        'restore', 'restore_any', 'force_delete', 'force_delete_any', 'replicate', 'reorder',
    ];

    /** @var array<string, list<string>> izin aset yang dipegang => awalan izin penjualan yang diberikan */
    private const INHERITED = [
        'view_any_asset' => ['view_any', 'view'],
        'manage_condition_asset' => ['create', 'update', 'delete', 'delete_any', 'restore', 'restore_any'],
    ];

    public function up(): void
    {
        Schema::create('asset_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            // Riwayat tidak boleh hilang diam-diam bila badan usahanya dihapus permanen.
            $table->foreignId('from_business_entity_id')->constrained('business_entities')->restrictOnDelete();
            $table->foreignId('to_business_entity_id')->constrained('business_entities')->restrictOnDelete();
            $table->date('sold_at');
            $table->unsignedBigInteger('sold_price')->default(0);
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['asset_id', 'sold_at']);
            $table->index(['from_business_entity_id', 'sold_at']);
            $table->index(['to_business_entity_id', 'sold_at']);
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PREFIXES as $prefix) {
            Permission::findOrCreate(self::permission($prefix), 'web');
        }

        $roles = Role::query()->where('guard_name', 'web')->with('permissions')->get();
        $usersWithDirectPermissions = User::query()->whereHas('permissions')->with('permissions')->get();

        foreach ([...$roles, ...$usersWithDirectPermissions] as $holder) {
            foreach (self::INHERITED as $assetPermission => $prefixes) {
                if ($holder->permissions->contains('name', $assetPermission)) {
                    $holder->givePermissionTo(array_map(self::permission(...), $prefixes));
                }
            }
        }

        $roles->firstWhere('name', config('filament-shield.super_admin.name', 'super_admin'))
            ?->givePermissionTo(array_map(self::permission(...), self::PREFIXES));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_sales');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', array_map(self::permission(...), self::PREFIXES))
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private static function permission(string $prefix): string
    {
        return $prefix.'_'.self::SUBJECT;
    }
};
