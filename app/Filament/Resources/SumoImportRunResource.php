<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SumoImportRunResource\Pages;
use App\Models\SumoImportRun;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

/**
 * Impor data sumo dari panel: unggah dump (.sql / .gz) → dimuat ke database
 * staging sumo → uji coba otomatis → impor sungguhan setelah laporannya diperiksa.
 * Setiap run menyimpan file, tabel yang dimuat, laporan, dan log-nya.
 */
class SumoImportRunResource extends Resource
{
    protected static ?string $model = SumoImportRun::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 70;

    protected static ?string $navigationLabel = 'Impor Data Sumo';

    protected static ?string $slug = 'impor-sumo';

    public static function getModelLabel(): string
    {
        return 'Impor Data Sumo';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Impor Data Sumo';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(SumoImportRun::PERMISSION) ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function stagingDatabase(): string
    {
        return (string) config('database.connections.'.config('sumo-import.connection', 'sumo').'.database');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Unggah dump sumo')
                ->description(fn (): string => 'Dump dimuat ke database staging `'.self::stagingDatabase().'` (SUMO_DB_DATABASE), bukan ke database shelf. '
                    .'Setelah dimuat, uji coba berjalan otomatis; data shelf baru berubah setelah tombol Impor Sungguhan dikonfirmasi.')
                ->schema([
                    FileUpload::make('file_path')
                        ->label('File dump')
                        ->disk(fn (): string => (string) config('sumo-import.panel.disk'))
                        ->directory(fn (): string => (string) config('sumo-import.panel.directory'))
                        ->visibility('private')
                        ->storeFileNamesIn('original_name')
                        ->maxSize(fn (): int => (int) config('sumo-import.panel.max_upload_kb'))
                        ->helperText(fn (): string => 'Hasil mysqldump database sumo, .sql atau .gz (disarankan, jauh lebih kecil). Maksimal '
                            .Number::fileSize((int) config('sumo-import.panel.max_upload_kb') * 1024).'.')
                        ->required()
                        ->columnSpanFull(),
                    Textarea::make('notes')
                        ->label('Catatan')
                        ->placeholder('Mis. dump server sumo 27 Okt, sebelum cut-over.')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('created_at')->label('Diunggah')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('original_name')->label('File')->searchable()->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SumoImportRun::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => SumoImportRun::STATUS_COLORS[$state] ?? 'gray'),
                IconColumn::make('staged')->label('Di staging')->boolean(),
                TextColumn::make('user.name')->label('Oleh')->placeholder('-'),
                TextColumn::make('imported_at')->label('Diimpor')->dateTime('d/m/Y H:i')->placeholder('-'),
            ])
            ->defaultSort('id', 'desc')
            ->poll(fn (): ?string => SumoImportRun::anyBusy() ? '5s' : null)
            ->recordActions([
                ViewAction::make()->label('Buka'),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Belum ada impor sumo')
            ->emptyStateDescription('Unggah dump database sumo; sistem memuatnya ke staging dan menjalankan uji coba.')
            ->emptyStateActions([
                CreateAction::make()->label('Unggah dump')->icon('heroicon-o-arrow-up-tray'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Ringkasan')
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => SumoImportRun::STATUS_LABELS[$state] ?? $state)
                            ->color(fn (string $state): string => SumoImportRun::STATUS_COLORS[$state] ?? 'gray'),
                        TextEntry::make('original_name')->label('File'),
                        TextEntry::make('file_size')->label('Ukuran')->formatStateUsing(fn (int $state): string => Number::fileSize($state, 1)),
                        TextEntry::make('staged')
                            ->label('Di staging')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Ya, `'.self::stagingDatabase().'`' : 'Tidak')
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                        TextEntry::make('user.name')->label('Diunggah oleh')->placeholder('-'),
                        TextEntry::make('created_at')->label('Diunggah')->dateTime('d M Y H:i'),
                        TextEntry::make('loaded_at')->label('Dimuat ke staging')->dateTime('d M Y H:i')->placeholder('-'),
                        TextEntry::make('dry_run_at')->label('Uji coba')->dateTime('d M Y H:i')->placeholder('-'),
                        TextEntry::make('imported_at')->label('Impor sungguhan')->dateTime('d M Y H:i')->placeholder('-'),
                        TextEntry::make('importedBy.name')->label('Diimpor oleh')->placeholder('-'),
                        TextEntry::make('file_sha256')->label('SHA-256')->fontFamily(FontFamily::Mono)->copyable()->limit(16)->placeholder('-')->columnSpan(2),
                    ]),
                    TextEntry::make('notes')->label('Catatan')->placeholder('-')->columnSpanFull(),
                    TextEntry::make('error')
                        ->label('Penyebab gagal')
                        ->color('danger')
                        ->columnSpanFull()
                        ->visible(fn (SumoImportRun $record): bool => $record->status === SumoImportRun::STATUS_FAILED && filled($record->error)),
                ]),
            Section::make('Hasil uji coba')
                ->description('Jumlah per langkah bila diimpor sekarang. Tidak ada yang disimpan; periksa pemetaan dan peringatan sebelum impor sungguhan.')
                ->schema([
                    ViewEntry::make('dry_run_report')->hiddenLabel()->view('filament.sumo-import.report'),
                ])
                ->visible(fn (SumoImportRun $record): bool => $record->dry_run_report !== null),
            Section::make('Hasil impor sungguhan')
                ->schema([
                    ViewEntry::make('import_report')->hiddenLabel()->view('filament.sumo-import.report'),
                ])
                ->visible(fn (SumoImportRun $record): bool => $record->import_report !== null),
            Section::make('Tabel di dump')
                ->description('Jumlah baris per tabel setelah dump dimuat ke staging.')
                ->collapsible()
                ->collapsed()
                ->schema([
                    KeyValueEntry::make('source_tables')->hiddenLabel()->keyLabel('Tabel')->valueLabel('Baris'),
                ])
                ->visible(fn (SumoImportRun $record): bool => filled($record->source_tables)),
            Section::make('Log')
                ->schema([
                    ViewEntry::make('log')->hiddenLabel()->view('filament.sumo-import.log'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSumoImportRuns::route('/'),
            'create' => Pages\CreateSumoImportRun::route('/unggah'),
            'view' => Pages\ViewSumoImportRun::route('/{record}'),
        ];
    }
}
