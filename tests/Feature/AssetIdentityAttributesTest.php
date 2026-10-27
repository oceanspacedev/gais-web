<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\ListAssets;
use App\Models\Asset;
use App\Models\AssetAttribute;
use App\Models\CustomAssetAttribute;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Serial number, IMEI, dan tipe aset hanya ada sebagai atribut kustom ber-key;
 * properti $asset->serial_number dkk. membaca dan menulis atribut itu.
 */
class AssetIdentityAttributesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_identity_columns_are_gone_from_the_assets_table(): void
    {
        foreach (['serial_number', 'imei1', 'imei2', 'type'] as $column) {
            $this->assertFalse(Schema::hasColumn('assets', $column), $column);
        }

        foreach (array_keys(CustomAssetAttribute::IDENTITY_KEYS) as $key) {
            $this->assertSame($key, CustomAssetAttribute::query()->findOrFail(CustomAssetAttribute::idForKey($key))->key);
        }
    }

    public function test_virtual_properties_are_stored_as_keyed_attributes(): void
    {
        $asset = Asset::factory()->create(['serial_number' => '  SN-IDENT-001 ', 'imei1' => '351234567890123', 'imei2' => null, 'type' => 'ThinkPad T14']);

        $this->assertSame('SN-IDENT-001', $this->stored($asset, CustomAssetAttribute::KEY_SERIAL_NUMBER));
        $this->assertSame('351234567890123', $this->stored($asset, CustomAssetAttribute::KEY_IMEI1));
        $this->assertNull($this->stored($asset, CustomAssetAttribute::KEY_IMEI2));

        $fresh = Asset::query()->withIdentity()->findOrFail($asset->id);
        $this->assertSame('SN-IDENT-001', $fresh->serial_number);
        $this->assertSame('ThinkPad T14', $fresh->type);

        // Nilai kosong menghapus barisnya; nilai baru menimpa.
        $fresh->forceFill(['serial_number' => '', 'type' => 'ThinkPad T15'])->save();

        $this->assertNull($this->stored($asset, CustomAssetAttribute::KEY_SERIAL_NUMBER));
        $this->assertSame('ThinkPad T15', $asset->fresh()->type);
        $this->assertSame(1, AssetAttribute::query()->where('asset_id', $asset->id)->where('custom_attribute_id', CustomAssetAttribute::idForKey(CustomAssetAttribute::KEY_TYPE))->count());
    }

    public function test_where_identity_matches_partial_values_across_keys(): void
    {
        $phone = Asset::factory()->create(['serial_number' => null, 'imei1' => null, 'imei2' => '869999000111222']);
        $laptop = Asset::factory()->create(['serial_number' => 'PF-IDENT-77', 'imei1' => null, 'imei2' => null]);

        $imei = Asset::query()->whereIdentity([CustomAssetAttribute::KEY_IMEI1, CustomAssetAttribute::KEY_IMEI2], '9990001')->pluck('id')->all();
        $serial = Asset::query()->where('name', 'tidak-ada')->orWhereIdentity(CustomAssetAttribute::KEY_SERIAL_NUMBER, 'IDENT-77')->pluck('id')->all();

        $this->assertSame([$phone->id], $imei);
        $this->assertSame([$laptop->id], $serial);
    }

    public function test_asset_list_searches_and_shows_serial_numbers_from_attributes(): void
    {
        $admin = User::factory()->create(['access_all_business_entities' => true]);
        $admin->assignRole(Role::findOrCreate(config('filament-shield.super_admin.name', 'super_admin'), 'web'));
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $match = Asset::factory()->create(['serial_number' => 'ZZ-LIST-SEARCH-1']);
        $other = Asset::factory()->create(['serial_number' => 'ZZ-OTHER-2']);

        Livewire::test(ListAssets::class)
            ->searchTable('ZZ-LIST-SEARCH')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other])
            ->assertTableColumnStateSet('serial_number', 'ZZ-LIST-SEARCH-1', $match)
            ->sortTable('serial_number', 'desc')
            ->assertSuccessful();
    }

    public function test_identity_attributes_cannot_be_deleted(): void
    {
        $attribute = CustomAssetAttribute::query()->findOrFail(CustomAssetAttribute::idForKey(CustomAssetAttribute::KEY_IMEI1));

        $this->assertFalse($attribute->delete());
        $this->assertTrue($attribute->fresh()->exists);
    }

    private function stored(Asset $asset, string $key): ?string
    {
        return AssetAttribute::query()
            ->where('asset_id', $asset->id)
            ->where('custom_attribute_id', CustomAssetAttribute::idForKey($key))
            ->value('attribute_value');
    }
}
