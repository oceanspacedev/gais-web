<?php

namespace App\Filament\Resources;

use App\Access\ScopesVisibleRecords;
use App\Enums\SecurityAnswerType;
use App\Filament\Resources\SecurityChecklistItemResource\Pages;
use App\Models\SecurityChecklistItem;
use App\Support\SecurityChecklist;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Pengaturan pertanyaan Security Checksheet ala Google Form: tulis pertanyaan,
 * pilih cara menjawab, isi pilihan jawaban lalu centang yang benar.
 * Petugas security cukup memilih jawaban.
 */
class SecurityChecklistItemResource extends Resource
{
    use ScopesVisibleRecords;

    /** Pilihan "tidak dinilai" untuk pertanyaan Ya / Tidak. */
    private const NOT_SCORED = 'none';

    protected static ?string $model = SecurityChecklistItem::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('Pertanyaan Checksheet');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Pertanyaan Checksheet');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                TextInput::make('question')
                    ->label('Pertanyaan')
                    ->placeholder('Contoh: Pintu gerbang depan')
                    ->required()
                    ->maxLength(255),

                ToggleButtons::make('answer_type')
                    ->label('Cara security menjawab')
                    ->options(SecurityAnswerType::options())
                    ->icons(SecurityAnswerType::icons())
                    ->columns(2)
                    ->default(SecurityAnswerType::Choice->value)
                    ->helperText(fn (Get $get): ?string => self::type($get)?->description())
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get): void {
                        $set('yes_no_key', self::type($get) === SecurityAnswerType::YesNo ? SecurityAnswerType::YES : null);

                        if (self::type($get)?->hasOptions() && blank($get('choices'))) {
                            $set('choices', self::blankChoices());
                        }
                    }),

                // Seperti kunci jawaban Google Form: tiap pilihan punya centang "Benar" sendiri.
                Repeater::make('choices')
                    ->label('Pilihan jawaban')
                    ->helperText(fn (Get $get): string => self::type($get) === SecurityAnswerType::Checkboxes
                        ? 'Centang "Benar" pada semua pilihan yang seharusnya dicentang security. Jawaban lain ditandai Tidak Sesuai.'
                        : 'Centang "Benar" pada jawaban yang normal. Jawaban lain ditandai Tidak Sesuai. Boleh tidak dicentang bila tidak perlu dinilai.')
                    ->schema([
                        TextInput::make('label')
                            ->hiddenLabel()
                            ->placeholder('Tulis pilihan, mis. Aman')
                            ->required()
                            ->distinct()
                            ->maxLength(255)
                            ->columnSpan(['default' => 3]),
                        // Pilih satu: hanya satu yang boleh benar, centang lain otomatis lepas.
                        Checkbox::make('is_key')
                            ->label('Benar')
                            ->fixIndistinctState(fn (Get $get): bool => self::type($get, '../../answer_type') === SecurityAnswerType::Choice)
                            ->extraFieldWrapperAttributes(['style' => 'padding-top: 0.5rem;'])
                            ->columnSpan(['default' => 1]),
                    ])
                    ->columns(['default' => 4])
                    ->defaultItems(2)
                    ->minItems(2)
                    ->addActionLabel('Tambah pilihan')
                    ->reorderable()
                    ->visible(fn (Get $get): bool => self::type($get)?->hasOptions() ?? false),

                ToggleButtons::make('yes_no_key')
                    ->label('Jawaban yang benar')
                    ->helperText('Jawaban lain ditandai Tidak Sesuai.')
                    ->options([
                        SecurityAnswerType::YES => SecurityAnswerType::YES,
                        SecurityAnswerType::NO => SecurityAnswerType::NO,
                        self::NOT_SCORED => 'Tidak dinilai',
                    ])
                    ->colors([SecurityAnswerType::YES => 'success', SecurityAnswerType::NO => 'success', self::NOT_SCORED => 'gray'])
                    ->inline()
                    ->default(SecurityAnswerType::YES)
                    ->required()
                    ->visible(fn (Get $get): bool => self::type($get) === SecurityAnswerType::YesNo),

                Callout::make('Isian teks tidak dinilai')
                    ->description('Security mengetik kondisinya sendiri, jadi tidak ada jawaban benar atau salah.')
                    ->info()
                    ->visible(fn (Get $get): bool => self::type($get) === SecurityAnswerType::Text),

                TextInput::make('section')
                    ->label('Kelompok')
                    ->placeholder('Contoh: Keamanan')
                    ->helperText('Pertanyaan dengan kelompok yang sama tampil dalam satu kotak.')
                    ->datalist(fn (): array => self::sections())
                    ->required()
                    ->maxLength(255),

                Toggle::make('is_active')
                    ->label('Tampilkan di form security')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        // Urutan tabel = urutan di form security.
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            // Dikelompokkan seperti kotak di form; kelompok diurutkan menurut pertanyaan pertamanya.
            ->defaultGroup(
                Group::make('section')
                    ->label('Kelompok')
                    ->titlePrefixedWithLabel(false)
                    ->collapsible()
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        '(select min(first_item.sort) from security_checklist_items as first_item where first_item.section = security_checklist_items.section) '.($direction === 'desc' ? 'desc' : 'asc'),
                    )),
            )
            ->groupingSettingsHidden()
            ->paginated(false)
            ->emptyStateHeading('Belum ada pertanyaan')
            ->emptyStateDescription('Tambahkan pertanyaan pertama, misalnya "Pintu gerbang depan".')
            ->columns([
                TextColumn::make('question')
                    ->label('Pertanyaan')
                    ->weight('bold')
                    ->wrap()
                    ->searchable(['question', 'section']),

                ViewColumn::make('choices')
                    ->label('Pilihan jawaban (hijau = benar)')
                    ->view('filament.security-checklist-item.choices-column'),

                TextColumn::make('answer_type')
                    ->label('Cara menjawab')
                    ->badge()
                    ->color('gray')
                    ->icon(fn (SecurityAnswerType $state): string => $state->icon())
                    ->formatStateUsing(fn (SecurityAnswerType $state): string => $state->label()),

                IconColumn::make('is_active')
                    ->label('Tampil')
                    ->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('section')
                    ->label('Kelompok')
                    ->options(fn (): array => array_combine(self::sections(), self::sections())),
                TernaryFilter::make('is_active')
                    ->label('Tampil di form'),
            ])
            ->recordActions([
                RestoreAction::make(),
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('lg')
                    ->mutateRecordDataUsing(fn (array $data): array => self::toFormData($data))
                    ->mutateDataUsing(fn (array $data): array => self::fromFormData($data)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSecurityChecklistItems::route('/'),
        ];
    }

    /**
     * Pratinjau form apa adanya seperti yang dilihat petugas security.
     */
    public static function previewAction(): Action
    {
        return Action::make('preview')
            ->label('Lihat Form Security')
            ->icon('heroicon-o-device-phone-mobile')
            ->color('gray')
            ->modalHeading('Tampilan form di HP security')
            ->modalDescription('Hanya pertanyaan yang tampil. Kunci jawaban tidak terlihat oleh security.')
            ->modalWidth('md')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalContent(fn () => view('filament.security-checklist-item.form-preview', [
                'sections' => SecurityChecklist::groupBySection(SecurityChecklist::activeDefinitions()),
            ]));
    }

    /**
     * Kolom options/answer_key dibuka menjadi isian form.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toFormData(array $data): array
    {
        $type = SecurityAnswerType::tryFrom((string) ($data['answer_type'] ?? ''));
        $key = $data['answer_key'] ?? null;
        $keys = array_map('strval', (array) $key);

        $data['choices'] = [];
        foreach ((array) ($data['options'] ?? []) as $option) {
            $data['choices'][(string) Str::uuid()] = ['label' => (string) $option, 'is_key' => in_array((string) $option, $keys, true)];
        }

        $data['yes_no_key'] = $type === SecurityAnswerType::YesNo ? (filled($key) ? (string) $key : self::NOT_SCORED) : null;

        return $data;
    }

    /**
     * Isian form disimpan kembali ke kolom options dan answer_key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromFormData(array $data): array
    {
        $type = SecurityAnswerType::tryFrom((string) ($data['answer_type'] ?? ''));
        $choices = array_values(array_filter(
            (array) ($data['choices'] ?? []),
            fn (mixed $choice): bool => is_array($choice) && filled($choice['label'] ?? null),
        ));

        $options = array_map(fn (array $choice): string => trim((string) $choice['label']), $choices);
        $keys = array_map(
            fn (array $choice): string => trim((string) $choice['label']),
            array_filter($choices, fn (array $choice): bool => (bool) ($choice['is_key'] ?? false)),
        );

        $data['options'] = $type?->hasOptions() ? $options : null;
        $data['answer_key'] = match ($type) {
            SecurityAnswerType::Choice => array_values($keys)[0] ?? null,
            SecurityAnswerType::Checkboxes => $keys === [] ? null : array_values($keys),
            SecurityAnswerType::YesNo => in_array($data['yes_no_key'] ?? null, [SecurityAnswerType::YES, SecurityAnswerType::NO], true) ? $data['yes_no_key'] : null,
            default => null,
        };

        unset($data['choices'], $data['yes_no_key']);

        return $data;
    }

    private static function type(Get $get, string $path = 'answer_type'): ?SecurityAnswerType
    {
        $type = $get($path);

        return $type instanceof SecurityAnswerType ? $type : SecurityAnswerType::tryFrom((string) $type);
    }

    /**
     * @return array<string, array{label: null, is_key: false}>
     */
    private static function blankChoices(): array
    {
        return [
            (string) Str::uuid() => ['label' => null, 'is_key' => false],
            (string) Str::uuid() => ['label' => null, 'is_key' => false],
        ];
    }

    /**
     * @return list<string>
     */
    private static function sections(): array
    {
        return SecurityChecklistItem::query()
            ->ordered()
            ->pluck('section')
            ->unique()
            ->values()
            ->all();
    }
}
