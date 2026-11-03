<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Exceptions\AssetSaleException;
use App\Filament\Resources\AssetResource;
use App\Filament\Resources\AssetResource\Pages\ViewAsset;
use App\Filament\Resources\AssetSaleResource;
use App\Filament\Resources\AssetSaleResource\Pages\ListAssetSales;
use App\Models\Asset;
use App\Models\AssetSale;
use App\Models\BusinessEntity;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\AssertsVisibilityContract;
use Tests\TestCase;

/**
 * Penjualan aset antar badan usaha (OCE-60): kepemilikan pindah ke pembeli,
 * riwayat utuh walau berpindah berkali-kali, pembeli harus beda dari pemilik,
 * aset harus di stok, dan pembatasan akses badan usaha untuk penjual maupun
 * pembeli.
 */
class AssetSaleTest extends TestCase
{
    use AssertsVisibilityContract, DatabaseTransactions;

    private const PERMISSIONS = [
        'view_any_asset', 'view_asset',
        'view_any_asset::sale', 'view_asset::sale', 'create_asset::sale', 'update_asset::sale', 'delete_asset::sale', 'restore_asset::sale',
    ];

    private BusinessEntity $alpha;

    private BusinessEntity $beta;

    private BusinessEntity $gamma;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->alpha = BusinessEntity::create(['name' => '__as_alpha__']);
        $this->beta = BusinessEntity::create(['name' => '__as_beta__']);
        $this->gamma = BusinessEntity::create(['name' => '__as_gamma__']);
    }

    public function test_selling_moves_ownership_to_the_buyer_and_keeps_the_full_history(): void
    {
        $actor = $this->userWith(self::PERMISSIONS, unrestricted: true);
        $asset = $this->stockAsset($this->alpha);

        $first = AssetSale::record($asset, [
            'to_business_entity_id' => $this->beta->id,
            'sold_at' => '2026-01-10',
            'sold_price' => 1500000,
            'notes' => ' BAST 01 ',
        ], $actor);

        // Instance pemanggil langsung mencerminkan pemilik baru.
        $this->assertSame($this->beta->id, $asset->business_entity_id);

        $asset->refresh();
        $this->assertSame($this->beta->id, $asset->business_entity_id);
        $this->assertSame($this->beta->id, $asset->recipient_business_entity_id, 'Aset masuk stok pembeli.');
        $this->assertNull($asset->recipient_id);
        $this->assertSame(AssetCondition::Available, $asset->condition_status);
        $this->assertTrue($asset->checkValidRecipient());

        $this->assertSame($this->alpha->id, $first->from_business_entity_id);
        $this->assertSame($this->beta->id, $first->to_business_entity_id);
        $this->assertSame(1500000, $first->sold_price);
        $this->assertSame('BAST 01', $first->notes);
        $this->assertSame($actor->id, $first->created_by);

        // Penjualan internal tidak menyentuh kolom penjualan keluar grup (status Dijual).
        $this->assertNull($asset->sold_at);
        $this->assertNull($asset->sold_to);
        $this->assertNull($asset->sold_price);

        $second = AssetSale::record($asset, ['to_business_entity_id' => $this->gamma->id, 'sold_at' => '2026-02-20', 'sold_price' => 0], $actor);

        $asset->refresh();
        $this->assertSame($this->gamma->id, $asset->business_entity_id);
        $this->assertSame([$first->id, $second->id], $asset->sales->modelKeys());
        $this->assertSame(
            [[$this->alpha->id, $this->beta->id], [$this->beta->id, $this->gamma->id]],
            $asset->sales->map(fn (AssetSale $sale): array => [$sale->from_business_entity_id, $sale->to_business_entity_id])->all(),
        );

        // Pemilik lama tidak lagi melihat aset sebagai aset aktif; pemilik baru melihatnya.
        $this->assertFalse(AssetResource::scopeToViewer(Asset::query(), $this->userWith(self::PERMISSIONS, $this->alpha))->whereKey($asset->id)->exists());
        $this->assertFalse(AssetResource::scopeToViewer(Asset::query(), $this->userWith(self::PERMISSIONS, $this->beta))->whereKey($asset->id)->exists());
        $this->assertTrue(AssetResource::scopeToViewer(Asset::query(), $this->userWith(self::PERMISSIONS, $this->gamma))->whereKey($asset->id)->exists());
    }

    public function test_buyer_must_differ_from_the_current_owner(): void
    {
        $actor = $this->userWith(self::PERMISSIONS, unrestricted: true);
        $asset = $this->stockAsset($this->alpha);

        foreach ([$this->alpha->id, null, ''] as $buyer) {
            try {
                AssetSale::record($asset, ['to_business_entity_id' => $buyer, 'sold_at' => '2026-01-10'], $actor);
                $this->fail('Pembeli yang sama dengan pemilik atau kosong harus ditolak.');
            } catch (AssetSaleException $exception) {
                $this->assertStringContainsString('harus berbeda dari pemilik', $exception->getMessage());
            }
        }

        $this->assertSame(0, AssetSale::query()->where('asset_id', $asset->id)->count());
        $this->assertSame($this->alpha->id, $asset->fresh()->business_entity_id);
    }

    public function test_asset_must_be_in_stock_before_it_is_sold(): void
    {
        $actor = $this->userWith(self::PERMISSIONS, unrestricted: true);
        $holder = User::factory()->create(['business_entity_id' => $this->alpha->id, 'name' => '__as_holder__']);
        $held = Asset::create([
            'name' => '__as_held__',
            'business_entity_id' => $this->alpha->id,
            'recipient_business_entity_id' => $this->alpha->id,
            'recipient_id' => $holder->id,
            'condition_status' => AssetCondition::Transferred,
        ]);

        try {
            AssetSale::record($held, ['to_business_entity_id' => $this->beta->id, 'sold_at' => '2026-01-10'], $actor);
            $this->fail('Aset yang masih dipegang tidak boleh dijual.');
        } catch (AssetSaleException $exception) {
            $this->assertStringContainsString('BA Pengembalian', $exception->getMessage());
            $this->assertStringContainsString('__as_holder__', $exception->getMessage());
        }

        $damaged = Asset::create(['name' => '__as_damaged__', 'business_entity_id' => $this->alpha->id, 'condition_status' => AssetCondition::Damaged]);

        try {
            AssetSale::record($damaged, ['to_business_entity_id' => $this->beta->id, 'sold_at' => '2026-01-10'], $actor);
            $this->fail('Aset rusak tidak boleh dijual.');
        } catch (AssetSaleException $exception) {
            $this->assertStringContainsString('Rusak', $exception->getMessage());
        }

        $trashed = $this->stockAsset($this->alpha);
        $trashed->delete();

        try {
            AssetSale::record($trashed, ['to_business_entity_id' => $this->beta->id, 'sold_at' => '2026-01-10'], $actor);
            $this->fail('Aset di Sampah tidak boleh dijual.');
        } catch (AssetSaleException $exception) {
            $this->assertStringContainsString('Sampah', $exception->getMessage());
        }

        $this->assertSame(0, AssetSale::query()->whereIn('asset_id', [$held->id, $damaged->id, $trashed->id])->count());
        $this->assertSame($this->alpha->id, $held->fresh()->business_entity_id);
        $this->assertSame($holder->id, $held->fresh()->recipient_id);
    }

    public function test_only_users_with_access_to_seller_and_buyer_entities_can_sell(): void
    {
        $asset = $this->stockAsset($this->alpha);

        try {
            AssetSale::record($asset, ['to_business_entity_id' => $this->beta->id, 'sold_at' => '2026-01-10'], $this->userWith(self::PERMISSIONS, $this->beta));
            $this->fail('User tanpa akses ke badan usaha penjual tidak boleh menjual.');
        } catch (AssetSaleException $exception) {
            $this->assertStringContainsString('pemilik aset berada di luar akses', $exception->getMessage());
        }

        $alphaUser = $this->userWith(self::PERMISSIONS, $this->alpha);

        try {
            AssetSale::record($asset, ['to_business_entity_id' => $this->gamma->id, 'sold_at' => '2026-01-10'], $alphaUser);
            $this->fail('Pembeli di luar akses penjual tidak boleh dipilih.');
        } catch (AssetSaleException $exception) {
            $this->assertStringContainsString($this->gamma->name, $exception->getMessage());
        }

        $this->assertSame(0, AssetSale::query()->where('asset_id', $asset->id)->count());

        $alphaUser->accessibleBusinessEntities()->attach($this->beta);
        $alphaUser = $alphaUser->fresh();

        $sale = AssetSale::record($asset, ['to_business_entity_id' => $this->beta->id, 'sold_at' => '2026-01-10'], $alphaUser);

        $this->assertSame($this->beta->id, $asset->fresh()->business_entity_id);
        $this->assertSame($alphaUser->id, $sale->created_by);
    }

    public function test_sales_are_visible_to_seller_and_buyer_but_not_outsiders(): void
    {
        $actor = $this->userWith(self::PERMISSIONS, unrestricted: true);
        $delta = BusinessEntity::create(['name' => '__as_delta__']);

        $sale = AssetSale::record($this->stockAsset($this->alpha), ['to_business_entity_id' => $this->beta->id, 'sold_at' => '2026-01-10'], $actor);
        $trashed = AssetSale::record($this->stockAsset($this->alpha), ['to_business_entity_id' => $this->beta->id, 'sold_at' => '2026-01-11'], $actor);
        $trashed->delete();
        $unrelated = AssetSale::record($this->stockAsset($this->gamma), ['to_business_entity_id' => $delta->id, 'sold_at' => '2026-01-12'], $actor);

        $ids = [$sale->id, $trashed->id, $unrelated->id];
        $viewers = [
            'seller' => $this->userWith(self::PERMISSIONS, $this->alpha),
            'buyer' => $this->userWith(self::PERMISSIONS, $this->beta),
            'outsider' => $this->userWith(self::PERMISSIONS, BusinessEntity::create(['name' => '__as_outsider__'])),
            'unrestricted' => $actor,
        ];

        foreach (['seller', 'buyer'] as $party) {
            $this->assertSame([$sale->id], AssetSaleResource::scopeToViewer(AssetSale::query()->whereKey($ids), $viewers[$party])->pluck('id')->all(), $party);
            $this->assertTrue($viewers[$party]->can('view', $sale), $party);
            $this->assertTrue($viewers[$party]->can('view', $trashed), "{$party}: record di Sampah dinilai dengan aturan yang sama.");
            $this->assertFalse($viewers[$party]->can('update', $trashed), "{$party}: record di Sampah hanya-baca.");
            $this->assertTrue($viewers[$party]->can('restore', $trashed), $party);
        }

        $this->assertSame([], AssetSaleResource::scopeToViewer(AssetSale::query()->whereKey($ids), $viewers['outsider'])->pluck('id')->all());
        $this->assertFalse($viewers['outsider']->can('view', $sale));
        $this->assertEqualsCanonicalizing([$sale->id, $unrelated->id], AssetSaleResource::scopeToViewer(AssetSale::query()->whereKey($ids), $actor)->pluck('id')->all());

        $this->assertQueryAndPolicyAgree(AssetSale::class, $viewers, $ids, ['eager' => ['fromBusinessEntity', 'toBusinessEntity'], 'lazy' => []]);
    }

    public function test_asset_link_in_the_sales_table_is_hidden_from_viewers_who_cannot_open_the_asset(): void
    {
        $actor = $this->userWith(self::PERMISSIONS, unrestricted: true);
        $asset = $this->stockAsset($this->alpha);
        $sale = AssetSale::record($asset, ['to_business_entity_id' => $this->beta->id, 'sold_at' => '2026-01-10'], $actor);
        $sale->load(['asset', 'fromBusinessEntity', 'toBusinessEntity']);
        $column = AssetSaleResource::table(Table::make(Mockery::mock(HasTable::class)))->getColumn('asset.name');

        $this->actingAs($this->userWith(self::PERMISSIONS, $this->alpha));
        $this->assertNull($column->record($sale)->getUrl(), 'Pemilik lama melihat baris penjualan tanpa tautan ke aset yang sudah dilepas.');

        $this->actingAs($this->userWith(self::PERMISSIONS, $this->beta));
        $this->assertStringContainsString('/admin/assets/'.$asset->id, (string) $column->record($sale)->getUrl());
    }

    public function test_asset_detail_page_sells_to_another_entity(): void
    {
        $actor = $this->userWith(self::PERMISSIONS, unrestricted: true);
        $asset = $this->stockAsset($this->alpha);
        $this->actingAs($actor);

        Livewire::test(ViewAsset::class, ['record' => $asset->getRouteKey()])
            ->assertActionVisible('sellToEntity')
            ->callAction('sellToEntity', ['to_business_entity_id' => $this->beta->id, 'sold_at' => '2026-03-01', 'sold_price' => 250000])
            ->assertHasNoActionErrors();

        $asset->refresh();
        $this->assertSame($this->beta->id, $asset->business_entity_id);
        $this->assertSame(AssetCondition::Available, $asset->condition_status);
        $this->assertSame([$this->alpha->id], $asset->sales->pluck('from_business_entity_id')->all());
        $this->assertSame($actor->id, $asset->sales->first()->created_by);
    }

    public function test_asset_detail_page_hides_the_action_without_permission_or_stock(): void
    {
        $asset = $this->stockAsset($this->alpha);

        $this->actingAs($this->userWith(['view_any_asset', 'view_asset'], unrestricted: true));
        Livewire::test(ViewAsset::class, ['record' => $asset->getRouteKey()])->assertActionHidden('sellToEntity');

        $holder = User::factory()->create(['business_entity_id' => $this->alpha->id]);
        $held = Asset::create([
            'name' => '__as_held_ui__',
            'business_entity_id' => $this->alpha->id,
            'recipient_business_entity_id' => $this->alpha->id,
            'recipient_id' => $holder->id,
            'condition_status' => AssetCondition::Transferred,
        ]);

        $this->actingAs($this->userWith(self::PERMISSIONS, unrestricted: true));
        Livewire::test(ViewAsset::class, ['record' => $held->getRouteKey()])->assertActionHidden('sellToEntity');
        Livewire::test(ViewAsset::class, ['record' => $asset->getRouteKey()])->assertActionVisible('sellToEntity');
    }

    public function test_sales_list_records_a_sale_from_the_asset_picker(): void
    {
        $actor = $this->userWith(self::PERMISSIONS, unrestricted: true);
        $asset = $this->stockAsset($this->alpha);
        $this->actingAs($actor);

        $this->assertArrayHasKey($asset->id, AssetSaleResource::sellableAssetOptions());
        $this->assertArrayNotHasKey($this->alpha->id, AssetSaleResource::buyerOptions($this->alpha->id));

        Livewire::test(ListAssetSales::class)
            ->assertOk()
            ->callAction('record', ['asset_id' => $asset->id, 'to_business_entity_id' => $this->alpha->id, 'sold_at' => '2026-03-01', 'sold_price' => 0])
            ->assertHasActionErrors(['to_business_entity_id']);

        $this->assertSame(0, AssetSale::query()->where('asset_id', $asset->id)->count());

        Livewire::test(ListAssetSales::class)
            ->callAction('record', ['asset_id' => $asset->id, 'to_business_entity_id' => $this->beta->id, 'sold_at' => '2026-03-01', 'sold_price' => 100])
            ->assertHasNoActionErrors();

        $this->assertSame($this->beta->id, $asset->fresh()->business_entity_id);

        $sales = AssetSale::query()->where('asset_id', $asset->id)->get();
        $this->assertCount(1, $sales);
        // Aset masuk stok beta, jadi tetap bisa dijual lagi, kini atas nama beta.
        $this->assertStringContainsString($this->beta->name, AssetSaleResource::sellableAssetOptions()[$asset->id] ?? '');

        Livewire::test(ListAssetSales::class)->assertCanSeeTableRecords($sales);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions, ?BusinessEntity $entity = null, bool $unrestricted = false): User
    {
        $user = User::factory()->create([
            'business_entity_id' => $entity?->id,
            'access_all_business_entities' => $unrestricted,
        ]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user->fresh();
    }

    private function stockAsset(BusinessEntity $owner): Asset
    {
        return Asset::create([
            'name' => '__as_asset_'.uniqid().'__',
            'business_entity_id' => $owner->id,
            'recipient_business_entity_id' => $owner->id,
            'condition_status' => AssetCondition::Available,
        ]);
    }
}
