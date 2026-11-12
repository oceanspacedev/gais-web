<?php

namespace Tests\Feature;

use App\Enums\SecurityAnswerType;
use App\Filament\Exports\SecurityChecksheetExporter;
use App\Filament\Resources\SecurityChecksheetResource\Pages\CreateSecurityChecksheet;
use App\Filament\Resources\SecurityChecksheetResource\Pages\EditSecurityChecksheet;
use App\Filament\Resources\SecurityChecksheetResource\Pages\ListSecurityChecksheets;
use App\Filament\Resources\SecurityChecksheetResource\Pages\ViewSecurityChecksheet;
use App\Models\SecurityChecklistItem;
use App\Models\SecurityChecksheet;
use App\Models\User;
use App\Support\ObPhotoDraft;
use App\Support\SecurityChecklist;
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

    private SecurityChecklistItem $gate;

    private SecurityChecklistItem $parkingLight;

    private SecurityChecklistItem $extinguisher;

    private SecurityChecklistItem $yard;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Pertanyaan bawaan database disisihkan; tiap tes memakai pertanyaannya sendiri.
        SecurityChecklistItem::query()->update(['is_active' => false]);

        $this->gate = SecurityChecklistItem::create([
            'section' => 'Keamanan',
            'question' => 'Pintu utama / gerbang',
            'answer_type' => SecurityAnswerType::Choice,
            'options' => ['Aman terkunci', 'Tidak terkunci', 'Rusak'],
            'answer_key' => 'Aman terkunci',
        ]);
        $this->parkingLight = SecurityChecklistItem::create([
            'section' => 'Kelistrikan',
            'question' => 'Lampu parkir menyala?',
            'answer_type' => SecurityAnswerType::YesNo,
            'answer_key' => SecurityAnswerType::YES,
        ]);
        $this->extinguisher = SecurityChecklistItem::create([
            'section' => 'Kondisi Aset',
            'question' => 'APAR',
            'answer_type' => SecurityAnswerType::Checkboxes,
            'options' => ['Tersegel', 'Tekanan normal', 'Kedaluwarsa'],
            'answer_key' => ['Tersegel', 'Tekanan normal'],
        ]);
        $this->yard = SecurityChecklistItem::create([
            'section' => 'Kebersihan',
            'question' => 'Halaman parkir',
            'answer_type' => SecurityAnswerType::Text,
            'answer_key' => 'diabaikan untuk teks',
        ]);
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

    public function test_guard_answers_by_picking_and_mismatches_against_the_key_are_flagged(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $guard = $this->guard();
        $this->actingAs($guard);

        Livewire::test(CreateSecurityChecksheet::class)
            ->assertFormFieldExists($this->field($this->gate))
            ->assertFormFieldExists($this->field($this->yard))
            ->fillForm([
                'location' => 'HO Petra',
                'checklist' => $this->answers(light: SecurityAnswerType::NO),
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
        $this->assertFalse($record->hasFindings());

        $entries = collect($record->checklistEntries())->keyBy('item_id');
        $this->assertCount(4, $entries);
        $this->assertSame('Pintu utama / gerbang', $entries[$this->gate->id]['question']);
        $this->assertTrue($entries[$this->gate->id]['matches']);
        $this->assertFalse($entries[$this->parkingLight->id]['matches']);
        // Urutan centang tidak memengaruhi kecocokan.
        $this->assertTrue($entries[$this->extinguisher->id]['matches']);
        $this->assertNull($entries[$this->yard->id]['matches']);
        $this->assertSame('Bersih', $entries[$this->yard->id]['answer']);

        $this->assertSame(1, $record->mismatch_count);
        $this->assertSame(['Lampu parkir menyala?'], $record->mismatchedQuestions());

        $paths = $record->attachmentPaths();
        $this->assertCount(2, $paths);
        $this->assertSame([false, true], array_map(SecurityChecksheet::isVideo(...), $paths));
        Storage::disk('public')->assertExists($paths);

        $this->assertSame([], ObPhotoDraft::get(ObPhotoDraft::SECURITY));
    }

    public function test_every_question_is_required_inactive_questions_are_hidden_and_documents_are_rejected(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->actingAs($this->guard());

        $inactive = SecurityChecklistItem::create([
            'section' => 'Keamanan',
            'question' => 'Pos lama',
            'answer_type' => SecurityAnswerType::YesNo,
            'is_active' => false,
        ]);

        $answers = $this->answers();
        $answers['q'.$this->extinguisher->id] = [];

        Livewire::test(CreateSecurityChecksheet::class)
            ->assertFormFieldDoesNotExist($this->field($inactive))
            ->fillForm([
                'location' => 'HO Petra',
                'checklist' => $answers,
                'attachments' => [UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf')],
            ])
            ->call('create')
            ->assertHasFormErrors([$this->field($this->extinguisher) => 'required', 'attachments']);

        $this->assertSame(0, SecurityChecksheet::query()->where('location', 'HO Petra')->count());
    }

    public function test_form_explains_when_no_question_is_active(): void
    {
        SecurityChecklistItem::query()->update(['is_active' => false]);

        $this->actingAs($this->guard());

        Livewire::test(CreateSecurityChecksheet::class)
            ->assertSee('Belum ada pertanyaan checklist');
    }

    public function test_unsent_checklist_is_restored_after_the_page_reloads(): void
    {
        $this->actingAs($this->guard());

        Livewire::test(CreateSecurityChecksheet::class)
            ->fillForm(['location' => 'HO Petra'])
            ->set('data.'.$this->field($this->gate), 'Rusak');

        Livewire::test(CreateSecurityChecksheet::class)
            ->assertSet('data.location', 'HO Petra')
            ->assertSet('data.'.$this->field($this->gate), 'Rusak');
    }

    public function test_reports_keep_their_questions_when_the_master_changes(): void
    {
        $guard = $this->guard();
        $report = $this->report($guard, 'HO Petra');

        $this->gate->update(['question' => 'Gerbang depan', 'answer_key' => 'Rusak']);
        $this->parkingLight->delete();

        $report->refresh();
        $this->assertSame(['Pintu utama / gerbang', 'Lampu parkir menyala?', 'APAR', 'Halaman parkir'], array_column($report->checklistEntries(), 'question'));
        $this->assertSame(0, $report->mismatch_count);

        // Edit memakai pertanyaan yang tersimpan, termasuk yang sudah dihapus dari master.
        $supervisor = User::factory()->create()->givePermissionTo(Permission::whereIn('name', [
            'view_any_security::checksheet', 'view_security::checksheet', 'update_security::checksheet',
            'view_all_security::checksheet', 'update_all_security::checksheet',
        ])->get());
        $this->actingAs($supervisor);

        Livewire::test(EditSecurityChecksheet::class, ['record' => $report->getRouteKey()])
            ->assertFormFieldExists($this->field($this->parkingLight))
            ->assertSet('data.'.$this->field($this->gate), 'Aman terkunci')
            ->fillForm([$this->field($this->gate) => 'Rusak'])
            ->call('save')
            ->assertHasNoFormErrors();

        $report->refresh();
        $gate = collect($report->checklistEntries())->firstWhere('item_id', $this->gate->id);
        $this->assertSame('Pintu utama / gerbang', $gate['question']);
        $this->assertSame('Aman terkunci', $gate['answer_key']);
        $this->assertFalse($gate['matches']);
        $this->assertSame(1, $report->mismatch_count);
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

    public function test_list_can_be_filtered_to_reports_with_mismatched_answers(): void
    {
        $guard = $this->guard();
        $normal = $this->report($guard, 'HO Petra');
        $flagged = $this->report($guard, 'Gudang', answers: $this->answers(light: SecurityAnswerType::NO));

        $this->actingAs($guard);

        Livewire::test(ListSecurityChecksheets::class)
            ->assertSee('1 tidak sesuai')
            ->filterTable('has_mismatch')
            ->assertCanSeeTableRecords([$flagged])
            ->assertCanNotSeeTableRecords([$normal]);
    }

    public function test_detail_page_shows_the_checklist_mismatches_findings_and_attachments(): void
    {
        $guard = $this->guard();
        $report = $this->report($guard, 'HO Petra', [
            'findings' => 'Pagar belakang rusak',
            'attachments' => ['security-checksheets/gerbang.jpg', 'security-checksheets/patroli.mp4'],
        ], $this->answers(light: SecurityAnswerType::NO));

        $this->actingAs($guard);

        Livewire::test(ViewSecurityChecksheet::class, ['record' => $report->getRouteKey()])
            ->assertOk()
            ->assertSee('Pintu utama / gerbang')
            ->assertSee('Tersegel, Tekanan normal')
            ->assertSee('Tidak sesuai · kunci: Ya')
            ->assertSee('Pagar belakang rusak')
            ->assertSeeHtml('data-lightbox="true"')
            ->assertSeeHtml('<video');
    }

    public function test_export_has_one_column_per_question_and_the_mismatch_summary(): void
    {
        $labels = array_map(fn ($column): string => $column->getLabel(), SecurityChecksheetExporter::getColumns());

        $this->assertContains('Keamanan - Pintu utama / gerbang', $labels);
        $this->assertContains('Kondisi Aset - APAR', $labels);
        $this->assertContains('Jumlah Tidak Sesuai', $labels);
        $this->assertContains('Butir Tidak Sesuai', $labels);
        $this->assertContains('Kondisi Darurat / Temuan Kejadian', $labels);

        $report = $this->report($this->guard(), 'HO Petra');
        $this->assertSame('Tersegel, Tekanan normal', $report->answerFor($this->extinguisher->id));
    }

    private function guard(): User
    {
        return User::factory()->create()->assignRole('security');
    }

    private function field(SecurityChecklistItem $item): string
    {
        return 'checklist.q'.$item->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function answers(string $light = SecurityAnswerType::YES): array
    {
        return [
            'q'.$this->gate->id => 'Aman terkunci',
            'q'.$this->parkingLight->id => $light,
            'q'.$this->extinguisher->id => ['Tekanan normal', 'Tersegel'],
            'q'.$this->yard->id => 'Bersih',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $answers
     */
    private function report(User $user, string $location, array $attributes = [], ?array $answers = null): SecurityChecksheet
    {
        return SecurityChecksheet::create([
            'user_id' => $user->id,
            'location' => $location,
            ...SecurityChecklist::build(SecurityChecklist::activeDefinitions(), $answers ?? $this->answers()),
            ...$attributes,
        ]);
    }
}
