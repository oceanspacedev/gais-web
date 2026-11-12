<?php

namespace Tests\Feature;

use App\Enums\SecurityAnswerType;
use App\Filament\Resources\SecurityChecklistItemResource;
use App\Filament\Resources\SecurityChecklistItemResource\Pages\ManageSecurityChecklistItems;
use App\Models\SecurityChecklistItem;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityChecklistItemTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Kunci item repeater jadi angka agar isian tes menimpa baris bawaan.
        Repeater::fake();
    }

    public function test_old_checklist_items_are_seeded_as_choice_questions(): void
    {
        $seeded = SecurityChecklistItem::query()->where('question', 'Pintu utama / gerbang')->first();

        $this->assertNotNull($seeded);
        $this->assertSame('Keamanan', $seeded->section);
        $this->assertSame(SecurityAnswerType::Choice, $seeded->answer_type);
        $this->assertSame(['Baik', 'Bermasalah'], $seeded->options);
        $this->assertSame('Baik', $seeded->answer_key);
    }

    public function test_admin_creates_a_question_by_ticking_the_correct_choices(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ManageSecurityChecklistItems::class)
            ->callAction('create', data: [
                'question' => 'Gerbang belakang',
                'answer_type' => SecurityAnswerType::Checkboxes->value,
                'choices' => [
                    ['label' => 'Terkunci', 'is_key' => true],
                    ['label' => 'Digembok', 'is_key' => true],
                    ['label' => 'Rusak', 'is_key' => false],
                ],
                'section' => 'Keamanan',
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $item = SecurityChecklistItem::query()->where('question', 'Gerbang belakang')->sole();

        $this->assertSame(SecurityAnswerType::Checkboxes, $item->answer_type);
        $this->assertSame(['Terkunci', 'Digembok', 'Rusak'], $item->options);
        $this->assertSame(['Terkunci', 'Digembok'], $item->answer_key);
        $this->assertSame((int) SecurityChecklistItem::withTrashed()->max('sort'), $item->sort);
    }

    public function test_yes_no_question_can_be_left_unscored(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ManageSecurityChecklistItems::class)
            ->callAction('create', data: [
                'question' => 'Ada tamu menginap?',
                'answer_type' => SecurityAnswerType::YesNo->value,
                'yes_no_key' => 'none',
                'section' => 'Keamanan',
            ])
            ->assertHasNoActionErrors();

        $item = SecurityChecklistItem::query()->where('question', 'Ada tamu menginap?')->sole();

        $this->assertNull($item->options);
        $this->assertNull($item->answer_key);
    }

    public function test_choice_needs_two_distinct_filled_choices(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ManageSecurityChecklistItems::class)
            ->callAction('create', data: [
                'question' => 'Gerbang',
                'answer_type' => SecurityAnswerType::Choice->value,
                'choices' => [['label' => 'Aman', 'is_key' => true]],
                'section' => 'Keamanan',
            ])
            ->assertHasActionErrors();

        $this->assertSame(0, SecurityChecklistItem::query()->where('question', 'Gerbang')->count());
    }

    public function test_text_questions_drop_options_and_answer_key(): void
    {
        $item = SecurityChecklistItem::create([
            'section' => 'Catatan',
            'question' => 'Kondisi halaman',
            'answer_type' => SecurityAnswerType::Text,
            'options' => ['A', 'B'],
            'answer_key' => 'A',
        ]);

        $this->assertNull($item->fresh()->options);
        $this->assertNull($item->fresh()->answer_key);
    }

    public function test_admin_edits_a_question_with_its_answer_key_prefilled(): void
    {
        $item = SecurityChecklistItem::create([
            'section' => 'Keamanan',
            'question' => 'Pintu gudang',
            'answer_type' => SecurityAnswerType::Choice,
            'options' => ['Terkunci', 'Terbuka'],
            'answer_key' => 'Terkunci',
        ]);

        $this->actingAs($this->admin());

        $page = Livewire::test(ManageSecurityChecklistItems::class)
            ->assertCanSeeTableRecords([$item])
            ->assertSeeHtml('fi-color-success')
            ->mountAction(TestAction::make(EditAction::class)->table($item));

        $choices = array_values($page->get('mountedActions.0.data.choices'));
        $this->assertSame([['label' => 'Terkunci', 'is_key' => true], ['label' => 'Terbuka', 'is_key' => false]], $choices);

        $page->fillForm([
            'question' => 'Pintu gudang belakang',
            'choices' => [
                ['label' => 'Terkunci', 'is_key' => false],
                ['label' => 'Terbuka', 'is_key' => true],
            ],
        ])->callMountedAction()->assertHasNoActionErrors();

        $item->refresh();
        $this->assertSame('Pintu gudang belakang', $item->question);
        $this->assertSame('Terbuka', $item->answer_key);
        $this->assertTrue(SecurityChecklistItemResource::canReorder());
    }

    public function test_admin_previews_the_form_as_the_guard_sees_it(): void
    {
        SecurityChecklistItem::create([
            'section' => 'Keamanan',
            'question' => 'Pintu gudang pratinjau',
            'answer_type' => SecurityAnswerType::Choice,
            'options' => ['Terkunci', 'Terbuka'],
            'answer_key' => 'Terkunci',
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ManageSecurityChecklistItems::class)
            ->mountAction('preview')
            ->assertMountedActionModalSee(['Form Security', 'Pintu gudang pratinjau', 'Terkunci']);
    }

    public function test_security_guards_cannot_manage_questions(): void
    {
        $guard = User::factory()->create()->assignRole('security');

        $this->actingAs($guard);

        $this->assertFalse(SecurityChecklistItemResource::canViewAny());
        $this->get(SecurityChecklistItemResource::getUrl('index'))->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole(Role::findOrCreate(config('filament-shield.super_admin.name', 'super_admin'), 'web'));
    }
}
