<?php

namespace Database\Seeders;

use App\Enums\AssetCondition;
use App\Models\Area;
use App\Models\Asset;
use App\Models\AssetAttribute;
use App\Models\AssetCatalogItem;
use App\Models\AssetLocation;
use App\Models\Brand;
use App\Models\BusinessEntity;
use App\Models\Category;
use App\Models\CustomAssetAttribute;
use App\Models\Division;
use App\Models\ItemRequestScope;
use App\Models\ItemRequestType;
use App\Models\JobTitle;
use App\Models\ObRoom;
use App\Models\ObShift;
use App\Models\ProblemReportCategory;
use App\Models\Product;
use App\Models\UnitType;
use App\Models\User;
use App\Models\Vendor;
use App\Support\AssetReconciliationNormalizer as Normalizer;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data minimal untuk tools/guide-capture (screenshot panduan otomatis).
 * Jangan dijalankan di database asli.
 */
class GuideCaptureSeeder extends Seeder
{
    public function run(): void
    {
        $parent = Category::firstOrCreate(['name' => 'KENDARAAN']);
        Category::firstOrCreate(['name' => 'MOBIL', 'parent_id' => $parent->id]);

        // Pengajuan Barang dan Lapor Gangguan: tiga akun contoh dengan role berbeda.
        $area = Area::firstOrCreate(['name' => 'JAKARTA']);
        $ga = Division::firstOrCreate(['name' => 'GENERAL AFFAIR'], ['area_id' => $area->id]);
        $sales = Division::firstOrCreate(['name' => 'SALES'], ['area_id' => $area->id]);
        ItemRequestScope::firstOrCreate(['division_id' => $ga->id, 'item_request_type_id' => null, 'area_id' => null]);

        $entity = BusinessEntity::where('name', 'CV BERSAMA CS')->value('id');
        $user = fn (string $username, string $name, string $role, array $extra = []): User => tap(User::updateOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'email' => "{$username}@capture.test",
                'email_verified_at' => Carbon::now(),
                'password' => Hash::make('password'),
                'business_entity_id' => $entity,
                ...$extra,
            ],
        ))->syncRoles([$role]);

        $executor = $user('executor', 'Eko Executor', 'executor', ['division_id' => $ga->id]);
        $user('accounting', 'Ani Accounting', 'accounting');
        $user('pemohon', 'Putri Pemohon', 'requester', ['division_id' => $sales->id, 'approver_user_id' => $executor->id]);

        ItemRequestType::firstOrCreate(['name' => 'ATK'], ['pic_division_id' => $ga->id, 'requires_products' => true, 'is_active' => true]);
        ItemRequestType::firstOrCreate(['name' => 'PEMBELIAN INVENTARIS'], ['pic_division_id' => $ga->id, 'requires_products' => true, 'requires_accounting_approval' => true, 'is_active' => true]);

        $rim = UnitType::firstOrCreate(['name' => 'RIM']);
        $pcs = UnitType::firstOrCreate(['name' => 'PCS']);
        Product::firstOrCreate(['name' => 'KERTAS A4 80GR'], ['unit_type_id' => $rim->id, 'price' => 55000]);
        Product::firstOrCreate(['name' => 'PULPEN HITAM'], ['unit_type_id' => $pcs->id, 'price' => 3500]);
        Product::firstOrCreate(['name' => 'KURSI KERJA'], ['unit_type_id' => $pcs->id, 'price' => 850000]);

        foreach (['UMUM', 'LISTRIK', 'AC'] as $name) {
            ProblemReportCategory::firstOrCreate(['name' => $name]);
        }

        // Aset dan BA: staf GA, staf penerima, dan aset stok yang selalu dikembalikan ke Tersedia.
        $job = fn (string $title): ?int => JobTitle::where('title', $title)->value('id');
        $user('ga', 'Gita General Affair', 'general_affair', ['job_title_id' => $job('GENERAL AFFAIR OFFICER')]);
        $user('budi', 'Budi Santoso', 'requester', ['division_id' => $sales->id, 'job_title_id' => $job('MT WAREHOUSE STAFF')]);
        $user('ob', 'Oki Office Boy', 'office_boy', ['job_title_id' => $job('OFFICE BOY')]);

        $asset = fn (string $name, string $category, string $brand): Asset => Asset::withoutGlobalScopes()->updateOrCreate(
            ['name' => $name],
            [
                'category_id' => Category::where('name', $category)->value('id'),
                'brand_id' => Brand::where('name', $brand)->value('id'),
                'business_entity_id' => $entity,
                'purchase_date' => '2026-01-15',
                'condition_status' => AssetCondition::Available,
                'recipient_id' => null,
            ],
        );
        $asset('Laptop Lenovo ThinkPad E14', 'LAPTOP', 'Lenovo');

        // Kendaraan: pilihan "Plat Nomor" di checksheet diambil dari atribut aset bernama persis "Plat Nomor".
        $motor = Category::firstOrCreate(['name' => 'MOTOR', 'parent_id' => $parent->id]);
        $plate = CustomAssetAttribute::updateOrCreate(['name' => 'Plat Nomor'], ['type' => CustomAssetAttribute::TYPE_TEXT, 'category_id' => [$motor->id], 'is_active' => true, 'required' => false]);
        AssetAttribute::updateOrCreate(
            ['asset_id' => $asset('Honda Vario B 4321 KLM', 'MOTOR', 'Honda')->id, 'custom_attribute_id' => $plate->id],
            ['attribute_value' => 'B 4321 KLM'],
        );

        // Pekerjaan Vendor: vendor yang dipilih saat membuat tugas.
        Vendor::firstOrCreate(['name' => 'CV Sumber Teknik'], ['contact_person' => 'Pak Joko', 'last_price' => 1500000]);

        // Rekonsiliasi CSA: satu gudang dengan stok yang qty-nya selalu dikembalikan ke 10.
        $warehouse = AssetLocation::updateOrCreate(['external_code' => 'GDG-PST'], ['name' => 'GUDANG PUSAT']);
        $catalog = AssetCatalogItem::updateOrCreate(
            ['source_system' => 'CSA', 'external_code' => 'ATK-001'],
            ['name' => 'KERTAS THERMAL 80MM', 'normalized_name' => Normalizer::key('KERTAS THERMAL 80MM')],
        );
        Asset::withoutGlobalScopes()->updateOrCreate(
            ['name' => 'KERTAS THERMAL 80MM'],
            [
                'business_entity_id' => $entity,
                'asset_catalog_item_id' => $catalog->id,
                'asset_location_id' => $warehouse->id,
                'qty' => 10,
                'inventory_active' => true,
                'condition_status' => AssetCondition::Available,
                'recipient_id' => null,
            ],
        );

        // OB: ruangan dan shift untuk penugasan.
        $rooms = collect(['Ruang Meeting Finance', 'Toilet Lt. 1', 'Pantry'])
            ->map(fn (string $name): int => ObRoom::firstOrCreate(['name' => $name], ['is_active' => true])->id);
        $shift = ObShift::firstOrCreate(['name' => 'Shift Pagi'], ['start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
        $shift->rooms()->sync($rooms->mapWithKeys(fn (int $id, int $i): array => [$id => ['sort_order' => $i]])->all());
    }
}
