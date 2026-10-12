<?php

namespace App\Filament\Resources;

use App\Enums\ContractStatus;
use App\Filament\Resources\RentResource\Pages;
use App\Filament\Resources\RentResource\RelationManagers\UpdatesRelationManager;
use App\Models\BusinessEntity;
use App\Models\Rent;
use App\Models\User;
use App\Support\LegacyUpload;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/**
 * Perjanjian sewa (sumo: PERJANJIAN SEWA). Perpanjangan ada di relation manager.
 */
class RentResource extends Resource
{
    protected static ?string $model = Rent::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-home';

    protected static string|\UnitEnum|null $navigationGroup = 'Asuransi & Sewa';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'rent_code';

    public const AVAILABILITY_OPTIONS = ['ADA' => 'ADA', 'ADA (SCAN)' => 'ADA (SCAN)', 'TIDAK ADA' => 'TIDAK ADA'];

    public static function getModelLabel(): string
    {
        return __('Perjanjian Sewa');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Perjanjian Sewa');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $viewer = auth()->user();

        if ($viewer instanceof User && ! $viewer->hasUnrestrictedBusinessEntityAccess()) {
            $query->where(fn (Builder $scoped) => $scoped
                ->whereNull('rents.business_entity_id')
                ->orWhereIn('rents.business_entity_id', $viewer->accessibleBusinessEntityIds()));
        }

        return $query;
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                Section::make('Kontrak')
                    ->columns(2)
                    ->schema([
                        TextInput::make('rent_code')->label('Kode Sewa')->required()->maxLength(255),
                        Select::make('business_entity_id')
                            ->label('Badan Usaha')
                            ->options(fn (?Rent $record): array => BusinessEntity::optionsFor(auth()->user(), $record?->business_entity_id))
                            ->searchable(),
                        TextInput::make('rented_detail')->label('Objek Sewa')->required()->maxLength(255),
                        TextInput::make('rented_address')->label('Alamat')->maxLength(255),
                        TextInput::make('first_party')->label('Pihak Pertama')->required()->maxLength(255),
                        TextInput::make('second_party')->label('Pihak Kedua')->required()->maxLength(255),
                        DatePicker::make('join_date')->label('Tanggal Mulai')->native(false)->required(),
                        DatePicker::make('expired_date')->label('Tanggal Berakhir')->native(false)->required()->afterOrEqual('join_date'),
                        Select::make('status')->label('Status')->options(ContractStatus::class)->default(ContractStatus::Running->value)->required(),
                        TextInput::make('month_before_reminder')->label('Pengingat (bulan sebelum)')->maxLength(255),
                    ]),
                Section::make('Pembayaran')
                    ->columns(3)
                    ->schema([
                        TextInput::make('rent_per_year')->label('Sewa per Tahun')->numeric()->prefix('Rp'),
                        TextInput::make('cvcs_fund')->label('Dana CVCS')->numeric()->prefix('Rp'),
                        TextInput::make('online_fund')->label('Dana Online')->numeric()->prefix('Rp'),
                    ]),
                Section::make('Dokumen')
                    ->columns(2)
                    ->schema([
                        Select::make('deduction_evidence')->label('Bukti Potong')->options(self::AVAILABILITY_OPTIONS),
                        FileUpload::make('deduction_evidence_file')->label('File Bukti Potong')->directory(LegacyUpload::RENT_DIR)->maxSize(5120)->openable()
                            ->formatStateUsing(fn (?string $state): ?string => LegacyUpload::path(LegacyUpload::RENT_DIR, $state)),
                        Select::make('document')->label('Dokumen Perjanjian')->options(self::AVAILABILITY_OPTIONS),
                        FileUpload::make('document_file')->label('File Dokumen')->directory(LegacyUpload::RENT_DIR)->maxSize(5120)->openable()
                            ->formatStateUsing(fn (?string $state): ?string => LegacyUpload::path(LegacyUpload::RENT_DIR, $state)),
                        FileUpload::make('payment_evidence_file')->label('File Bukti Pembayaran')->directory(LegacyUpload::RENT_DIR)->maxSize(5120)->openable()
                            ->formatStateUsing(fn (?string $state): ?string => LegacyUpload::path(LegacyUpload::RENT_DIR, $state)),
                        TextInput::make('reminder_phones')
                            ->label('Nomor WA Pengingat')
                            ->helperText('Pisahkan dengan koma. Kosong = memakai nomor global di Notifikasi WA.')
                            ->maxLength(255),
                        Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('updates'))
            ->columns([
                TextColumn::make('rent_code')->label('Kode')->searchable()->sortable()->weight('bold'),
                TextColumn::make('rented_detail')->label('Objek Sewa')->searchable()->description(fn (Rent $record): ?string => $record->rented_address)->wrap(),
                TextColumn::make('first_party')->label('Pihak Pertama')->searchable()->toggleable(),
                TextColumn::make('second_party')->label('Pihak Kedua')->searchable()->toggleable(),
                TextColumn::make('rent_per_year')->label('Sewa / Tahun')->money('IDR', decimalPlaces: 0)->placeholder('-'),
                TextColumn::make('billing')->label('Tagihan')
                    ->state(fn (Rent $record): string => $record->billingSummary()['status'])
                    ->badge()
                    ->color(fn (Rent $record): string => match ($record->billingSummary()['status']) {
                        'LUNAS' => 'success',
                        'LEBIH' => 'info',
                        default => 'warning',
                    }),
                TextColumn::make('join_date')->label('Mulai')->date('d/m/Y')->sortable()->toggleable(),
                TextColumn::make('effective_expired_date')
                    ->label('Jatuh Tempo')
                    ->state(fn (Rent $record): ?string => $record->effectiveExpiredDate()?->format('d/m/Y'))
                    ->description(fn (Rent $record): ?string => $record->updates->isNotEmpty() ? $record->updates->count().' perpanjangan' : null)
                    ->color(fn (Rent $record): ?string => InsuranceResource::dueColor($record->effectiveExpiredDate())),
                TextColumn::make('effective_status')->label('Status')->state(fn (Rent $record): ?ContractStatus => $record->effectiveStatus())->badge(),
            ])
            ->defaultSort('expired_date')
            ->filters([
                SelectFilter::make('status')->label('Status')->options(ContractStatus::class),
                Filter::make('active')->label('Masih berjalan')->toggle()->query(fn (Builder $query): Builder => $query->activeContracts()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->label('Ekspor Excel')
                        ->exports([ExcelExport::make('sewa')->fromTable()->withFilename('perjanjian-sewa-'.now()->format('Ymd-His'))])
                        ->visible(fn (): bool => auth()->user()?->can('export', Rent::class) ?? false),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            UpdatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRents::route('/'),
            'create' => Pages\CreateRent::route('/create'),
            'view' => Pages\ViewRent::route('/{record}'),
            'edit' => Pages\EditRent::route('/{record}/edit'),
        ];
    }
}
