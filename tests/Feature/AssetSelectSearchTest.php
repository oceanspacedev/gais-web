<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Filament\Resources\AssetSaleResource;
use App\Models\Asset;
use App\Models\AssetLocation;
use App\Models\Brand;
use App\Models\BusinessEntity;
use App\Models\CustomAssetAttribute;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pilihan aset (Select) dicari seperti kotak cari tabel Aset, bukan hanya nama.
 */
class AssetSelectSearchTest extends TestCase
{
    use DatabaseTransactions;

    private Asset $car;

    private Asset $laptop;

    protected function setUp(): void
    {
        parent::setUp();

        $plate = CustomAssetAttribute::create(['name' => 'Plat Uji Select', 'type' => CustomAssetAttribute::TYPE_TEXT, 'is_active' => true, 'category_id' => []]);
        $entity = BusinessEntity::firstOrCreate(['name' => 'PT Uji Select']);

        $this->car = Asset::factory()->create([
            'name' => 'Toyota Avanza Uji',
            // Nilai tetap: tipe/serial acak dari factory bisa ikut memuat kata cari.
            'type' => 'BARANG ELEKTRONIK',
            'serial_number' => 'SN-UJI-MOBIL',
            'imei1' => null,
            'imei2' => null,
            'business_entity_id' => $entity->id,
            'brand_id' => Brand::create(['name' => 'Merek Uji Toyota'])->id,
            'asset_location_id' => AssetLocation::create(['name' => 'Gudang Uji Cikarang'])->id,
        ]);
        $this->car->attributes()->create(['custom_attribute_id' => $plate->id, 'attribute_value' => 'B 4321 UJI']);

        $this->laptop = Asset::factory()->create(['name' => 'Laptop Uji Select', 'business_entity_id' => $entity->id, 'serial_number' => 'SN-UJI-777']);
    }

    public function test_search_matches_name_brand_location_attributes_and_id(): void
    {
        $search = fn (string $term): array => Asset::query()->whereSearch($term)->pluck('id')->all();

        $this->assertContains($this->car->id, $search('avanza'));
        $this->assertContains($this->car->id, $search('Merek Uji Toyota'));
        $this->assertContains($this->car->id, $search('cikarang'));
        $this->assertContains($this->car->id, $search('b4321uji'));
        $this->assertContains($this->car->id, $search('#'.$this->car->id));
        $this->assertContains($this->laptop->id, $search('sn-uji-777'));
    }

    public function test_every_word_must_match_but_may_match_different_fields(): void
    {
        $results = Asset::query()->whereSearch('avanza B4321UJI')->pluck('id')->all();

        $this->assertSame([$this->car->id], $results);
        $this->assertSame([], Asset::query()->whereSearch('avanza SN-UJI-777')->pluck('id')->all());
    }

    public function test_option_label_shows_the_attribute_that_matched(): void
    {
        $options = Asset::searchOptions(Asset::query(), 'b 4321');

        $this->assertStringContainsString('Plat Uji Select: B 4321 UJI', $options[$this->car->id]);
        $this->assertStringContainsString('PT Uji Select', $options[$this->car->id]);

        $label = Asset::searchOptions(Asset::query(), 'Laptop Uji Select')[$this->laptop->id];
        $this->assertStringContainsString('SN-UJI-777', $label);
    }

    public function test_sale_asset_select_finds_stock_by_plate(): void
    {
        $admin = User::factory()->create(['access_all_business_entities' => true]);
        $admin->assignRole(Role::findOrCreate(config('filament-shield.super_admin.name', 'super_admin'), 'web'));
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->car->update(['condition_status' => AssetCondition::Available->value, 'recipient_id' => null]);

        $this->assertArrayHasKey($this->car->id, AssetSaleResource::sellableAssetOptions('B4321'));
        $this->assertArrayNotHasKey($this->laptop->id, AssetSaleResource::sellableAssetOptions('B4321'));
    }
}
