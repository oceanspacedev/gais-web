<?php

namespace App\Filament\Resources;

use App\Access\ScopesVisibleRecords;
use App\Enums\ContractStatus;
use App\Filament\Resources\VehicleInsuranceResource\Pages;
use App\Filament\Resources\VehicleInsuranceResource\RelationManagers\UpdatesRelationManager;
use App\Models\Asset;
use App\Models\BusinessEntity;
use App\Models\Category;
use App\Models\VehicleInsurance;
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
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/**
 * Polis asuransi kendaraan, satu polis per aset MOBIL/MOTOR. Dipisah dari
 * Asuransi Bangunan (stok dan bangunan gudang); field mengikuti menu asuransi
 * yang sudah ada, tanpa isian khusus gudang. Riwayat pembaharuan ada di relation
 * manager; daftar menampilkan nilai pembaharuan terakhir dan jatuh tempo efektif.
 */
class VehicleInsuranceResource extends Resource
{
    use ScopesVisibleRecords;

    protected static ?string $model = VehicleInsurance::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-truck';

    protected static string|\UnitEnum|null $navigationGroup = 'Asuransi & Sewa';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'policy_number';

    protected static ?string $slug = 'vehicle-insurances';

    public static function getModelLabel(): string
    {
        return __('Asuransi Kendaraan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Asuransi Kendaraan');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                Section::make('Polis')
                    ->icon('heroicon-o-document-text')
                    ->columns(['default' => 1, 'md' => 2, 'lg' => 3])
                    ->schema([
                        Select::make('asset_id')
                            ->label('Kendaraan')
                            ->helperText('Cari dengan plat nomor, nama, atau nomor aset.')
                            ->options(fn (?VehicleInsurance $record): array => static::vehicleOptions(null, $record?->asset_id))
                            ->getSearchResultsUsing(fn (string $search): array => static::vehicleOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => Asset::query()->withTrashed()->with(['attributes.customAttribute', 'businessEntity:id,name'])->find($value)?->optionLabel())
                            ->searchable()
                            ->required()
                            ->live()
                            // Badan usaha mengikuti aset bila belum diisi.
                            ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                if ($state !== null && blank($get('business_entity_id'))) {
                                    $set('business_entity_id', Asset::query()->whereKey($state)->value('business_entity_id'));
                                }
                            })
                            ->columnSpan(['md' => 2]),
                        TextInput::make('policy_number')->label('Nomor Polis')->required()->maxLength(255),
                        TextInput::make('insured_name')->label('Nama Tertanggung')->required()->maxLength(255),
                        Select::make('insurance_category_id')->label('Kategori')->relationship('category', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutTrashed())->preload()->searchable(),
                        Select::make('business_entity_id')
                            ->label('Badan Usaha')
                            ->options(fn (?VehicleInsurance $record): array => BusinessEntity::optionsFor(auth()->user(), $record?->business_entity_id))
                            ->searchable(),
                    ]),
                Section::make('Nilai dan Premi')
                    ->icon('heroicon-o-banknotes')
                    ->columns(['default' => 1, 'md' => 2, 'lg' => 4])
                    ->schema([
                        Select::make('insurance_provider_id')->label('Provider Asuransi')->relationship('provider', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutTrashed())->preload()->searchable(),
                        TextInput::make('extension_of_policy')->label('Perluasan / Jenis Pertanggungan')->placeholder('mis. ALL RISK, TLO')->maxLength(255),
                        TextInput::make('insured_worth')->label('Nilai Pertanggungan')->numeric()->prefix('Rp'),
                        TextInput::make('premium')->label('Premi')->numeric()->prefix('Rp'),
                    ]),
                Section::make('Periode dan Status')
                    ->icon('heroicon-o-calendar-days')
                    ->columns(['default' => 1, 'md' => 2, 'lg' => 4])
                    ->schema([
                        DatePicker::make('join_date')->label('Tanggal Mulai')->native(false)->required(),
                        DatePicker::make('expired_date')->label('Tanggal Berakhir')->native(false)->required()->afterOrEqual('join_date'),
                        Select::make('status')->label('Status')->options(ContractStatus::class)->default(ContractStatus::Running->value)->required(),
                        TextInput::make('payment_evidence')->label('Bukti Pembayaran')->maxLength(255),
                        TextInput::make('reminder_phones')
                            ->label('Nomor WA Pengingat')
                            ->helperText('Pisahkan dengan koma. Kosong = memakai nomor global di Notifikasi WA.')
                            ->maxLength(255)
                            ->columnSpan(['md' => 2]),
                        Textarea::make('notes')->label('Catatan')->rows(2)->columnSpan(['md' => 2]),
                    ]),
            ]);
    }

    public static function infolist(Schema $infolist): Schema
    {
        $money = fn (TextEntry $entry): TextEntry => $entry->money('IDR', decimalPlaces: 0)->placeholder('-');

        return $infolist
            ->columns(1)
            ->schema([
                Section::make('Polis')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Grid::make(['default' => 2, 'lg' => 4])->schema([
                            TextEntry::make('asset.name')->label('Kendaraan')->weight(FontWeight::SemiBold)
                                ->helperText(fn (VehicleInsurance $record): ?string => $record->licensePlate())
                                ->url(fn (VehicleInsurance $record): ?string => $record->asset !== null && AssetResource::canView($record->asset) ? AssetResource::getUrl('view', ['record' => $record->asset]) : null)
                                ->placeholder('-')
                                ->columnSpan(['default' => 2]),
                            TextEntry::make('insured_name')->label('Nama Tertanggung')->placeholder('-')->columnSpan(['default' => 2]),
                            TextEntry::make('category.name')->label('Kategori')->placeholder('-'),
                            TextEntry::make('businessEntity.name')->label('Badan Usaha')->placeholder('-'),
                        ]),
                    ]),

                Grid::make(['default' => 1, 'lg' => 2])->schema([
                    Group::make([
                        Section::make('Nilai dan Premi')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('provider.name')->label('Provider')->placeholder('-'),
                                    TextEntry::make('extension_of_policy')->label('Perluasan / Jenis')->placeholder('-'),
                                    $money(TextEntry::make('insured_worth')->label('Nilai Pertanggungan')),
                                    $money(TextEntry::make('premium')->label('Premi'))->weight(FontWeight::SemiBold),
                                ]),
                            ]),
                    ])->extraAttributes(['class' => 'gais-fill-column']),

                    Group::make([
                        Section::make('Periode dan Status')
                            ->icon('heroicon-o-calendar-days')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('join_date')->label('Tanggal Mulai')->date('d M Y')->placeholder('-'),
                                    TextEntry::make('expired_date')->label('Tanggal Berakhir')->date('d M Y')->placeholder('-'),
                                    TextEntry::make('status')->label('Status Polis Awal')->badge(),
                                    TextEntry::make('effective_expired_date')->label('Jatuh Tempo Berlaku')
                                        ->state(fn (VehicleInsurance $record): ?string => $record->effectiveExpiredDate()?->translatedFormat('d M Y'))
                                        ->helperText(fn (VehicleInsurance $record): ?string => $record->latestUpdate() ? 'Dari pembaharuan terakhir' : null)
                                        ->weight(FontWeight::Bold)
                                        ->color(fn (VehicleInsurance $record): ?string => $record->effectiveDueColor())
                                        ->placeholder('-'),
                                    TextEntry::make('payment_evidence')->label('Bukti Pembayaran')->placeholder('-'),
                                    TextEntry::make('reminder_phones')->label('Nomor WA Pengingat')->placeholder('Nomor global'),
                                    TextEntry::make('notes')->label('Catatan')
                                        ->columnSpan(['default' => 2])
                                        ->visible(fn (VehicleInsurance $record): bool => filled($record->notes)),
                                ]),
                            ]),
                    ])->extraAttributes(['class' => 'gais-fill-column']),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $money = fn (TextColumn $column): TextColumn => $column->money('IDR', decimalPlaces: 0)->placeholder('-');

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['asset', 'category', 'provider', 'updates.provider'])
                ->addSelect(['license_plate' => VehicleInsurance::licensePlateSubquery()])
                ->withMax('updates', 'expired_date'))
            ->columns([
                TextColumn::make('policy_number')->label('Nomor Polis')
                    ->state(fn (VehicleInsurance $record): string => (string) $record->effective('policy_number'))
                    ->description(fn (VehicleInsurance $record): ?string => $record->latestUpdate() !== null && $record->latestUpdate()->policy_number !== $record->policy_number ? 'Awal: '.$record->policy_number : null)
                    ->searchable(query: fn (Builder $query, string $search): Builder => static::applySearch($query, $search))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        'COALESCE((SELECT u.policy_number FROM vehicle_insurance_updates u WHERE u.vehicle_insurance_id = vehicle_insurances.id AND u.deleted_at IS NULL ORDER BY u.expired_date DESC, u.id DESC LIMIT 1), vehicle_insurances.policy_number) '
                        .($direction === 'desc' ? 'desc' : 'asc'),
                    ))
                    ->weight('bold'),
                TextColumn::make('license_plate')->label('Plat Nomor')
                    ->description(fn (VehicleInsurance $record): ?string => $record->asset?->name)
                    ->placeholder('-')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(VehicleInsurance::licensePlateSubquery(), $direction === 'desc' ? 'desc' : 'asc')),
                TextColumn::make('insured_name')->label('Tertanggung')->limit(30)->placeholder('-')->sortable()->toggleable(),
                TextColumn::make('provider')->label('Provider')->state(fn (VehicleInsurance $record): ?string => $record->effectiveRow()->provider?->name)->placeholder('-')->toggleable(),
                TextColumn::make('extension_of_policy')->label('Jenis')->state(fn (VehicleInsurance $record): mixed => $record->effective('extension_of_policy'))->placeholder('-')->toggleable(),
                TextColumn::make('category.name')->label('Kategori')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                $money(TextColumn::make('insured_worth')->label('Nilai Pertanggungan')->state(fn (VehicleInsurance $record): mixed => $record->effective('insured_worth')))->toggleable(),
                $money(TextColumn::make('premium')->label('Premi')->state(fn (VehicleInsurance $record): mixed => $record->effective('premium')))->toggleable(),
                TextColumn::make('join_date')->label('Mulai')->date('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('effective_expired_date')
                    ->label('Jatuh Tempo')
                    ->state(fn (VehicleInsurance $record): ?string => $record->effectiveExpiredDate()?->format('d/m/Y'))
                    ->description(fn (VehicleInsurance $record): ?string => $record->updates->isNotEmpty() ? $record->updates->count().' pembaharuan' : null)
                    ->color(fn (VehicleInsurance $record): ?string => $record->effectiveDueColor())
                    ->weight('bold')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => static::orderByEffectiveExpiry($query, $direction)),
                TextColumn::make('effective_status')->label('Status')->state(fn (VehicleInsurance $record): ?ContractStatus => $record->effectiveStatus())->badge(),
                TextColumn::make('notes')->label('Catatan')->state(fn (VehicleInsurance $record): mixed => $record->effective('notes'))->limit(30)->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort(fn (Builder $query, string $direction): Builder => static::orderByEffectiveExpiry($query, $direction))
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(ContractStatus::class)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where(fn (Builder $matches) => $matches
                            ->where('vehicle_insurances.status', $data['value'])
                            ->orWhereHas('updates', fn (Builder $updates) => $updates->where('status', $data['value'])))
                        : $query),
                SelectFilter::make('insurance_provider_id')->label('Provider')->relationship('provider', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutTrashed())->preload(),
                Filter::make('active')
                    ->label('Masih berjalan')
                    ->toggle()
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
                        ->exports([ExcelExport::make('asuransi-kendaraan')->fromTable()->withFilename('asuransi-kendaraan-'.now()->format('Ymd-His'))])
                        ->visible(fn (): bool => auth()->user()?->can('export', VehicleInsurance::class) ?? false),
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
            'index' => Pages\ListVehicleInsurances::route('/'),
            'create' => Pages\CreateVehicleInsurance::route('/create'),
            'view' => Pages\ViewVehicleInsurance::route('/{record}'),
            'edit' => Pages\EditVehicleInsurance::route('/{record}/edit'),
        ];
    }

    /**
     * Urut berdasarkan jatuh tempo efektif. Memerlukan withMax('updates', 'expired_date').
     */
    public static function orderByEffectiveExpiry(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderByRaw('COALESCE(updates_max_expired_date, vehicle_insurances.expired_date) '.($direction === 'desc' ? 'desc' : 'asc'));
    }

    /**
     * Pilihan aset kendaraan (kategori MOBIL/MOTOR), dicari seperti tabel aset
     * (plat nomor dan atribut lain). Aset yang sedang terpilih tetap ikut.
     *
     * @return array<int, string>
     */
    public static function vehicleOptions(?string $search = null, ?int $selected = null): array
    {
        $categoryIds = Category::query()
            ->whereIn('name', config('vehicle-asset-reconciliation.category_names', ['MOBIL', 'MOTOR']))
            ->pluck('id');

        $options = Asset::searchOptions(Asset::query()->whereIn('assets.category_id', $categoryIds), $search);

        if ($selected !== null && ! array_key_exists($selected, $options)) {
            $asset = Asset::query()->withTrashed()->with(['attributes.customAttribute', 'businessEntity:id,name'])->find($selected);

            if ($asset !== null) {
                $options = [$asset->getKey() => $asset->optionLabel()] + $options;
            }
        }

        return $options;
    }

    /**
     * Pencarian: nomor polis (termasuk pembaharuan), tertanggung, plat nomor, dan nama aset.
     */
    private static function applySearch(Builder $query, string $search): Builder
    {
        $like = '%'.$search.'%';
        $normalized = '%'.Asset::normalizeIdentifier($search).'%';

        return $query->where(fn (Builder $matches) => $matches
            ->where('vehicle_insurances.policy_number', 'like', $like)
            ->orWhere('vehicle_insurances.insured_name', 'like', $like)
            ->orWhereHas('updates', fn (Builder $updates) => $updates->where('policy_number', 'like', $like))
            ->orWhereHas('asset', fn (Builder $asset) => $asset
                ->where('assets.name', 'like', $like)
                ->orWhereHas('attributes', fn (Builder $attributes) => $attributes->where('search_value', 'like', $normalized))));
    }
}
