<?php

namespace App\Filament\Resources;

use App\Enums\ContractStatus;
use App\Filament\Resources\InsuranceResource\Pages;
use App\Filament\Resources\InsuranceResource\RelationManagers\UpdatesRelationManager;
use App\Models\BusinessEntity;
use App\Models\Insurance;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/**
 * Polis asuransi (sumo: ASURANSI > Data Asuransi). Riwayat pembaharuan ada di
 * relation manager; jatuh tempo efektif mengikuti pembaharuan terakhir.
 */
class InsuranceResource extends Resource
{
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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $viewer = auth()->user();

        if ($viewer instanceof User && ! $viewer->hasUnrestrictedBusinessEntityAccess()) {
            $query->where(fn (Builder $scoped) => $scoped
                ->whereNull('insurances.business_entity_id')
                ->orWhereIn('insurances.business_entity_id', $viewer->accessibleBusinessEntityIds()));
        }

        return $query;
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
                        Select::make('insurance_category_id')->label('Kategori')->relationship('category', 'name')->preload()->searchable(),
                        Select::make('insurance_scope_id')->label('Cakupan')->relationship('scope', 'name')->preload()->searchable(),
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
                        Select::make('stock_provider_id')->label('Provider Asuransi Stok')->relationship('stockProvider', 'name')->preload()->searchable(),
                        Select::make('building_provider_id')->label('Provider Asuransi Bangunan')->relationship('buildingProvider', 'name')->preload()->searchable(),
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
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['category', 'scope', 'stockProvider', 'buildingProvider', 'updates']))
            ->columns([
                TextColumn::make('policy_number')->label('Nomor Polis')->searchable()->sortable()->weight('bold'),
                TextColumn::make('insured_name')->label('Tertanggung')->searchable()->sortable()->description(fn (Insurance $record): ?string => $record->warehouse_code),
                TextColumn::make('category.name')->label('Kategori')->placeholder('-')->toggleable(),
                TextColumn::make('scope.name')->label('Cakupan')->placeholder('-')->toggleable(),
                TextColumn::make('stockProvider.name')->label('Provider Stok')->placeholder('-')->toggleable(),
                TextColumn::make('stock_premium')->label('Premi Stok')->money('IDR', decimalPlaces: 0)->placeholder('-')->toggleable(),
                TextColumn::make('building_premium')->label('Premi Bangunan')->money('IDR', decimalPlaces: 0)->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('join_date')->label('Mulai')->date('d/m/Y')->sortable()->toggleable(),
                TextColumn::make('effective_expired_date')
                    ->label('Jatuh Tempo')
                    ->state(fn (Insurance $record): ?string => $record->effectiveExpiredDate()?->format('d/m/Y'))
                    ->description(fn (Insurance $record): ?string => $record->updates->isNotEmpty() ? $record->updates->count().' pembaharuan' : null)
                    ->color(fn (Insurance $record): ?string => static::dueColor($record->effectiveExpiredDate())),
                TextColumn::make('effective_status')->label('Status')->state(fn (Insurance $record): ?ContractStatus => $record->effectiveStatus())->badge(),
            ])
            ->defaultSort('expired_date')
            ->filters([
                SelectFilter::make('status')->label('Status')->options(ContractStatus::class),
                SelectFilter::make('insurance_category_id')->label('Kategori')->relationship('category', 'name')->preload(),
                SelectFilter::make('stock_provider_id')->label('Provider Stok')->relationship('stockProvider', 'name')->preload(),
                Filter::make('active')
                    ->label('Masih berjalan')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->activeContracts()),
                Filter::make('due_soon')
                    ->label('Jatuh tempo ≤ 30 hari')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->activeContracts()->where(function (Builder $due): void {
                        $limit = now()->addDays(30)->toDateString();
                        $due->whereHas('updates', fn (Builder $updates) => $updates->whereDate('expired_date', '<=', $limit))
                            ->orWhere(fn (Builder $base) => $base->whereDoesntHave('updates')->whereDate('expired_date', '<=', $limit));
                    })),
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

    public static function dueColor(?CarbonInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        if ($date->isPast()) {
            return 'danger';
        }

        return $date->diffInDays(now()) <= 30 ? 'warning' : null;
    }
}
