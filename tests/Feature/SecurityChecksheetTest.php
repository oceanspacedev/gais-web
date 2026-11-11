<?php

namespace Tests\Feature;

use App\Filament\Exports\SecurityChecksheetExporter;
use App\Filament\Resources\SecurityChecksheetResource\Pages\CreateSecurityChecksheet;
use App\Filament\Resources\SecurityChecksheetResource\Pages\ListSecurityChecksheets;
use App\Filament\Resources\SecurityChecksheetResource\Pages\ViewSecurityChecksheet;
use App\Models\SecurityChecksheet;
use App\Models\User;
use App\Support\ObPhotoDraft;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\AssertsVisibilityContract;
use Tests\TestCase;

class SecurityChecksheetTest extends TestCase
{
    use AssertsVisibilityContract, DatabaseTransactions;

    private const CHECKLIST = [
        'keamanan' => ['pintu_utama' => 'aman terkunci', 'ruangan_kantor' => 'aman terkunci', 'gudang_ruangan' => 'aman terkunci'],
        'kelistrikan' => ['lampu_parkir' => 'Nyala sampai pagi 1 lampu', 'lampu_gudang' => 'sudah mati semua', 'ac_ruangan' => 'Ga ada yang nyala'],
        'kondisi_aset' => ['mobil' => 'aman', 'motor' => 'aman', 'apar' => 'aman'],
        'kebersihan' => ['halaman_parkir' => 'Bersih', 'area_toko' => 'Bersih'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_security_role_mirrors_the_office_boy_permissions(): void
    {
        $security = Role::findByName('security', 'web');

        $this->assertTrue($security->hasPermissionTo('view_any_security::checksheet'));
        $this->assertTrue($security->hasPermissionTo('view_security::checksheet'));
        $this->assertTrue($security->hasPermissionTo('create_security::checksheet'));
        $this->assertFalse($security->hasPermissionTo('view_all_security::checksheet'));
        $this->assertFalse($security->hasPermissionTo('delete_security::checksheet'));
    }

    public function test_guard_submits_the_checklist_with_photo_and_video_attachments(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $guard = $this->guard();
        $this->actingAs($guard);

        Livewire::test(CreateSecurityChecksheet::class)
            ->fillForm([
                'location' => 'HO Petra',
                'checklist' => self::CHECKLIST,
                'findings' => null,
                'attachments' => [
                    UploadedFile::fake()->image('gerbang.jpg'),
                    UploadedFile::fake()->create('patroli.mp4', 20480, 'video/mp4'),
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $record = SecurityChecksheet::query()->where('user_id', $guard->id)->sole();

        $this->assertSame('HO Petra', $record->location);
        $this->assertStringStartsWith('SEC-'.now()->format('Ymd').'-', $record->reference_number);
        $this->assertTrue($record->report_date->isToday());
        $this->assertEquals(self::CHECKLIST, $record->checklist);
        $this->assertFalse($record->hasFindings());

        $paths = $record->attachmentPaths();
        $this->assertCount(2, $paths);
        $this->assertSame([false, true], array_map(SecurityChecksheet::isVideo(...), $paths));
        Storage::disk('public')->assertExists($paths);

        $this->assertSame([], ObPhotoDraft::get(ObPhotoDraft::SECURITY));
    }

    public function test_every_checklist_item_is_required_and_documents_are_rejected(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->actingAs($this->guard());

        $checklist = self::CHECKLIST;
        $checklist['kebersihan']['area_toko'] = null;

        Livewire::test(CreateSecurityChecksheet::class)
            ->fillForm([
                'location' => 'HO Petra',
                'checklist' => $checklist,
                'attachments' => [UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf')],
            ])
            ->call('create')
            ->assertHasFormErrors(['checklist.kebersihan.area_toko' => 'required', 'attachments']);

        $this->assertSame(0, SecurityChecksheet::query()->where('location', 'HO Petra')->count());
    }

    public function test_unsent_checklist_is_restored_after_the_page_reloads(): void
    {
        $this->actingAs($this->guard());

        Livewire::test(CreateSecurityChecksheet::class)
            ->fillForm(['location' => 'HO Petra'])
            ->set('data.checklist.keamanan.pintu_utama', 'aman terkunci');

        Livewire::test(CreateSecurityChecksheet::class)
            ->assertSet('data.location', 'HO Petra')
            ->assertSet('data.checklist.keamanan.pintu_utama', 'aman terkunci');
    }

    public function test_guard_only_sees_own_reports_unless_allowed_to_view_all(): void
    {
        $guard = $this->guard();
        $supervisor = User::factory()->create()->givePermissionTo(Permission::findByName('view_all_security::checksheet', 'web'));

        $own = $this->report($guard, 'HO Petra');
        $other = $this->report($this->guard(), 'Gudang Lain');
        $trashed = $this->report($guard, 'Terhapus');
        $trashed->delete();

        $ids = [$own->id, $other->id, $trashed->id];

        $this->assertSame([$own->id], SecurityChecksheet::query()->whereKey($ids)->visibleTo($guard)->pluck('id')->all());
        $this->assertCount(2, SecurityChecksheet::query()->whereKey($ids)->visibleTo($supervisor)->get());
        $this->assertQueryAndPolicyAgree(SecurityChecksheet::class, ['guard' => $guard, 'supervisor' => $supervisor, 'stranger' => User::factory()->create()], $ids);

        $this->actingAs($guard);

        Livewire::test(ListSecurityChecksheets::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other]);

        $this->assertFalse($guard->can('update', $own));
        $this->assertFalse($guard->can('view', $other));
    }

    public function test_detail_page_shows_the_checklist_findings_and_attachments(): void
    {
        $guard = $this->guard();
        $report = $this->report($guard, 'HO Petra', [
            'findings' => 'Pagar belakang rusak',
            'attachments' => ['security-checksheets/gerbang.jpg', 'security-checksheets/patroli.mp4'],
        ]);

        $this->actingAs($guard);

        Livewire::test(ViewSecurityChecksheet::class, ['record' => $report->getRouteKey()])
            ->assertOk()
            ->assertSee('Pintu utama / gerbang')
            ->assertSee('Nyala sampai pagi 1 lampu')
            ->assertSee('Pagar belakang rusak')
            ->assertSeeHtml('data-lightbox="true"')
            ->assertSeeHtml('<video');
    }

    public function test_export_has_one_column_per_checklist_item(): void
    {
        $labels = array_map(fn ($column): string => $column->getLabel(), SecurityChecksheetExporter::getColumns());

        $this->assertContains('Keamanan - Pintu utama / gerbang', $labels);
        $this->assertContains('Kebersihan - Area toko / ruangan', $labels);
        $this->assertContains('Kondisi Darurat / Temuan Kejadian', $labels);
    }

    private function guard(): User
    {
        return User::factory()->create()->assignRole('security');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function report(User $user, string $location, array $attributes = []): SecurityChecksheet
    {
        return SecurityChecksheet::create([
            'user_id' => $user->id,
            'location' => $location,
            'checklist' => self::CHECKLIST,
            ...$attributes,
        ]);
    }
}
