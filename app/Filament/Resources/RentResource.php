<?php

namespace App\Filament\Resources;

use App\Access\ScopesVisibleRecords;
use App\Enums\ContractStatus;
use App\Filament\Resources\RentResource\Pages;
use App\Filament\Resources\RentResource\RelationManagers\UpdatesRelationManager;
use App\Models\BusinessEntity;
use App\Models\Rent;
use App\Support\LegacyUpload;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/**
 * Perjanjian sewa (sumo: PERJANJIAN SEWA). Perpanjangan ada di relation manager;
 * daftar menampilkan nilai perpanjangan terakhir beserta tagihan dan tanggal
 * reminder, seperti sumo.
 */
class RentResource extends Resource
{
    use ScopesVisibleRecords;

    protected static ?string $model = Rent::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-home';

    protected static string|\UnitEnum|null $navigationGroup = 'Asuransi & Sewa';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'rent_code';

    public const AVAILABILITY_OPTIONS = ['ADA' => 'ADA', 'ADA (SCAN)' => 'ADA (SCAN)', 'TIDAK ADA' => 'TIDAK ADA'];

    /** Sumo: jpeg, png, jpg, pdf maksimal 10 MB. */
    public const FILE_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];

    public const FILE_MAX_KB = 10240;

    public static function getModelLabel(): string
    {
        return __('Perjanjian Sewa');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Perjanjian Sewa');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                Section::make('Kontrak')
                    ->columns(2)
                    ->schema([
                        TextInput::make('rent_code')
                            ->label('Kode Sewa')
                            ->placeholder('Otomatis, mis. '.Rent::CODE_PREFIX.'0001')
                            ->helperText('Kosongkan untuk kode otomatis seperti di sumo.')
                            ->maxLength(255),
                        Select::make('business_entity_id')
                            ->label('Badan Usaha')
                            ->options(fn (?Rent $record): array => BusinessEntity::optionsFor(auth()->user(), $record?->business_entity_id))
                            ->searchable(),
                        TextInput::make('rented_detail')->label('Objek Sewa')->required()->maxLength(255),
                        TextInput::make('rented_address')->label('Alamat')->required()->maxLength(255),
                        TextInput::make('first_party')->label('Pihak Pertama')->required()->maxLength(255),
                        TextInput::make('second_party')->label('Pihak Kedua')->required()->maxLength(255),
                        DatePicker::make('join_date')->label('Tanggal Mulai')->native(false)->required(),
                        DatePicker::make('expired_date')->label('Tanggal Berakhir')->native(false)->required()->afterOrEqual('join_date'),
                        Select::make('status')->label('Status')->options(ContractStatus::class)->default(ContractStatus::Running->value)->required(),
                        TextInput::make('month_before_reminder')
                            ->label('Pengingat (bulan sebelum)')
                            ->helperText('Baris sewa diwarnai sejak tanggal berakhir dikurangi bulan ini.')
                            ->numeric()
                            ->minValue(0),
                    ]),
                Section::make('Pembayaran')
                    ->columns(3)
                    ->schema([
                        TextInput::make('rent_per_year')->label('Sewa per Tahun')->numeric()->prefix('Rp')->required(),
                        TextInput::make('cvcs_fund')->label('Dana PKP (CV.CS)')->numeric()->prefix('Rp'),
                        TextInput::make('online_fund')->label('Dana NON-PKP (Online)')->numeric()->prefix('Rp'),
                    ]),
                Section::make('Dokumen')
                    ->columns(2)
                    ->schema([
                        Select::make('deduction_evidence')->label('Bukti Potong')->options(self::AVAILABILITY_OPTIONS),
                        static::fileUpload('deduction_evidence_file', 'File Bukti Potong'),
                        Select::make('document')->label('Dokumen Perjanjian')->options(self::AVAILABILITY_OPTIONS),
                        static::fileUpload('document_file', 'File Dokumen'),
                        static::fileUpload('payment_evidence_file', 'File Bukti Pembayaran'),
                        TextInput::make('reminder_phones')
                            ->label('Nomor WA Pengingat')
                            ->helperText('Pisahkan dengan koma. Kosong = memakai nomor global di Notifikasi WA.')
                            ->maxLength(255),
                        Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Schema $infolist): Schema
    {
        $money = fn (TextEntry $entry): TextEntry => $entry->money('IDR', decimalPlaces: 0)->placeholder('-');

        return $infolist
            ->columns(1)
            ->schema([
                Section::make('Kontrak')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('rent_code')->label('Kode Sewa')->weight('bold'),
                        TextEntry::make('rented_detail')->label('Objek Sewa'),
                        TextEntry::make('rented_address')->label('Alamat')->placeholder('-'),
                        TextEntry::make('first_party')->label('Pihak Pertama'),
                        TextEntry::make('second_party')->label('Pihak Kedua'),
                        TextEntry::make('businessEntity.name')->label('Badan Usaha')->placeholder('-'),
                        TextEntry::make('join_date')->label('Tanggal Mulai')->date('d/m/Y'),
                        TextEntry::make('expired_date')->label('Tanggal Berakhir')->date('d/m/Y')->color(fn (Rent $record): ?string => $record->dueColor()),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('reminder_date')->label('Tanggal Reminder')
                            ->state(fn (Rent $record): ?string => $record->reminderDate()?->format('d/m/Y'))
                            ->helperText(fn (Rent $record): string => ($record->month_before_reminder ?: 0).' bulan sebelum berakhir')
                            ->placeholder('-'),
                        TextEntry::make('deduction_evidence')->label('Bukti Potong')->placeholder('-'),
                        TextEntry::make('document')->label('Dokumen Perjanjian')->placeholder('-'),
                        TextEntry::make('reminder_phones')->label('Nomor WA Pengingat')->placeholder('Nomor global'),
                        TextEntry::make('notes')->label('Catatan')->placeholder('-')->columnSpanFull(),
                    ]),
                Section::make('Tagihan Kontrak Awal')
                    ->description('Jumlah tahun = hari sewa / 366 dibulatkan ke atas; total = tahun x sewa per tahun.')
                    ->schema([
                        Grid::make(3)->schema([
                            $money(TextEntry::make('rent_per_year')->label('Sewa per Tahun')),
                            $money(TextEntry::make('billing_total')->label('Total Tagihan')->state(fn (Rent $record): int => $record->billingSummary()['total'])),
                            TextEntry::make('billing_years')->label('Jumlah Tahun')->state(fn (Rent $record): string => $record->billingSummary()['years'].' tahun'),
                            $money(TextEntry::make('cvcs_fund')->label('Dana PKP (CV.CS)')),
                            $money(TextEntry::make('online_fund')->label('Dana NON-PKP (Online)')),
                            $money(TextEntry::make('billing_remaining')->label('Sisa Tagihan')->state(fn (Rent $record): int => $record->billingSummary()['remaining'])),
                            TextEntry::make('billing_status')->label('Status Tagihan')
                                ->state(fn (Rent $record): string => $record->billingSummary()['status'])
                                ->badge()
                                ->color(fn (string $state): string => Rent::billingColor($state)),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $money = fn (TextColumn $column): TextColumn => $column->money('IDR', decimalPlaces: 0)->placeholder('-');

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('updates')->withMax('updates', 'expired_date'))
            ->columns([
                TextColumn::make('rent_code')->label('Kode')
                    ->state(fn (Rent $record): string => (string) $record->effective('rent_code'))
                    ->description(fn (Rent $record): ?string => $record->latestUpdate() !== null ? 'Awal: '.$record->rent_code : null)
                    ->searchable(query: fn (Builder $query, string $search): Builder => static::applySearch($query, $search))
                    // Urut sesuai nilai yang tampil: kode perpanjangan terakhir, bukan kode induk.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        'COALESCE((SELECT u.rent_code FROM rent_updates u WHERE u.rent_id = rents.id AND u.deleted_at IS NULL ORDER BY u.expired_date DESC, u.id DESC LIMIT 1), rents.rent_code) '
                        .($direction === 'desc' ? 'desc' : 'asc'),
                    ))
                    ->weight('bold'),
                TextColumn::make('rented_detail')->label('Objek Sewa')->description(fn (Rent $record): ?string => $record->rented_address)->wrap(),
                TextColumn::make('first_party')->label('Pihak Pertama')->state(fn (Rent $record): mixed => $record->effective('first_party'))->toggleable(),
                TextColumn::make('second_party')->label('Pihak Kedua')->state(fn (Rent $record): mixed => $record->effective('second_party'))->toggleable(),
                TextColumn::make('effective_join_date')->label('Mulai')->state(fn (Rent $record): ?string => $record->effective('join_date')?->format('d/m/Y'))->toggleable(),
                TextColumn::make('effective_expired_date')
                    ->label('Berakhir')
                    ->state(fn (Rent $record): ?string => $record->effectiveExpiredDate()?->format('d/m/Y'))
                    ->description(fn (Rent $record): ?string => $record->updates->isNotEmpty() ? $record->updates->count().' perpanjangan' : null)
                    ->color(fn (Rent $record): ?string => $record->effectiveDueColor())
                    ->weight('bold')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => static::orderByEffectiveExpiry($query, $direction)),
                TextColumn::make('reminder')->label('Reminder')
                    ->state(fn (Rent $record): string => ((int) $record->effective('month_before_reminder')).' bulan')
                    ->description(fn (Rent $record): ?string => $record->effectiveReminderDate()?->format('d/m/Y'))
                    ->toggleable(),
                $money(TextColumn::make('rent_per_year')->label('Sewa / Tahun')->state(fn (Rent $record): mixed => $record->effective('rent_per_year'))),
                $money(TextColumn::make('billing_total')->label('Total Tagihan')->state(fn (Rent $record): int => $record->effectiveBillingSummary()['total']))->toggleable(),
                $money(TextColumn::make('cvcs_fund')->label('Dana PKP')->state(fn (Rent $record): mixed => $record->effective('cvcs_fund')))->toggleable(),
                $money(TextColumn::make('online_fund')->label('Dana NON-PKP')->state(fn (Rent $record): mixed => $record->effective('online_fund')))->toggleable(),
                $money(TextColumn::make('billing_remaining')->label('Sisa Tagihan')->state(fn (Rent $record): int => $record->effectiveBillingSummary()['remaining']))->toggleable(),
                TextColumn::make('billing')->label('Status Tagihan')
                    ->state(fn (Rent $record): string => $record->effectiveBillingSummary()['status'])
                    ->badge()
                    ->color(fn (string $state): string => Rent::billingColor($state)),
                TextColumn::make('deduction_evidence')->label('Bukti Potong')->state(fn (Rent $record): mixed => $record->effective('deduction_evidence'))->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('document')->label('Berkas')->state(fn (Rent $record): mixed => $record->effective('document'))->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_evidence_file')->label('Bukti Bayar')
                    ->state(fn (Rent $record): string => filled($record->effective('payment_evidence_file')) ? 'Lihat Dokumen' : 'Tidak ada file')
                    ->url(fn (Rent $record): ?string => LegacyUpload::url(LegacyUpload::RENT_DIR, $record->effective('payment_evidence_file')), shouldOpenInNewTab: true)
                    ->color(fn (Rent $record): string => filled($record->effective('payment_evidence_file')) ? 'primary' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('effective_status')->label('Status')->state(fn (Rent $record): ?ContractStatus => $record->effectiveStatus())->badge(),
                TextColumn::make('notes')->label('Catatan')->state(fn (Rent $record): mixed => $record->effective('notes'))->limit(30)->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort(fn (Builder $query, string $direction): Builder => static::orderByEffectiveExpiry($query, $direction))
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(ContractStatus::class)
                    // Sumo: cocok bila status kontrak atau salah satu perpanjangannya sama.
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where(fn (Builder $matches) => $matches
                            ->where('rents.status', $data['value'])
                            ->orWhereHas('updates', fn (Builder $updates) => $updates->where('status', $data['value'])))
                        : $query),
                Filter::make('active')
                    ->label('Masih berjalan')
                    ->toggle()
                    // Sumo menyembunyikan sewa tutup/refund secara default.
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->activeContracts()),
                Filter::make('due_soon')
                    ->label('Berakhir ≤ 30 hari')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->activeContracts()->dueWithin(30)),
            ])
            ->recordActions([
                RestoreAction::make(),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
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

    /**
     * Unggahan dokumen sewa ke folder lama Rent_File dengan batas seperti sumo.
     */
    public static function fileUpload(string $name, string $label): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->directory(LegacyUpload::RENT_DIR)
            ->acceptedFileTypes(self::FILE_TYPES)
            ->maxSize(self::FILE_MAX_KB)
            ->openable()
            ->formatStateUsing(fn (?string $state): ?string => LegacyUpload::path(LegacyUpload::RENT_DIR, $state));
    }

    /**
     * Urut berdasarkan tanggal berakhir efektif (sumo: COALESCE(MAX(update.expired_date), expired_date)).
     * Memerlukan withMax('updates', 'expired_date') pada query tabel.
     */
    public static function orderByEffectiveExpiry(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderByRaw('COALESCE(updates_max_expired_date, rents.expired_date) '.($direction === 'desc' ? 'desc' : 'asc'));
    }

    /**
     * Pencarian seperti sumo: kode (termasuk perpanjangan), objek, alamat, pihak,
     * dan catatan (termasuk catatan perpanjangan).
     */
    private static function applySearch(Builder $query, string $search): Builder
    {
        $like = '%'.$search.'%';

        return $query->where(fn (Builder $matches) => $matches
            ->where('rents.rent_code', 'like', $like)
            ->orWhere('rents.rented_detail', 'like', $like)
            ->orWhere('rents.rented_address', 'like', $like)
            ->orWhere('rents.first_party', 'like', $like)
            ->orWhere('rents.second_party', 'like', $like)
            ->orWhere('rents.notes', 'like', $like)
            ->orWhereHas('updates', fn (Builder $updates) => $updates
                ->where('rent_code', 'like', $like)
                ->orWhere('notes', 'like', $like)));
    }
}
