<?php

namespace App\Filament\Resources;

use App\Access\ScopesVisibleRecords;
use App\Enums\ContractStatus;
use App\Filament\Resources\InsuranceResource\Pages;
use App\Filament\Resources\InsuranceResource\RelationManagers\UpdatesRelationManager;
use App\Models\BusinessEntity;
use App\Models\Insurance;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
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
 * Polis asuransi (sumo: ASURANSI > Data Asuransi). Riwayat pembaharuan ada di
 * relation manager; daftar menampilkan nilai pembaharuan terakhir (nomor polis,
 * provider, nilai, status, catatan) dan jatuh tempo efektif, seperti sumo.
 */
class InsuranceResource extends Resource
{
    use ScopesVisibleRecords;

    protected static ?string $model = Insurance::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Asuransi & Sewa';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'policy_number';

    public static function getModelLabel(): string
    {
        return __('Asuransi');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Asuransi');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                Section::make('Polis')
                    ->columns(2)
                    ->schema([
                        TextInput::make('policy_number')->label('Nomor Polis')->required()->maxLength(255),
                        TextInput::make('insured_name')->label('Nama Tertanggung')->required()->maxLength(255),
                        TextInput::make('warehouse_code')->label('Kode Gudang')->maxLength(255),
                        Select::make('insurance_category_id')->label('Kategori')->relationship('category', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutTrashed())->preload()->searchable()->required(),
                        Select::make('insurance_scope_id')->label('Cakupan')->relationship('scope', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutTrashed())->preload()->searchable()->required(),
                        Select::make('business_entity_id')
                            ->label('Badan Usaha')
                            ->options(fn (?Insurance $record): array => BusinessEntity::optionsFor(auth()->user(), $record?->business_entity_id))
                            ->searchable(),
                        TextInput::make('insured_address')->label('Alamat Tertanggung')->maxLength(255)->columnSpanFull(),
                        TextInput::make('insured_detail')->label('Detail Tertanggung')->maxLength(255)->columnSpanFull(),
                        TextInput::make('risk_address')->label('Alamat Risiko')->maxLength(255)->columnSpanFull(),
                    ]),
                Section::make('Nilai dan Premi')
                    ->columns(2)
                    ->schema([
                        Select::make('stock_provider_id')->label('Provider Asuransi Stok')->relationship('stockProvider', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutTrashed())->preload()->searchable(),
                        Select::make('building_provider_id')->label('Provider Asuransi Bangunan')->relationship('buildingProvider', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutTrashed())->preload()->searchable(),
                        TextInput::make('stock_worth')->label('Nilai Stok')->numeric()->prefix('Rp'),
                        TextInput::make('building_worth')->label('Nilai Bangunan')->numeric()->prefix('Rp'),
                        TextInput::make('actual_stock_worth')->label('Nilai Stok Aktual')->numeric()->prefix('Rp'),
                        TextInput::make('extension_of_policy')->label('Perluasan Polis')->maxLength(255),
                        TextInput::make('stock_premium')->label('Premi Stok')->numeric()->prefix('Rp'),
                        TextInput::make('building_premium')->label('Premi Bangunan')->numeric()->prefix('Rp'),
                    ]),
                Section::make('Periode dan Status')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('join_date')->label('Tanggal Mulai')->native(false)->required(),
                        DatePicker::make('expired_date')->label('Tanggal Berakhir')->native(false)->required()->afterOrEqual('join_date'),
                        Select::make('status')->label('Status')->options(ContractStatus::class)->default(ContractStatus::Running->value)->required(),
                        TextInput::make('payment_evidence')->label('Bukti Pembayaran')->maxLength(255),
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
        $money = fn (TextColumn $column): TextColumn => $column->money('IDR', decimalPlaces: 0)->placeholder('-');

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['category', 'scope', 'stockProvider', 'buildingProvider', 'updates.stockProvider', 'updates.buildingProvider'])
                ->withMax('updates', 'expired_date'))
            ->columns([
                TextColumn::make('policy_number')->label('Nomor Polis')
                    ->state(fn (Insurance $record): string => (string) $record->effective('policy_number'))
                    ->description(fn (Insurance $record): ?string => $record->latestUpdate() !== null && $record->latestUpdate()->policy_number !== $record->policy_number ? 'Awal: '.$record->policy_number : null)
                    ->searchable(query: fn (Builder $query, string $search): Builder => static::applySearch($query, $search))
                    // Urut sesuai nilai yang tampil: nomor pembaharuan terakhir, bukan nomor induk.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        'COALESCE((SELECT u.policy_number FROM insurance_updates u WHERE u.insurance_id = insurances.id AND u.deleted_at IS NULL ORDER BY u.expired_date DESC, u.id DESC LIMIT 1), insurances.policy_number) '
                        .($direction === 'desc' ? 'desc' : 'asc'),
                    ))
                    ->weight('bold'),
                TextColumn::make('insured_name')->label('Tertanggung')->sortable()->description(fn (Insurance $record): ?string => $record->warehouse_code),
                TextColumn::make('insured_detail')->label('Detail')->limit(20)->placeholder('-')->toggleable(),
                TextColumn::make('category.name')->label('Kategori')->placeholder('-')->toggleable(),
                TextColumn::make('scope.name')->label('Cakupan')->placeholder('-')->toggleable(),
                TextColumn::make('stock_provider')->label('Asuransi Stok')->state(fn (Insurance $record): ?string => $record->effectiveRow()->stockProvider?->name)->placeholder('-')->toggleable(),
                $money(TextColumn::make('stock_worth')->label('Nilai Stok')->state(fn (Insurance $record): mixed => $record->effective('stock_worth')))->toggleable(),
                $money(TextColumn::make('actual_stock_worth')->label('Aktual Stok')->state(fn (Insurance $record): mixed => $record->effective('actual_stock_worth')))->toggleable(),
                $money(TextColumn::make('stock_difference')->label('Selisih')->state(fn (Insurance $record): ?int => $record->effectiveRow()->stockDifference()))->color('danger')->toggleable(),
                TextColumn::make('stock_status')->label('Status Stok')
                    ->state(fn (Insurance $record): ?string => $record->effectiveRow()->stockStatus())
                    ->badge()
                    ->color(fn (?string $state): string => Insurance::stockStatusColor($state))
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('building_provider')->label('Asuransi Bangunan')->state(fn (Insurance $record): ?string => $record->effectiveRow()->buildingProvider?->name)->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                $money(TextColumn::make('building_worth')->label('Nilai Bangunan')->state(fn (Insurance $record): mixed => $record->effective('building_worth')))->toggleable(isToggledHiddenByDefault: true),
                $money(TextColumn::make('stock_premium')->label('Premi Stok')->state(fn (Insurance $record): mixed => $record->effective('stock_premium')))->toggleable(isToggledHiddenByDefault: true),
                $money(TextColumn::make('building_premium')->label('Premi Bangunan')->state(fn (Insurance $record): mixed => $record->effective('building_premium')))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('join_date')->label('Mulai')->date('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('effective_expired_date')
                    ->label('Jatuh Tempo')
                    ->state(fn (Insurance $record): ?string => $record->effectiveExpiredDate()?->format('d/m/Y'))
                    ->description(fn (Insurance $record): ?string => $record->updates->isNotEmpty() ? $record->updates->count().' pembaharuan' : null)
                    ->color(fn (Insurance $record): ?string => $record->effectiveDueColor())
                    ->weight('bold')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => static::orderByEffectiveExpiry($query, $direction)),
                TextColumn::make('effective_status')->label('Status')->state(fn (Insurance $record): ?ContractStatus => $record->effectiveStatus())->badge(),
                TextColumn::make('notes')->label('Catatan')->state(fn (Insurance $record): mixed => $record->effective('notes'))->limit(30)->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort(fn (Builder $query, string $direction): Builder => static::orderByEffectiveExpiry($query, $direction))
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(ContractStatus::class)
                    // Sumo: cocok bila status polis atau salah satu pembaharuannya sama.
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where(fn (Builder $matches) => $matches
                            ->where('insurances.status', $data['value'])
                            ->orWhereHas('updates', fn (Builder $updates) => $updates->where('status', $data['value'])))
                        : $query),
                SelectFilter::make('insurance_category_id')->label('Kategori')->relationship('category', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutTrashed())->preload(),
                SelectFilter::make('stock_provider_id')->label('Provider Stok')->relationship('stockProvider', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutTrashed())->preload(),
                Filter::make('active')
                    ->label('Masih berjalan')
                    ->toggle()
                    // Sumo menyembunyikan polis tutup/refund secara default.
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->activeContracts()),
                Filter::make('due_soon')
                    ->label('Jatuh tempo ≤ 30 hari')
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
                        ->exports([ExcelExport::make('asuransi')->fromTable()->withFilename('asuransi-'.now()->format('Ymd-His'))])
                        ->visible(fn (): bool => auth()->user()?->can('export', Insurance::class) ?? false),
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
            'index' => Pages\ListInsurances::route('/'),
            'create' => Pages\CreateInsurance::route('/create'),
            'view' => Pages\ViewInsurance::route('/{record}'),
            'edit' => Pages\EditInsurance::route('/{record}/edit'),
        ];
    }

    /**
     * Urut berdasarkan jatuh tempo efektif (sumo: COALESCE(MAX(update.expired_date), expired_date)).
     * Memerlukan withMax('updates', 'expired_date') pada query tabel.
     */
    public static function orderByEffectiveExpiry(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderByRaw('COALESCE(updates_max_expired_date, insurances.expired_date) '.($direction === 'desc' ? 'desc' : 'asc'));
    }

    /**
     * Pencarian seperti sumo: nomor polis (termasuk pembaharuan), tertanggung,
     * kode gudang, alamat tertanggung, detail, dan alamat risiko.
     */
    private static function applySearch(Builder $query, string $search): Builder
    {
        $like = '%'.$search.'%';

        return $query->where(fn (Builder $matches) => $matches
            ->where('insurances.policy_number', 'like', $like)
            ->orWhere('insurances.insured_name', 'like', $like)
            ->orWhere('insurances.warehouse_code', 'like', $like)
            ->orWhere('insurances.insured_address', 'like', $like)
            ->orWhere('insurances.insured_detail', 'like', $like)
            ->orWhere('insurances.risk_address', 'like', $like)
            ->orWhereHas('updates', fn (Builder $updates) => $updates->where('policy_number', 'like', $like)));
    }
}
