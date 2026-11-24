<?php

namespace App\Filament\Resources;

use App\Access\ScopesVisibleRecords;
use App\Enums\BadgeColor;
use App\Enums\LegalForm;
use App\Filament\Resources\BusinessEntityResource\Pages;
use App\Models\BusinessEntity;
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
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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
        return $form
            ->columns(1)
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    // Index unik database ikut menghitung badan usaha di Sampah, jadi validasi pun begitu.
                    ->unique(ignoreRecord: true),
                TextInput::make('format')
                    ->required()
                    ->maxLength(255),
                Select::make('color')
                    ->label('Color')
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
                    ->searchable()
                    ->required(),
                Section::make('Identitas Kop Surat')
                    ->description('Kop surat dibuat otomatis dari data ini. Field yang kosong tidak dicetak.')
                    ->collapsible()
                    ->schema([
                        TextInput::make('legal_name')
                            ->label('Nama Resmi Lengkap')
                            ->helperText('Persis seperti di SK AHU/akta, termasuk PT/CV, Tbk, (Persero). Tulis "PT"/"CV" tanpa titik.')
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null)
                            ->maxLength(255),
                        Select::make('legal_form')
                            ->label('Bentuk Badan Usaha')
                            ->options(LegalForm::class)
                            ->native(false),
                        TextInput::make('short_name')
                            ->label('Singkatan/Brand')
                            ->maxLength(64),
                        Select::make('legal_entity_id')
                            ->label('PT Pemilik (untuk brand/outlet)')
                            ->helperText('Jika diisi, kop memakai data PT ini dengan baris "Outlet: ...".')
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
                            // Satu tingkat saja: PT yang sudah punya outlet tidak boleh punya PT pemilik.
                            ->disabled(fn (?BusinessEntity $record): bool => (bool) $record?->outlets()->exists())
                            ->hint(fn (?BusinessEntity $record): ?string => $record?->outlets()->exists() ? 'Badan usaha ini sudah menjadi PT pemilik outlet lain.' : null),
                    ]),
                Section::make('Alamat & Kontak')
                    ->collapsible()
                    ->schema([
                        Textarea::make('address')
                            ->label('Alamat Kantor Pusat/Domisili')
                            ->helperText('Alamat tempat kedudukan sesuai akta. Maksimal 2 baris supaya kop tidak terlalu tinggi.')
                            ->rows(2)
                            ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                if (is_string($value) && count(preg_split('/\R/', trim($value))) > 2) {
                                    $fail('Alamat maksimal 2 baris.');
                                }
                            })
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null)
                            ->maxLength(200),
                        TextInput::make('city')
                            ->label('Kota')
                            ->helperText('Tidak dicetak di kop; untuk baris "Kota, tanggal".')
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Telepon')
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null)
                            ->maxLength(50),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                    ]),
                Section::make('Legalitas')
                    ->description('Disimpan sebagai data badan usaha, tidak dicetak di kop.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextInput::make('npwp')
                            ->label('NPWP')
                            ->helperText('16 digit. NPWP lama 15 digit otomatis diawali 0.')
                            ->rule(fn (): \Closure => static::digitsRule([15, 16], 'NPWP harus 15 atau 16 digit.'))
                            ->dehydrateStateUsing(fn (?string $state): ?string => static::normalizeNpwp($state)),
                        TextInput::make('nib')
                            ->label('NIB')
                            ->helperText('13 digit dari OSS.')
                            ->rule(fn (): \Closure => static::digitsRule([13], 'NIB harus 13 digit.'))
                            ->dehydrateStateUsing(fn (?string $state): ?string => static::digitsOnly($state)),
                        TextInput::make('legal_registration_number')
                            ->label('No. Pengesahan Badan Hukum')
                            ->helperText('Contoh: SK AHU-0012345.AH.01.01.Tahun 2020.')
                            ->maxLength(255),
                    ]),
                Section::make('Logo & Kop Gambar')
                    ->collapsible()
                    ->schema([
                        FileUpload::make('logo')
                            ->label('Logo')
                            ->helperText('PNG/JPG, logo saja (bukan kop utuh), latar transparan lebih baik.')
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->maxSize(2048)
                            ->disk('public')
                            ->directory('kopsurat/logo'),
                        FileUpload::make('letterhead')
                            ->image()
                            ->label('Kop Gambar (override)')
                            ->helperText('Jika diisi, gambar ini dipakai sebagai kop dan data kop di atas diabaikan.')
                            ->disk('public')
                            ->directory('kopsurat'),
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
                    ->modalWidth('2xl'),
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
