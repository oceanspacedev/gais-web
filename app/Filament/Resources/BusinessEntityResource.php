<?php

namespace App\Filament\Resources;

use App\Access\ScopesVisibleRecords;
use App\Enums\BadgeColor;
use App\Enums\LegalForm;
use App\Enums\LetterheadLayout;
use App\Filament\Resources\BusinessEntityResource\Pages;
use App\Models\BusinessEntity;
use App\Support\Letterhead;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BusinessEntityResource extends Resource
{
    use ScopesVisibleRecords;

    protected static ?string $model = BusinessEntity::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Organisasi';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $form): Schema
    {
        $trim = fn (?string $state): ?string => filled($state) ? trim($state) : null;
        // Brand/outlet memakai kop PT pemiliknya; data kop sendiri disembunyikan.
        $isOutlet = fn (Get $get): bool => $get('legal_form') === LegalForm::Brand || $get('legal_form') === LegalForm::Brand->value || filled($get('legal_entity_id'));

        return $form
            ->columns(1)
            ->schema([
                Tabs::make()
                    ->contained(false)
                    ->tabs([
                        Tab::make('Umum')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Badan Usaha')
                                    ->placeholder('Contoh: CV MITRA COMPLETE SELULAR')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    // Index unik database ikut menghitung badan usaha di Sampah, jadi validasi pun begitu.
                                    ->unique(ignoreRecord: true),
                                Grid::make(['default' => 1, 'sm' => 2])->schema([
                                    TextInput::make('format')
                                        ->label('Prefix Nomor Surat')
                                        ->placeholder('Contoh: 120920.MT/')
                                        ->helperText('Kosongkan untuk memakai inisial nama.')
                                        ->maxLength(255),
                                    Select::make('color')
                                        ->label('Warna Label')
                                        ->allowHtml()
                                        ->options(
                                            collect(BadgeColor::cases())
                                                ->sort(static fn ($a, $b) => $a->value <=> $b->value)
                                                ->mapWithKeys(static fn ($case) => [
                                                    $case->value => "<span class='flex items-center gap-x-4'>
                                            <span class='rounded-full w-4 h-4' style='background:rgb(".$case->getColor()[600].")'></span>
                                            <span>".$case->getLabel().'</span>
                                            </span>',
                                                ]),
                                        )
                                        ->default(BadgeColor::Gray->value)
                                        ->searchable(),
                                ]),
                                Grid::make(['default' => 1, 'sm' => 2])->schema([
                                    Select::make('legal_form')
                                        ->label('Jenis')
                                        ->options(LegalForm::class)
                                        ->placeholder('Pilih jenis')
                                        ->native(false)
                                        ->live(),
                                    TextInput::make('short_name')
                                        ->label('Singkatan')
                                        ->placeholder('Contoh: MKLI')
                                        ->maxLength(64),
                                ]),
                            ]),
                        Tab::make('Kop Surat')
                            ->icon('heroicon-o-document-text')
                            ->badge(fn (?BusinessEntity $record): ?string => $record && $record->letterheadStatus() === BusinessEntity::LETTERHEAD_INCOMPLETE ? 'Belum lengkap' : null)
                            ->badgeColor('warning')
                            ->schema([
                                View::make('filament.business-entity.letterhead-preview')
                                    ->viewData(fn (Get $get): array => ['letterhead' => Letterhead::fromFormState([
                                        'name' => $get('name'),
                                        'legal_name' => $get('legal_name'),
                                        'address' => $get('address'),
                                        'phone' => $get('phone'),
                                        'email' => $get('email'),
                                        'logo' => $get('logo'),
                                        'letterhead' => $get('letterhead'),
                                        'legal_entity_id' => $get('legal_entity_id'),
                                        'letterhead_layout' => $get('letterhead_layout'),
                                    ])]),
                                Select::make('legal_entity_id')
                                    ->label('Kop ikut PT/CV')
                                    ->placeholder('Pilih PT/CV pemilik outlet ini')
                                    ->helperText('Outlet memakai kop PT/CV pemiliknya, dengan baris "Outlet: ...".')
                                    ->relationship(
                                        'legalEntity',
                                        'name',
                                        fn (Builder $query, ?BusinessEntity $record) => $query
                                            ->whereNull('legal_entity_id')
                                            ->when($record, fn (Builder $q) => $q->whereKeyNot($record->getKey()))
                                            ->orderBy('name'),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->visible($isOutlet)
                                    // Satu tingkat saja: PT yang sudah punya outlet tidak boleh punya PT pemilik.
                                    ->disabled(fn (?BusinessEntity $record): bool => (bool) $record?->outlets()->exists())
                                    ->hint(fn (?BusinessEntity $record): ?string => $record?->outlets()->exists() ? 'Sudah menjadi pemilik outlet lain.' : null),
                                Group::make([
                                    ToggleButtons::make('letterhead_layout')
                                        ->label('Posisi teks')
                                        ->helperText('Logo selalu di kiri. Tanpa logo, teks otomatis di tengah.')
                                        ->options(LetterheadLayout::class)
                                        ->default(LetterheadLayout::Right->value)
                                        // Record lama belum punya nilai: tampilkan pilihan bawaan (kanan).
                                        ->afterStateHydrated(fn (ToggleButtons $component, mixed $state) => blank($state) ? $component->state(LetterheadLayout::Right->value) : null)
                                        ->grouped()
                                        ->live(),
                                    TextInput::make('legal_name')
                                        ->label('Nama di Kop')
                                        ->placeholder('Contoh: CV MITRA COMPLETE SELULAR')
                                        ->helperText('Sesuai akta. Kosongkan untuk memakai Nama Badan Usaha.')
                                        ->dehydrateStateUsing($trim)
                                        ->live(onBlur: true)
                                        ->maxLength(255),
                                    Textarea::make('address')
                                        ->label('Alamat')
                                        ->placeholder("Contoh:\nJl. Tuparev No. 109F, Kedawung, Cirebon 45153")
                                        ->helperText('Maksimal 2 baris.')
                                        ->rows(2)
                                        ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                            if (is_string($value) && count(preg_split('/\R/', trim($value))) > 2) {
                                                $fail('Alamat maksimal 2 baris.');
                                            }
                                        })
                                        ->dehydrateStateUsing($trim)
                                        ->live(onBlur: true)
                                        ->maxLength(200),
                                    Grid::make(['default' => 1, 'sm' => 2])->schema([
                                        TextInput::make('phone')
                                            ->label('Telepon')
                                            ->placeholder('Contoh: 0231-8332833')
                                            ->tel()
                                            ->dehydrateStateUsing($trim)
                                            ->live(onBlur: true)
                                            ->maxLength(50),
                                        TextInput::make('email')
                                            ->label('Email')
                                            ->placeholder('Contoh: info@perusahaan.co.id')
                                            ->email()
                                            ->live(onBlur: true)
                                            ->maxLength(255),
                                    ]),
                                    FileUpload::make('logo')
                                        ->label('Logo')
                                        ->helperText('PNG/JPG, logonya saja (bukan kop utuh).')
                                        ->image()
                                        ->acceptedFileTypes(['image/png', 'image/jpeg'])
                                        ->maxSize(2048)
                                        ->disk('public')
                                        ->directory('kopsurat/logo')
                                        ->live(),
                                ])->hidden($isOutlet),
                                Section::make('Pakai gambar kop sendiri')
                                    ->description('Opsional. Kalau diisi, gambar ini menggantikan kop otomatis.')
                                    ->collapsible()
                                    ->collapsed(fn (?BusinessEntity $record): bool => blank($record?->letterhead))
                                    ->compact()
                                    ->schema([
                                        FileUpload::make('letterhead')
                                            ->hiddenLabel()
                                            ->image()
                                            ->disk('public')
                                            ->directory('kopsurat')
                                            ->live(),
                                    ]),
                            ]),
                        Tab::make('Legalitas')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Callout::make('Disimpan sebagai data, tidak dicetak di kop.')
                                    ->info()
                                    ->color(null),
                                Grid::make(['default' => 1, 'sm' => 2])->schema([
                                    TextInput::make('npwp')
                                        ->label('NPWP')
                                        ->placeholder('16 digit')
                                        ->helperText('NPWP lama 15 digit otomatis diawali 0.')
                                        ->rule(fn (): \Closure => static::digitsRule([15, 16], 'NPWP harus 15 atau 16 digit.'))
                                        ->dehydrateStateUsing(fn (?string $state): ?string => static::normalizeNpwp($state)),
                                    TextInput::make('nib')
                                        ->label('NIB')
                                        ->placeholder('13 digit dari OSS')
                                        ->rule(fn (): \Closure => static::digitsRule([13], 'NIB harus 13 digit.'))
                                        ->dehydrateStateUsing(fn (?string $state): ?string => static::digitsOnly($state)),
                                ]),
                                TextInput::make('legal_registration_number')
                                    ->label('No. Pengesahan Badan Hukum')
                                    ->placeholder('Contoh: AHU-0012345.AH.01.01.Tahun 2020')
                                    ->maxLength(255),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('legalEntity'))
            ->columns([
                TextColumn::make('name')
                    ->translateLabel('Business Entity')
                    ->searchable()
                    ->badge()
                    ->color(fn ($record) => $record->color)
                    ->getStateUsing(fn ($record) => $record->name),
                TextColumn::make('format')->translateLabel(),
                TextColumn::make('letterhead_status')
                    ->label('Kop Surat')
                    ->badge()
                    ->getStateUsing(fn (BusinessEntity $record): string => $record->letterheadStatus())
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        BusinessEntity::LETTERHEAD_IMAGE => 'Kop gambar',
                        BusinessEntity::LETTERHEAD_PARENT => 'Ikut PT pemilik',
                        BusinessEntity::LETTERHEAD_COMPLETE => 'Lengkap',
                        default => 'Belum lengkap',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        BusinessEntity::LETTERHEAD_IMAGE => 'info',
                        BusinessEntity::LETTERHEAD_PARENT => 'gray',
                        BusinessEntity::LETTERHEAD_COMPLETE => 'success',
                        default => 'warning',
                    }),
                TextColumn::make('created_at')
                    ->translateLabel()
                    ->dateTime(),
                TextColumn::make('updated_at')
                    ->translateLabel()
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Filter::make('letterhead_incomplete')
                    ->label('Kop belum lengkap')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->letterheadIncomplete()),
            ])
            ->actions([
                RestoreAction::make(),
                Action::make('previewLetterhead')
                    ->label('Pratinjau Kop')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('gray')
                    ->url(fn (BusinessEntity $record): string => route('business-entity.letterhead-preview', $record))
                    ->openUrlInNewTab()
                    ->hidden(fn (BusinessEntity $record): bool => $record->trashed()),
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('3xl'),
                DeleteAction::make()
                    // Outlet yang PT pemiliknya dihapus akan kehilangan kop PT-nya.
                    ->disabled(fn (BusinessEntity $record): bool => $record->outlets()->exists())
                    ->tooltip(fn (BusinessEntity $record): ?string => $record->outlets()->exists() ? 'Masih menjadi PT pemilik outlet lain.' : null),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @param  list<int>  $lengths
     */
    private static function digitsRule(array $lengths, string $message): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($lengths, $message): void {
            $digits = static::digitsOnly(is_scalar($value) ? (string) $value : null);
            if ($digits !== null && ! in_array(strlen($digits), $lengths, true)) {
                $fail($message);
            }
        };
    }

    public static function digitsOnly(?string $state): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $state);

        return $digits === '' ? null : $digits;
    }

    /** NPWP 15 digit (format lama) diawali 0 menjadi 16 digit sesuai PMK 112/2022. */
    public static function normalizeNpwp(?string $state): ?string
    {
        $digits = static::digitsOnly($state);

        return $digits !== null && strlen($digits) === 15 ? '0'.$digits : $digits;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBusinessEntities::route('/'),
        ];
    }

    public static function getModelLabel(): string
    {
        return __('Business Entity');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Business Entities');
    }
}
