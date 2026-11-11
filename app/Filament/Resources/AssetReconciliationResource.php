<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetReconciliationResource\Pages;
use App\Filament\Resources\AssetReconciliationResource\RelationManagers\ItemsRelationManager;
use App\Models\Asset;
use App\Models\AssetReconciliation;
use App\Support\RelationSearch;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetReconciliationResource extends Resource
{
    protected static ?string $model = AssetReconciliation::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Aset';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Import & Laporan Audit';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('import', Asset::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('1. Import Workbook Audit')
                ->description('Alur wajib: Export format → isi audit → Import → Laporan gap → Apply. File di-stage dan dibandingkan dulu; data GAIS baru berubah setelah Terapkan Koreksi dikonfirmasi.')
                ->schema([
                    Select::make('source_system')
                        ->label('Jenis Audit')
                        ->options([
                            'CSA' => 'CSA (stok ritel / inventori)',
                            'VEHICLE_AUDIT' => 'Kendaraan (Monitoring Asset / LHP)',
                        ])
                        ->default('CSA')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (callable $set, ?string $state): void {
                            $set('source_sheet', $state === 'VEHICLE_AUDIT' ? 'Monitoring Asset' : 'ASET');
                            // Vehicle create is opt-in; CSA keeps create-location on by default.
                            $set('auto_create_locations', $state !== 'VEHICLE_AUDIT');
                        })
                        ->helperText('CSA memakai sheet ASET (qty). Kendaraan memakai sheet Monitoring Asset (plat).')
                        ->columnSpanFull(),
                    Select::make('business_entity_id')
                        ->label('Badan Usaha Default')
                        ->relationship('businessEntity', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutTrashed())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->placeholder('Pilih badan usaha resmi')
                        ->helperText(fn (callable $get): string => $get('source_system') === 'VEHICLE_AUDIT'
                            ? 'Dipakai bila ACC/STNK tidak punya alias resmi. Alias MSI/CS/TOP/MKLI dipetakan exact ke master.'
                            : 'Dipakai untuk baris tanpa marker badan usaha dari CSA. Marker resmi seperti CSN dan override Gudang memiliki prioritas lebih tinggi.')
                        ->columnSpanFull(),
                    FileUpload::make('stored_path')
                        ->label('File Excel Audit')
                        ->disk(fn (): string => config('filesystems.default'))
                        ->visibility('private')
                        ->directory('asset-reconciliations')
                        ->storeFileNamesIn('original_filename')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->maxSize(20480)
                        ->required()
                        ->columnSpanFull(),
                    TextInput::make('source_sheet')
                        ->label(fn (callable $get): string => $get('source_system') === 'VEHICLE_AUDIT'
                            ? 'Nama Sheet Monitoring'
                            : 'Nama Sheet Aset')
                        ->default('ASET')
                        ->required()
                        ->maxLength(100),
                    Toggle::make('auto_create_locations')
                        ->label(fn (callable $get): string => $get('source_system') === 'VEHICLE_AUDIT'
                            ? 'Buat aset/lokasi baru dari audit (opt-in)'
                            : 'Buat lokasi baru untuk kode gudang yang belum dikenal')
                        ->helperText(fn (callable $get): string => $get('source_system') === 'VEHICLE_AUDIT'
                            ? 'Off = plat hilang / keberadaan baru → Terblokir. On = saat Apply boleh buat aset MOBIL/MOTOR dan lokasi dari CEK KEBERADAAN. Tidak mengubah GAIS saat preview.'
                            : 'Lokasi baru hanya dibuat ketika koreksi diterapkan, tidak saat preview compare.')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('original_filename')->label('Workbook')->searchable()->limit(36),
                TextColumn::make('source_system')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::sourceLabel($state))
                    ->color(fn (?string $state): string => $state === 'VEHICLE_AUDIT' ? 'info' : 'gray'),
                TextColumn::make('businessEntity.name')->label('Badan Usaha Default')->searchable(query: RelationSearch::on('businessEntity', 'name'))->sortable()->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusLabel($state))
                    ->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('total_rows')->label('Baris')->numeric(),
                TextColumn::make('inline_rows')->label('Inline')->numeric()->color('success'),
                TextColumn::make('gap_rows')->label('Gap')->numeric()->color('warning'),
                TextColumn::make('blocked_rows')->label('Blokir')->numeric()->color('danger'),
                TextColumn::make('importer.name')->label('Pengguna')->toggleable(),
                TextColumn::make('applied_at')->label('Diterapkan')->dateTime('d M Y H:i')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('source_system')->label('Jenis Audit')->options([
                    'CSA' => 'CSA',
                    'VEHICLE_AUDIT' => 'Kendaraan',
                ]),
                SelectFilter::make('status')->options([
                    AssetReconciliation::STATUS_PROCESSING => self::statusLabel(AssetReconciliation::STATUS_PROCESSING),
                    AssetReconciliation::STATUS_COMPARED => self::statusLabel(AssetReconciliation::STATUS_COMPARED),
                    AssetReconciliation::STATUS_ALIGNED => self::statusLabel(AssetReconciliation::STATUS_ALIGNED),
                    AssetReconciliation::STATUS_APPLIED => self::statusLabel(AssetReconciliation::STATUS_APPLIED),
                    AssetReconciliation::STATUS_FAILED => self::statusLabel(AssetReconciliation::STATUS_FAILED),
                ]),
            ])
            ->actions([
                ViewAction::make()->label('Buka Laporan'),
            ])
            ->emptyStateHeading('Belum ada batch audit')
            ->emptyStateDescription('Pilih CSA atau Audit Kendaraan: Export format → isi audit → Import → Laporan → Apply.')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('1. Import Audit')
                    ->icon('heroicon-o-document-magnifying-glass'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $isVehicle = fn (?AssetReconciliation $record): bool => ($record?->source_system ?? null) === 'VEHICLE_AUDIT';
        $count = fn (TextEntry $entry): TextEntry => $entry->numeric()->size(TextSize::Large)->weight(FontWeight::Bold);

        // Satu kolom selebar layar: data batch, angka hasil banding, lalu hasil apply.
        return $schema
            ->columns(1)
            ->schema([
                Section::make('Penyebab Gagal')
                    ->icon('heroicon-o-x-circle')
                    ->iconColor('danger')
                    ->visible(fn (?AssetReconciliation $record): bool => filled($record?->failure_message))
                    ->schema([
                        TextEntry::make('failure_message')->hiddenLabel()->color('danger'),
                    ]),

                Section::make('Informasi Batch')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Grid::make(['default' => 2, 'lg' => 4])->schema([
                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => self::statusLabel($state))
                                ->color(fn (string $state): string => self::statusColor($state)),
                            TextEntry::make('source_system')
                                ->label('Jenis Audit')
                                ->badge()
                                ->formatStateUsing(fn (?string $state): string => self::sourceLabel($state))
                                ->color(fn (?string $state): string => $state === 'VEHICLE_AUDIT' ? 'info' : 'gray'),
                            TextEntry::make('source_sheet')->label('Sheet')->placeholder('-'),
                            TextEntry::make('businessEntity.name')
                                ->label('Badan Usaha Default')
                                ->placeholder('Batch lama: belum ditentukan')
                                ->color(fn (?string $state): ?string => filled($state) ? null : 'danger'),
                            TextEntry::make('original_filename')
                                ->label('Workbook')
                                ->icon('heroicon-o-table-cells')
                                ->columnSpan(['default' => 2]),
                            TextEntry::make('compared_at')->label('Waktu Laporan')->dateTime('d M Y, H:i')->placeholder('-'),
                            TextEntry::make('applied_at')->label('Waktu Apply')->dateTime('d M Y, H:i')->placeholder('Belum diterapkan'),
                            TextEntry::make('parent.uuid')
                                ->label('Batch Induk')
                                ->columnSpan(['default' => 2])
                                ->visible(fn (?AssetReconciliation $record): bool => filled($record?->parent_id)),
                        ]),
                    ]),

                Section::make('Hasil Banding')
                    ->icon('heroicon-o-scale')
                    ->description('Tinjau Inline, Gap, dan Blocked sebelum Apply. Tidak ada mutasi GAIS pada tahap ini.')
                    ->schema([
                        Grid::make(['default' => 2, 'lg' => 4])->schema([
                            $count(TextEntry::make('total_rows')->label('Total Baris')),
                            $count(TextEntry::make('inline_rows')->label('Sudah Inline'))->color('success'),
                            $count(TextEntry::make('gap_rows')->label('Perlu Koreksi'))->color('warning'),
                            $count(TextEntry::make('blocked_rows')->label('Terblokir'))->color('danger'),
                        ]),
                    ]),

                Section::make('Hasil Apply')
                    ->icon('heroicon-o-check-badge')
                    ->schema([
                        Grid::make(['default' => 2, 'lg' => 4])->schema([
                            $count(TextEntry::make('created_rows')->label('Aset Dibuat')),
                            $count(TextEntry::make('updated_rows')->label('Aset Disesuaikan')),
                            $count(TextEntry::make('retired_rows')
                                ->label(fn (?AssetReconciliation $record): string => $isVehicle($record) ? 'Dinonaktifkan / Terjual' : 'Saldo Jadi 0')),
                            $count(TextEntry::make('orphan_gais_plates')
                                ->label('Plat GAIS tanpa pasangan audit')
                                ->state(fn (?AssetReconciliation $record): ?int => $record?->summary['orphan_gais_plates'] ?? null)
                                ->helperText('Observasi saja — tidak dihapus otomatis (FR-VA-013).'))
                                ->visible(fn (?AssetReconciliation $record): bool => $isVehicle($record)
                                    && array_key_exists('orphan_gais_plates', $record?->summary ?? [])),
                        ]),
                        // summary.actions berbentuk aksi => jumlah; state dibangun di sini karena
                        // Filament memformat tiap nilai array satu per satu (hasilnya dulu "-, -, -").
                        TextEntry::make('preview_actions')
                            ->label('Ringkasan Aksi Preview')
                            ->state(fn (?AssetReconciliation $record): array => collect($record?->summary['actions'] ?? [])
                                ->map(fn (int $total, string $action): string => self::actionLabel($action).": {$total}")
                                ->values()
                                ->all())
                            ->badge()
                            ->color('gray')
                            ->visible(fn (?AssetReconciliation $record): bool => filled($record?->summary['actions'] ?? null)),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetReconciliations::route('/'),
            'create' => Pages\CreateAssetReconciliation::route('/create'),
            'view' => Pages\ViewAssetReconciliation::route('/{record}'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Import & Laporan Audit Aset';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Import & Laporan Audit Aset';
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            AssetReconciliation::STATUS_PROCESSING => 'Memproses',
            AssetReconciliation::STATUS_COMPARED => 'Preview tersedia',
            AssetReconciliation::STATUS_ALIGNED => 'Sudah inline',
            AssetReconciliation::STATUS_APPLIED => 'Koreksi diterapkan',
            AssetReconciliation::STATUS_FAILED => 'Gagal',
            default => $status,
        };
    }

    public static function sourceLabel(?string $source): string
    {
        return match ($source) {
            'VEHICLE_AUDIT' => 'Kendaraan',
            'CSA' => 'CSA',
            default => $source ?? '-',
        };
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'mark_sold' => 'Tandai terjual',
            'retire_duplicate' => 'Nonaktifkan duplikat',
            'create', 'create_missing' => 'Buat aset',
            'enrich' => 'Lengkapi identitas',
            'adjust' => 'Sesuaikan',
            'retire' => 'Saldo 0',
            'review' => 'Tinjau',
            'none' => 'Tanpa aksi',
            default => $action,
        };
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            AssetReconciliation::STATUS_ALIGNED => 'success',
            AssetReconciliation::STATUS_APPLIED => 'primary',
            AssetReconciliation::STATUS_COMPARED => 'warning',
            AssetReconciliation::STATUS_FAILED => 'danger',
            default => 'gray',
        };
    }
}
