<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Enums\WaNotificationType;
use App\Filament\Resources\InsuranceResource;
use App\Filament\Resources\VehicleInsuranceResource;
use App\Filament\Resources\VehicleInsuranceResource\Pages\CreateVehicleInsurance;
use App\Filament\Resources\VehicleInsuranceResource\Pages\ListVehicleInsurances;
use App\Filament\Resources\VehicleInsuranceResource\Pages\ViewVehicleInsurance;
use App\Filament\Resources\VehicleInsuranceResource\RelationManagers\UpdatesRelationManager;
use App\Models\Asset;
use App\Models\AssetAttribute;
use App\Models\BusinessEntity;
use App\Models\Category;
use App\Models\CustomAssetAttribute;
use App\Models\InsuranceProvider;
use App\Models\User;
use App\Models\VehicleInsurance;
use App\Models\WaNotificationLog;
use App\Models\WaNotificationSetting;
use App\Services\ContractReminderService;
use App\Services\ReminderPhoneReplacer;
use App\Services\VehicleInsuranceAuditSync;
use App\Services\WhatsAppGateway;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Asuransi kendaraan dipisah dari asuransi bangunan: menu, izin, pengingat WA,
 * pemindahan atribut aset "Asuransi", dan sinkron audit kendaraan.
 */
class VehicleInsuranceTest extends TestCase
{
    use DatabaseTransactions;

    private const VEHICLE_PERMISSIONS = [
        'ViewAny:VehicleInsurance', 'View:VehicleInsurance', 'Create:VehicleInsurance', 'Update:VehicleInsurance',
    ];

    private Category $car;

    private BusinessEntity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->car = Category::query()->firstOrCreate(['name' => 'MOBIL']);
        $this->entity = BusinessEntity::query()->firstOrCreate(['name' => 'PT Uji Asuransi Kendaraan']);
    }

    public function test_vehicle_insurance_is_created_for_a_vehicle_and_renewed_from_the_panel(): void
    {
        $this->actingAs($this->userWith(self::VEHICLE_PERMISSIONS));
        $asset = $this->vehicle('B 1234 UJI');
        $provider = InsuranceProvider::create(['name' => 'Provider Kendaraan Uji']);

        $this->assertArrayHasKey($asset->id, VehicleInsuranceResource::vehicleOptions('B1234UJI'), 'Kendaraan dicari dengan plat nomor.');

        Livewire::test(CreateVehicleInsurance::class)
            ->fillForm([
                'asset_id' => $asset->id,
                'policy_number' => 'POL-KEND-1',
                'insured_name' => 'PT Uji QQ Pemakai',
                'insurance_provider_id' => $provider->id,
                'extension_of_policy' => 'ALL RISK',
                'insured_worth' => 250000000,
                'premium' => 6500000,
                'join_date' => '2026-01-01',
                'expired_date' => '2026-12-31',
                'status' => ContractStatus::Running->value,
            ])
            ->assertFormSet(['business_entity_id' => $this->entity->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $insurance = VehicleInsurance::query()->where('policy_number', 'POL-KEND-1')->firstOrFail();
        $this->assertSame($asset->id, $insurance->asset_id);
        $this->assertSame($this->entity->id, $insurance->business_entity_id, 'Badan usaha mengikuti aset.');
        $this->assertSame('B 1234 UJI', $insurance->licensePlate());

        Livewire::test(UpdatesRelationManager::class, ['ownerRecord' => $insurance, 'pageClass' => ViewVehicleInsurance::class])
            ->assertOk()
            ->callTableAction('create', data: [
                'policy_number' => 'POL-KEND-2',
                'status' => ContractStatus::Running->value,
                'join_date' => '2027-01-01',
                'expired_date' => '2027-12-31',
                'premium' => 7000000,
            ])
            ->assertHasNoTableActionErrors();

        $insurance->refresh()->load('updates');
        $this->assertSame('2027-12-31', $insurance->effectiveExpiredDate()?->toDateString());
        $this->assertSame('POL-KEND-2', $insurance->effective('policy_number'));
        $this->assertSame(7000000, $insurance->effective('premium'));

        // Data pindahan di database uji ikut tampil, jadi polis ini dicari dulu.
        Livewire::test(ListVehicleInsurances::class)
            ->assertOk()
            ->searchTable('POL-KEND-2')
            ->assertCanSeeTableRecords([$insurance])
            ->assertTableColumnStateSet('license_plate', 'B 1234 UJI', $insurance)
            ->searchTable('B 1234 UJI')
            ->assertCanSeeTableRecords([$insurance])
            ->assertCountTableRecords(1);

        $this->get(VehicleInsuranceResource::getUrl('view', ['record' => $insurance]))
            ->assertOk()
            ->assertSee('B 1234 UJI')
            ->assertSee('Jatuh Tempo Berlaku');
        $this->get(VehicleInsuranceResource::getUrl('edit', ['record' => $insurance]))->assertOk();
    }

    public function test_building_and_vehicle_insurance_have_separate_menus_and_permissions(): void
    {
        $buildingOnly = $this->userWith(['ViewAny:Insurance', 'View:Insurance']);
        $vehicleOnly = $this->userWith(['ViewAny:VehicleInsurance', 'View:VehicleInsurance']);

        $this->actingAs($buildingOnly);
        $this->assertTrue(InsuranceResource::canViewAny());
        $this->assertFalse(VehicleInsuranceResource::canViewAny());
        $this->get(VehicleInsuranceResource::getUrl())->assertForbidden();

        $this->actingAs($vehicleOnly);
        $this->assertFalse(InsuranceResource::canViewAny());
        $this->assertTrue(VehicleInsuranceResource::canViewAny());
        $this->get(VehicleInsuranceResource::getUrl())->assertOk()->assertSee('Asuransi Kendaraan');

        $this->assertSame('Asuransi Bangunan', InsuranceResource::getNavigationLabel());
        $this->assertSame('Asuransi Kendaraan', VehicleInsuranceResource::getNavigationLabel());
        $this->assertSame(InsuranceResource::getNavigationGroup(), VehicleInsuranceResource::getNavigationGroup());
    }

    public function test_roles_managing_insurance_received_the_vehicle_permissions(): void
    {
        $migration = include database_path('migrations/2026_11_16_100003_create_vehicle_insurance_permissions.php');
        $role = Role::findOrCreate('uji_pengelola_asuransi', 'web');
        // Saat migrasi ini jalan nama izin masih format lama; rename menyusul paling akhir.
        $rename = require database_path('migrations/2026_11_16_200001_rename_permissions_to_shield_v4_format.php');
        $rename->down();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role->givePermissionTo(Permission::findOrCreate('view_any_insurance', 'web'), Permission::findOrCreate('update_insurance', 'web'));

        $migration->up();
        $rename->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('ViewAny:VehicleInsurance'));
        $this->assertTrue($role->hasPermissionTo('Update:VehicleInsurance'));
        $this->assertFalse($role->hasPermissionTo('Delete:VehicleInsurance'), 'Hanya izin yang setara dengan izin asuransi lamanya.');
    }

    public function test_reminder_is_sent_for_vehicle_insurance_with_its_own_log_type(): void
    {
        $sent = [];
        $this->app->instance(WhatsAppGateway::class, new class($sent) extends WhatsAppGateway
        {
            /** @param list<array{0: string, 1: string}> $sent */
            public function __construct(private array &$sent)
            {
                parent::__construct();
            }

            public function send($phoneNumber, string $message): bool
            {
                $this->sent[] = [(string) $phoneNumber, $message];

                return true;
            }
        });
        WaNotificationSetting::current()->update([
            'is_enabled' => true,
            'target_phones' => '08111',
            'remind_rent' => false,
            'remind_insurance' => true,
            'insurance_days_before' => '30',
            'remind_request_cutoff' => false,
        ]);
        WaNotificationLog::query()->delete();

        $vehicle = VehicleInsurance::create([
            'asset_id' => $this->vehicle('D 777 REM')->id,
            'policy_number' => 'POL-REM-KEND',
            'insured_name' => 'PT Uji',
            'join_date' => today()->subYear(),
            'expired_date' => today()->addDays(30),
            'reminder_phones' => '08999',
        ]);
        // Asuransi bangunan dengan id yang sama tidak boleh menahan pengingat kendaraan.
        DB::table('insurances')->insert([
            'id' => $vehicle->id + 900000, 'policy_number' => 'POL-REM-BGN', 'insured_name' => 'Gudang', 'status' => 'BERJALAN',
            'join_date' => today()->subYear(), 'expired_date' => today()->addDays(30), 'created_at' => now(), 'updated_at' => now(),
        ]);
        WaNotificationLog::create([
            'notif_type' => WaNotificationType::Insurance, 'reference_id' => $vehicle->id, 'phone' => '08999',
            'sent_date' => today(), 'status' => WaNotificationLog::STATUS_SUCCESS, 'message_preview' => 'bangunan',
        ]);

        app(ContractReminderService::class)->run();

        $vehicleMessages = collect($sent)->filter(fn (array $message): bool => str_contains($message[1], 'POL-REM-KEND'));
        $this->assertSame(['08999'], $vehicleMessages->pluck(0)->values()->all());
        $this->assertStringContainsString('ASURANSI KENDARAAN', $vehicleMessages->first()[1]);
        $this->assertStringContainsString('D 777 REM', $vehicleMessages->first()[1]);
        $this->assertTrue(WaNotificationLog::query()->where('notif_type', WaNotificationType::VehicleInsurance->value)->where('reference_id', $vehicle->id)->exists());
        $this->assertCount(1, collect($sent)->filter(fn (array $message): bool => str_contains($message[1], 'POL-REM-BGN') && str_contains($message[1], 'ASURANSI BANGUNAN')));

        $this->assertSame(['rent' => 0, 'insurance' => 0, 'vehicle_insurance' => 1], app(ReminderPhoneReplacer::class)->replace('08999', '08555'));
        $this->assertSame('08555', $vehicle->refresh()->reminder_phones);
    }

    public function test_old_asset_insurance_attribute_moves_to_vehicle_insurance_without_losing_data(): void
    {
        $attribute = CustomAssetAttribute::query()->firstOrCreate(
            ['name' => 'Asuransi', 'type' => CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY],
            ['is_active' => true, 'category_id' => [$this->car->id]],
        );
        $recipient = User::factory()->create(['whatsapp_number' => '+62 812-0000-7777']);
        $attribute->forceFill(['is_active' => true, 'is_notifiable' => true, 'notification_recipient_user_ids' => [$recipient->id], 'notification_recipient_whatsapp_numbers' => []])->save();
        $provider = InsuranceProvider::create(['name' => 'UJIPROV']);

        $asset = $this->vehicle('B 4321 MIG');
        $value = AssetAttribute::documentValue([
            'expires_at' => '2027-04-30',
            'document_number' => 'POL-MIG-1',
            'notes' => 'UJIPROV INSURANCE | ALL RISK | PT UJI QQ SOPIR | Join: 2026-04-30',
        ]);
        AssetAttribute::create(['asset_id' => $asset->id, 'custom_attribute_id' => $attribute->id, 'attribute_value' => $value]);
        $free = $this->vehicle('B 4322 MIG');
        AssetAttribute::create(['asset_id' => $free->id, 'custom_attribute_id' => $attribute->id, 'attribute_value' => AssetAttribute::documentValue([
            'expires_at' => '2027-05-01', 'document_number' => 'POL-MIG-2', 'notes' => 'catatan bebas',
        ])]);

        $migration = include database_path('migrations/2026_11_16_100002_move_vehicle_insurance_attribute_to_vehicle_insurances.php');
        $migration->up();
        $migration->up();

        $moved = VehicleInsurance::query()->where('asset_id', $asset->id)->sole();
        $this->assertSame('POL-MIG-1', $moved->policy_number);
        $this->assertSame($provider->id, $moved->insurance_provider_id, 'Provider dicocokkan tanpa akhiran INSURANCE.');
        $this->assertSame('ALL RISK', $moved->extension_of_policy);
        $this->assertSame('PT UJI QQ SOPIR', $moved->insured_name);
        $this->assertSame('2026-04-30', $moved->join_date?->toDateString());
        $this->assertSame('2027-04-30', $moved->expired_date?->toDateString());
        $this->assertSame($this->entity->id, $moved->business_entity_id);
        $this->assertContains('6281200007777', WaNotificationSetting::parsePhoneList($moved->reminder_phones));

        $this->assertSame('catatan bebas', VehicleInsurance::query()->where('asset_id', $free->id)->sole()->notes, 'Catatan yang tidak berpola disimpan apa adanya.');

        $attribute->refresh();
        $this->assertFalse($attribute->is_active);
        $this->assertFalse($attribute->is_notifiable);
        $this->assertSame($value, AssetAttribute::query()->where('asset_id', $asset->id)->where('custom_attribute_id', $attribute->id)->value('attribute_value'), 'Nilai atribut lama tidak dihapus.');
    }

    public function test_vehicle_audit_creates_then_renews_the_vehicle_policy(): void
    {
        $asset = $this->vehicle('E 5555 AUD');
        $sync = app(VehicleInsuranceAuditSync::class);
        $payload = ['insurance_expires_at' => '2026-12-01', 'insurance_policy_number' => 'AUD-1', 'insurance_name' => 'Provider Audit Uji', 'insurance_type' => 'TLO', 'insurance_insured' => 'PT AUDIT', 'insurance_join_date' => '2025-12-01'];

        $this->assertTrue($sync->needsSync($asset, $payload));
        $insurance = $sync->sync($asset, $payload);
        $this->assertSame('AUD-1', $insurance->policy_number);
        $this->assertSame('TLO', $insurance->extension_of_policy);
        $this->assertSame('PT AUDIT', $insurance->insured_name);
        $this->assertFalse($sync->needsSync($asset, $payload));

        $renewal = ['insurance_expires_at' => '2027-12-01', 'insurance_policy_number' => 'AUD-2', 'insurance_join_date' => '2026-12-01'] + $payload;
        $sync->sync($asset, $renewal);
        $sync->sync($asset, $renewal);

        $insurance->refresh()->load('updates');
        $this->assertSame(1, VehicleInsurance::query()->where('asset_id', $asset->id)->count());
        $this->assertCount(1, $insurance->updates, 'Nomor polis baru menjadi satu pembaharuan.');
        $this->assertSame('2027-12-01', $insurance->effectiveExpiredDate()?->toDateString());
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions): User
    {
        $user = User::factory()->create(['access_all_business_entities' => true]);
        $user->givePermissionTo(array_map(fn (string $name) => Permission::findOrCreate($name, 'web'), $permissions));

        return $user;
    }

    private function vehicle(string $plate): Asset
    {
        $asset = Asset::create([
            'name' => 'Toyota Avanza '.$plate,
            'business_entity_id' => $this->entity->id,
            'category_id' => $this->car->id,
        ]);

        $plateAttribute = CustomAssetAttribute::query()->firstOrCreate(
            ['name' => config('vehicle-asset-reconciliation.plate_attribute_name', 'Plat Nomor')],
            ['type' => CustomAssetAttribute::TYPE_TEXT, 'is_active' => true, 'category_id' => [$this->car->id]],
        );
        AssetAttribute::create(['asset_id' => $asset->id, 'custom_attribute_id' => $plateAttribute->id, 'attribute_value' => $plate]);

        return $asset;
    }
}
