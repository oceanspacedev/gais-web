<?php

namespace App\Filament\Resources;

use App\Access\ScopesVisibleRecords;
use App\Enums\AssetCondition;
use App\Exceptions\AssetSaleException;
use App\Filament\Resources\AssetSaleResource\Pages;
use App\Models\Asset;
use App\Models\AssetSale;
use App\Models\BusinessEntity;
use App\Models\User;
use App\Support\RelationSearch;
use App\Support\StoredFile;
use Carbon\Carbon;
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
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Riwayat penjualan aset antar badan usaha. Penjualan dicatat lewat aksi
 * "Jual ke Badan Usaha Lain" di detail aset atau "Catat Penjualan" di daftar
 * ini (keduanya memakai AssetSale::record()); di sini baris hanya dilihat,
 * diperbaiki (tanggal, harga, dokumen, catatan), dan dihapus/dipulihkan.
 */
class AssetSaleResource extends Resource
{
    use ScopesVisibleRecords;

    protected static ?string $model = AssetSale::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Aset';

    protected static ?int $navigationSort = 4;

    // "Penjualan Aset" di sidebar dan judul halaman (judul panjang terlipat
    // di samping tiga tombol header); konteks antar badan usaha ada di deskripsi aksi.
    protected static ?string $navigationLabel = 'Penjualan Aset';

    /** Opsi aset yang dikirim ke browser per render; sisanya lewat pencarian server. */
    protected const OPTIONS_LIMIT = 50;

    public static function getModelLabel(): string
    {
        return 'Penjualan Aset';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Penjualan Aset';
    }

    protected static function baseVisibleQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['asset', 'fromBusinessEntity', 'toBusinessEntity', 'createdBy']);
    }

    /**
     * Form ubah: aset dan kedua badan usaha tidak bisa diganti karena
     * kepemilikan aset sudah mengikuti baris ini; yang boleh diperbaiki hanya
     * tanggal, harga, dokumen, dan catatan.
     */
    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                Placeholder::make('summary')
                    ->label('Penjualan')
                    ->content(fn (?AssetSale $record): string => $record ? static::summary($record) : '-')
                    ->columnSpanFull(),
                ...static::saleDetailFields(),
            ]);
    }

    public static function summary(AssetSale $sale): string
    {
        return sprintf(
            '%s · %s → %s',
            $sale->asset?->name ?? '(aset dihapus)',
            $sale->fromBusinessEntity?->name ?? '-',
            $sale->toBusinessEntity?->name ?? '-',
        );
    }

    /**
     * Form aksi "Jual ke Badan Usaha Lain" di halaman detail aset.
     *
     * @return list<Component>
     */
    public static function sellFormSchema(Asset $asset): array
    {
        return [
            Placeholder::make('owner')
                ->label('Pemilik Saat Ini')
                ->content(fn (): string => $asset->businessEntity?->name ?? '-'),
            static::buyerSelect(fn (): mixed => $asset->business_entity_id),
            ...static::saleDetailFields(),
        ];
    }

    /**
     * Badan usaha pembeli: yang bisa diakses viewer, tanpa pemilik aset saat
     * ini. $ownerId menerima Get dan mengembalikan badan usaha pemilik.
     */
    public static function buyerSelect(callable $ownerId): Select
    {
        return Select::make('to_business_entity_id')
            ->label('Badan Usaha Pembeli')
            ->options(fn (Get $get): array => static::buyerOptions($ownerId($get)))
            ->in(fn (Get $get): array => array_keys(static::buyerOptions($ownerId($get))))
            ->searchable()
            ->native(false)
            ->required()
            ->helperText('Pemilik aset saat ini tidak bisa dipilih.');
    }

    /**
     * @return array<int, string>
     */
    public static function buyerOptions(mixed $ownerId): array
    {
        $options = BusinessEntity::optionsFor(Auth::user());

        if (filled($ownerId)) {
            unset($options[(int) $ownerId]);
        }

        return $options;
    }

    /**
     * Isian penjualan selain pembeli; dipakai form ubah dan kedua aksi catat.
     *
     * @return list<Component>
     */
    public static function saleDetailFields(): array
    {
        return [
            DatePicker::make('sold_at')
                ->label('Tanggal Penjualan')
                ->native(false)
                ->default(now())
                ->required(),
            TextInput::make('sold_price')
                ->label('Harga Jual')
                ->numeric()
                ->minValue(0)
                ->prefix('Rp')
                ->default(0)
                ->required()
                ->helperText('Isi 0 bila dialihkan tanpa nilai.'),
            FileUpload::make('document_path')
                ->label('Dokumen (BAST / Invoice)')
                ->directory('asset-sales')
                ->preserveFilenames()
                ->acceptedFileTypes(['application/pdf', 'image/*'])
                ->maxSize(4096)
                ->helperText('PDF/JPG, maks 4 MB. Bisa diunggah belakangan lewat Ubah.')
                ->columnSpanFull(),
            Textarea::make('notes')
                ->label('Catatan')
                ->rows(3)
                ->maxLength(1000)
                ->placeholder('Nomor BAST, referensi invoice, atau alasan perpindahan.')
                ->columnSpanFull(),
        ];
    }

    /**
     * Aksi jual tampil hanya bila aset memang bisa dijual oleh viewer: punya
     * izin catat, aset di stok (Tersedia tanpa pemegang), dan badan usaha
     * pemiliknya termasuk akses viewer. Aturan yang sama dipaksa lagi di
     * AssetSale::record().
     */
    public static function canSell(Asset $asset): bool
    {
        $viewer = Auth::user();

        return $viewer instanceof User
            && $viewer->can('create', AssetSale::class)
            && ! $asset->trashed()
            && $asset->business_entity_id !== null
            && $viewer->canAccessBusinessEntity($asset->business_entity_id)
            && $asset->condition_status === AssetCondition::Available
            && $asset->recipient_id === null;
    }

    /**
     * Catat penjualan atas nama viewer. Aturan yang ditolak ditampilkan
     * sebagai notifikasi dan modalnya tetap terbuka.
     *
     * @param  array<string, mixed>  $data
     */
    public static function recordSale(Asset $asset, array $data): AssetSale
    {
        $viewer = Auth::user();

        abort_unless($viewer instanceof User && $viewer->can('create', AssetSale::class), 403);

        try {
            return AssetSale::record($asset, $data, $viewer);
        } catch (AssetSaleException $exception) {
            Notification::make()
                ->title('Penjualan tidak dapat dicatat')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            throw new Halt;
        }
    }

    /**
     * Aset yang bisa dijual viewer: stok (Tersedia, tanpa pemegang) di badan
     * usaha yang termasuk aksesnya. Dipakai pemilih aset di "Catat Penjualan".
     */
    public static function sellableAssets(): Builder
    {
        $viewer = Auth::user();
        $query = AssetResource::scopeToViewer(Asset::query(), $viewer)
            ->whereNotNull('business_entity_id')
            ->where('condition_status', AssetCondition::Available->value)
            ->whereNull('recipient_id');

        return $viewer instanceof User
            ? $viewer->limitToAccessibleBusinessEntities($query, 'assets.business_entity_id')
            : $query;
    }

    /**
     * @return array<int, string>
     */
    public static function sellableAssetOptions(?string $search = null): array
    {
        return static::sellableAssets()
            ->with('businessEntity:id,name')
            ->when(filled($search), fn (Builder $query): Builder => $query->where(fn (Builder $match): Builder => $match
                ->where('name', 'like', "%{$search}%")
                ->orWhereAnyAttribute((string) $search)))
            ->orderBy('name')
            ->limit(static::OPTIONS_LIMIT)
            ->get()
            ->mapWithKeys(fn (Asset $asset): array => [$asset->id => static::assetOptionLabel($asset)])
            ->all();
    }

    public static function assetOptionLabel(Asset $asset): string
    {
        return sprintf('#%d · %s · %s', $asset->id, $asset->name, $asset->businessEntity?->name ?? '-');
    }

    /**
     * Tautan ke aset hanya bila viewer boleh membukanya: pemilik lama tetap
     * melihat baris penjualannya, tetapi asetnya sudah di luar cakupannya.
     */
    public static function assetUrl(AssetSale $sale): ?string
    {
        $asset = $sale->asset;
        $viewer = Auth::user();

        if (! $asset || ! $viewer instanceof User || ! $viewer->can('view', $asset)) {
            return null;
        }

        return AssetResource::getUrl('view', ['record' => $asset]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sold_at', 'desc')
            ->columns([
                TextColumn::make('sold_at')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('asset.name')
                    ->label('Aset')
                    ->weight(FontWeight::SemiBold)
                    ->searchable(query: RelationSearch::on('asset', 'name'))
                    ->url(fn (AssetSale $record): ?string => static::assetUrl($record))
                    ->placeholder('(aset dihapus)'),
                TextColumn::make('fromBusinessEntity.name')
                    ->label('Dari')
                    ->badge()
                    ->color('danger')
                    ->placeholder('-'),
                TextColumn::make('toBusinessEntity.name')
                    ->label('Kepada')
                    ->badge()
                    ->color('success')
                    ->placeholder('-'),
                TextColumn::make('sold_price')
                    ->label('Harga')
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((int) $state, 0, ',', '.'))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('document_path')
                    ->label('Dokumen')
                    ->state(fn (AssetSale $record): string => $record->document_path ? 'Dokumen' : '-')
                    ->icon(fn (AssetSale $record): ?string => $record->document_path ? 'heroicon-o-document-text' : null)
                    ->color(fn (AssetSale $record): string => $record->document_path ? 'primary' : 'gray')
                    ->url(fn (AssetSale $record): ?string => $record->document_path ? StoredFile::url($record->document_path) : null, true),
                TextColumn::make('createdBy.name')
                    ->label('Dicatat oleh')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Dicatat pada')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('from_business_entity_id')
                    ->label('Dijual oleh')
                    ->relationship('fromBusinessEntity', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutTrashed())
                    ->multiple()
                    ->searchable()
                    ->preload(),
                SelectFilter::make('to_business_entity_id')
                    ->label('Dibeli oleh')
                    ->relationship('toBusinessEntity', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutTrashed())
                    ->multiple()
                    ->searchable()
                    ->preload(),
                Filter::make('sold_at')
                    ->label('Periode Penjualan')
                    ->form([
                        DatePicker::make('from')
                            ->label('Dari Tanggal')
                            ->native(false),
                        DatePicker::make('until')
                            ->label('Sampai Tanggal')
                            ->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('sold_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('sold_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = Indicator::make('Dari: '.Carbon::parse($data['from'])->format('d M Y'))->removeField('from');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = Indicator::make('Sampai: '.Carbon::parse($data['until'])->format('d M Y'))->removeField('until');
                        }

                        return $indicators;
                    }),
            ])
            ->actions([
                RestoreAction::make(),
                ViewAction::make(),
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('md'),
                DeleteAction::make()
                    ->modalDescription('Riwayat ini masuk Sampah dan bisa dipulihkan. Kepemilikan aset tidak dibalik otomatis; perbaiki badan usaha aset lewat form aset bila perlu.'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $infolist): Schema
    {
        return $infolist
            ->columns(1)
            ->schema([
                Section::make('Informasi Penjualan')
                    ->icon('heroicon-o-building-office-2')
                    ->schema([
                        Grid::make(['default' => 2, 'lg' => 3])->schema([
                            TextEntry::make('asset.name')
                                ->label('Aset')
                                ->weight(FontWeight::SemiBold)
                                ->color(fn (AssetSale $record): string => static::assetUrl($record) ? 'primary' : 'gray')
                                ->url(fn (AssetSale $record): ?string => static::assetUrl($record))
                                ->placeholder('(aset dihapus)'),
                            TextEntry::make('sold_at')
                                ->label('Tanggal Penjualan')
                                ->date('d M Y'),
                            TextEntry::make('sold_price')
                                ->label('Harga Jual')
                                ->state(fn (AssetSale $record): string => 'Rp '.number_format($record->sold_price ?: 0, 0, ',', '.'))
                                ->weight(FontWeight::Bold)
                                ->color('success'),
                            TextEntry::make('fromBusinessEntity.name')
                                ->label('Dijual oleh')
                                ->badge()
                                ->color('danger')
                                ->placeholder('-'),
                            TextEntry::make('toBusinessEntity.name')
                                ->label('Dibeli oleh')
                                ->badge()
                                ->color('success')
                                ->placeholder('-'),
                            TextEntry::make('document_path')
                                ->label('Dokumen (BAST / Invoice)')
                                ->state(fn (AssetSale $record): string => $record->document_path ? 'Lihat Dokumen' : 'Belum diunggah')
                                ->url(fn (AssetSale $record): ?string => $record->document_path ? StoredFile::url($record->document_path) : null, true)
                                ->icon(fn (AssetSale $record): ?string => $record->document_path ? 'heroicon-o-paper-clip' : null)
                                ->color(fn (AssetSale $record): string => $record->document_path ? 'primary' : 'gray'),
                            TextEntry::make('createdBy.name')
                                ->label('Dicatat oleh')
                                ->placeholder('-'),
                            TextEntry::make('created_at')
                                ->label('Dicatat pada')
                                ->dateTime('d M Y H:i'),
                        ]),
                        TextEntry::make('notes')
                            ->label('Catatan')
                            ->visible(fn (AssetSale $record): bool => filled($record->notes)),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetSales::route('/'),
            'view' => Pages\ViewAssetSale::route('/{record}'),
        ];
    }
}
