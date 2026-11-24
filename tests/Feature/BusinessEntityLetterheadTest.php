<?php

namespace Tests\Feature;

use App\Filament\Resources\BusinessEntityResource;
use App\Filament\Resources\BusinessEntityResource\Pages\ManageBusinessEntities;
use App\Models\BusinessEntity;
use App\Models\User;
use App\Support\Letterhead;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Kop surat dibuat dari data badan usaha: kop gambar (override) menang, brand/outlet
 * ikut PT pemiliknya, field kosong tidak dicetak, dan file gambar yang hilang tidak
 * menggagalkan PDF.
 */
class BusinessEntityLetterheadTest extends TestCase
{
    use DatabaseTransactions;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_full_data_renders_every_filled_field(): void
    {
        Storage::disk('public')->put('kopsurat/logo/__klt__.png', base64_decode(self::PNG));

        $entity = BusinessEntity::create([
            'name' => '__klt_full__',
            'legal_name' => 'PT __KLT RESMI__',
            'address' => "Jl. __Klt__ No. 1\nCirebon 45153",
            'phone' => '0231-000',
            'email' => 'kop@example.test',
            'npwp' => '0617648514455000',
            'nib' => '1234567890123',
            'logo' => 'kopsurat/logo/__klt__.png',
        ]);

        $html = $this->renderKop($entity);

        $this->assertStringContainsString('PT __KLT RESMI__', $html);
        $this->assertStringContainsString('Jl. __Klt__ No. 1<br />', $html);
        $this->assertStringContainsString('Telp. 0231-000 | kop@example.test', $html);
        // Kop ringkas: NPWP/NIB hanya disimpan sebagai data.
        $this->assertStringNotContainsString('0617648514455000', $html);
        $this->assertStringNotContainsString('1234567890123', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertSame(BusinessEntity::LETTERHEAD_COMPLETE, $entity->letterheadStatus());
        $this->assertFalse(BusinessEntity::letterheadIncomplete()->whereKey($entity->id)->exists());
    }

    public function test_entity_without_data_gets_a_text_kop_with_its_own_name(): void
    {
        $entity = BusinessEntity::create(['name' => '__klt_kosong__']);

        $html = $this->renderKop($entity);

        $this->assertStringContainsString('__klt_kosong__', $html);
        $this->assertStringNotContainsString('Telp.', $html);
        $this->assertStringNotContainsString('NPWP', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('cvcs_kop', $html);
        $this->assertSame(BusinessEntity::LETTERHEAD_INCOMPLETE, $entity->letterheadStatus());
        $this->assertTrue(BusinessEntity::letterheadIncomplete()->whereKey($entity->id)->exists());
    }

    public function test_image_override_wins_over_data(): void
    {
        Storage::disk('public')->put('kopsurat/__klt_override__.png', base64_decode(self::PNG));

        $entity = BusinessEntity::create([
            'name' => '__klt_override__',
            'legal_name' => 'PT __KLT TIDAK DICETAK__',
            'letterhead' => 'kopsurat/__klt_override__.png',
        ]);

        $letterhead = Letterhead::for($entity);
        $html = $this->renderKop($entity);

        $this->assertTrue($letterhead->isImage());
        $this->assertStringContainsString('class="kop-image"', $html);
        $this->assertStringNotContainsString('PT __KLT TIDAK DICETAK__', $html);
        $this->assertSame(BusinessEntity::LETTERHEAD_IMAGE, $entity->letterheadStatus());
    }

    public function test_missing_image_files_fall_back_to_the_text_kop(): void
    {
        $this->withoutExceptionHandling();

        $entity = BusinessEntity::create([
            'name' => '__klt_hilang__',
            'legal_name' => 'CV __KLT HILANG__',
            'letterhead' => 'kopsurat/__tidak_ada__.png',
            'logo' => 'kopsurat/logo/__tidak_ada__.png',
        ]);

        $html = $this->renderKop($entity);

        $this->assertStringContainsString('CV __KLT HILANG__', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_outlet_uses_the_kop_of_its_owning_company(): void
    {
        $company = BusinessEntity::create([
            'name' => '__klt_pt__',
            'legal_name' => 'PT __KLT PEMILIK__',
            'address' => 'Jl. __Pemilik__',
        ]);
        $outlet = BusinessEntity::create([
            'name' => '__klt_outlet__',
            'address' => 'Jl. __Outlet__',
            'legal_entity_id' => $company->id,
        ]);

        $html = $this->renderKop($outlet);

        $this->assertStringContainsString('PT __KLT PEMILIK__', $html);
        $this->assertStringContainsString('Jl. __Pemilik__', $html);
        $this->assertStringNotContainsString('Jl. __Outlet__', $html);
        $this->assertStringContainsString('Outlet: __klt_outlet__', $html);
        $this->assertSame(BusinessEntity::LETTERHEAD_PARENT, $outlet->letterheadStatus());
        $this->assertFalse(BusinessEntity::letterheadIncomplete()->whereKey($outlet->id)->exists());
    }

    public function test_identifier_inputs_are_normalized(): void
    {
        $this->assertSame('0617648514455000', BusinessEntityResource::normalizeNpwp('61.764.851.4-455.000'));
        $this->assertSame('0617648514455000', BusinessEntityResource::normalizeNpwp('0617648514455000'));
        $this->assertNull(BusinessEntityResource::normalizeNpwp('  '));
        $this->assertSame('1234567890123', BusinessEntityResource::digitsOnly('1234 5678 90123'));
    }

    public function test_letterhead_preview_requires_view_permission(): void
    {
        $entity = BusinessEntity::create(['name' => '__klt_preview__', 'legal_name' => 'PT __KLT PREVIEW__']);

        $this->actingAs(User::factory()->create())
            ->get(route('business-entity.letterhead-preview', $entity))
            ->assertForbidden();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::findOrCreate('View:BusinessEntity', 'web'));

        $this->actingAs($viewer)
            ->get(route('business-entity.letterhead-preview', $entity))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_form_saves_and_normalizes_identifiers(): void
    {
        $this->actingAsManager();

        Livewire::test(ManageBusinessEntities::class)
            ->callAction('create', data: [
                'name' => '__klt_form__',
                'format' => '__KLT/',
                'color' => 'red',
                'legal_name' => '  PT __KLT FORM__  ',
                'npwp' => '61.764.851.4-455.000',
                'nib' => '1234 5678 90123',
            ])
            ->assertHasNoActionErrors();

        $entity = BusinessEntity::where('name', '__klt_form__')->firstOrFail();
        $this->assertSame('PT __KLT FORM__', $entity->legal_name);
        $this->assertSame('0617648514455000', $entity->npwp);
        $this->assertSame('1234567890123', $entity->nib);

        Livewire::test(ManageBusinessEntities::class)
            ->callTableAction('edit', $entity, data: ['npwp' => '123', 'address' => "Baris 1\nBaris 2\nBaris 3"])
            ->assertHasTableActionErrors(['npwp', 'address']);
    }

    public function test_a_company_with_outlets_cannot_get_an_owner_or_be_deleted(): void
    {
        $this->actingAsManager();

        $company = BusinessEntity::create(['name' => '__klt_pt_rantai__', 'format' => 'X/', 'color' => 'red']);
        BusinessEntity::create(['name' => '__klt_outlet_rantai__', 'legal_entity_id' => $company->id]);

        Livewire::test(ManageBusinessEntities::class)
            ->mountTableAction('edit', $company)
            ->assertFormFieldIsDisabled('legal_entity_id');

        Livewire::test(ManageBusinessEntities::class)
            ->assertTableActionDisabled('delete', $company);
    }

    public function test_whitespace_counts_as_blank_everywhere(): void
    {
        Storage::disk('public')->put('kopsurat/logo/__klt_ws__.png', base64_decode(self::PNG));

        $entity = BusinessEntity::create([
            'name' => '__klt_ws__',
            'legal_name' => '   ',
            'address' => 'Jl. __Ws__',
            'phone' => '021',
            'logo' => 'kopsurat/logo/__klt_ws__.png',
        ]);

        $this->assertStringContainsString('__klt_ws__', $this->renderKop($entity));
        $this->assertSame(BusinessEntity::LETTERHEAD_INCOMPLETE, $entity->letterheadStatus());
        $this->assertTrue(BusinessEntity::letterheadIncomplete()->whereKey($entity->id)->exists());
    }

    public function test_an_outlet_whose_owner_was_deleted_is_flagged_incomplete(): void
    {
        $company = BusinessEntity::create(['name' => '__klt_pt_hapus__', 'legal_name' => 'PT __KLT HAPUS__']);
        $outlet = BusinessEntity::create(['name' => '__klt_outlet_yatim__', 'legal_entity_id' => $company->id]);
        $company->delete();

        $outlet = $outlet->fresh();
        $html = $this->renderKop($outlet);

        $this->assertStringNotContainsString('PT __KLT HAPUS__', $html);
        $this->assertStringContainsString('__klt_outlet_yatim__', $html);
        $this->assertSame(BusinessEntity::LETTERHEAD_INCOMPLETE, $outlet->letterheadStatus());
        $this->assertTrue(BusinessEntity::letterheadIncomplete()->whereKey($outlet->id)->exists());
    }

    private function actingAsManager(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $manager = User::factory()->create(['access_all_business_entities' => true]);
        $manager->givePermissionTo(array_map(
            fn (string $name) => Permission::findOrCreate($name, 'web'),
            ['ViewAny:BusinessEntity', 'View:BusinessEntity', 'Create:BusinessEntity', 'Update:BusinessEntity', 'Delete:BusinessEntity'],
        ));

        $this->actingAs($manager);
    }

    private function renderKop(BusinessEntity $entity): string
    {
        return view('pdf.partials.letterhead', [
            'letterhead' => Letterhead::for($entity->fresh('legalEntity')),
        ])->render();
    }
}
