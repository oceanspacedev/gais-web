<?php

namespace App\Filament\Resources;

use App\Access\ScopesVisibleRecords;
use App\Filament\Resources\AssetQrLabelHistoryResource\Pages;
use App\Models\Asset;
use App\Models\AssetQrLabelHistory;
use App\Services\AssetQrLabelHistoryService;
use App\Support\RelationSearch;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
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
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssetQrLabelHistoryResource extends Resource
{
    use ScopesVisibleRecords;

    protected static ?string $model = AssetQrLabelHistory::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Aset';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'History Label QR';

    public static function getModelLabel(): string
    {
        return 'History Label QR';
    }

    public static function getPluralModelLabel(): string
    {
        return 'History Label QR';
    }

    /**
     * History adalah log audit: tidak bisa dibuat, diubah, atau dihapus dari
     * panel, apa pun permission-nya. Melihat diatur AssetQrLabelHistoryPolicy.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        // Satu kolom selebar layar: ringkasan generate, lalu daftar aset di label.
        return $schema
            ->columns(1)
            ->schema([
                Section::make('Detail Generate Label QR')
                    ->icon('heroicon-o-qr-code')
                    ->schema([
                        Grid::make(['default' => 2, 'lg' => 4])->schema([
                            TextEntry::make('action')
                                ->label('Aksi')
                                ->badge()
                                ->formatStateUsing(fn (AssetQrLabelHistory $record): string => $record->actionLabel())
                                ->color(fn (string $state): string => match ($state) {
                                    AssetQrLabelHistory::ACTION_PRINT => 'info',
                                    AssetQrLabelHistory::ACTION_DOWNLOAD_PDF => 'success',
                                    default => 'gray',
                                })
                                ->columnSpan(['default' => 2, 'lg' => 1]),
                            TextEntry::make('created_at')
                                ->label('Waktu')
                                ->dateTime('d M Y, H:i:s'),
                            TextEntry::make('user.name')
                                ->label('Di-generate oleh')
                                ->weight(FontWeight::SemiBold)
                                ->helperText(fn (AssetQrLabelHistory $record): ?string => $record->user?->email)
                                ->placeholder('—'),
                            TextEntry::make('asset_count')
                                ->label('Jumlah Aset')
                                ->numeric()
                                ->size(TextSize::Large)
                                ->weight(FontWeight::Bold),
                            TextEntry::make('file_name')
                                ->label('File PDF')
                                ->icon(fn (AssetQrLabelHistory $record): ?string => filled($record->file_name) ? 'heroicon-o-document' : null)
                                ->placeholder('Tidak ada file')
                                ->columnSpanFull(),
                        ]),
                    ]),

                Section::make('Aset dalam Label')
                    ->icon('heroicon-o-cube')
                    ->schema([
                        RepeatableEntry::make('labelled_assets')
                            ->hiddenLabel()
                            ->state(fn (AssetQrLabelHistory $record) => static::labelledAssets($record))
                            ->table([
                                TableColumn::make('ID')->width('6rem'),
                                TableColumn::make('Nama Aset'),
                                TableColumn::make('Kategori'),
                            ])
                            ->schema([
                                // Label dipakai saat tabel jadi kartu di layar sempit.
                                TextEntry::make('id')->label('ID')->color('gray'),
                                TextEntry::make('name')
                                    ->label('Nama Aset')
                                    ->weight(FontWeight::SemiBold)
                                    ->url(fn (Asset $record): ?string => $record->trashed() ? null : AssetResource::getUrl('view', ['record' => $record]))
                                    ->helperText(fn (Asset $record): ?string => $record->trashed() ? 'Aset sudah dihapus' : null),
                                TextEntry::make('category.name')->label('Kategori')->placeholder('-'),
                            ])
                            ->visible(fn (AssetQrLabelHistory $record): bool => static::labelledAssets($record)->isNotEmpty()),
                        // Aset yang sudah dihapus permanen tidak bisa dimuat lagi; pakai nama yang tercatat saat generate.
                        TextEntry::make('asset_summary')
                            ->label('Daftar aset (tercatat saat generate)')
                            ->placeholder('—')
                            ->visible(fn (AssetQrLabelHistory $record): bool => static::labelledAssets($record)->isEmpty()),
                    ]),
            ]);
    }

    /**
     * Aset yang dicetak di label, urut seperti saat generate.
     *
     * @return Collection<int, Asset>
     */
    private static function labelledAssets(AssetQrLabelHistory $record): Collection
    {
        $ids = collect($record->asset_ids ?? [])->map(fn ($id): int => (int) $id);

        return Asset::withTrashed()
            ->with('category')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Asset $asset): int => (int) $ids->search($asset->id))
            ->values();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('action')
                    ->label('Aksi')
                    ->badge()
                    ->formatStateUsing(fn (AssetQrLabelHistory $record): string => $record->actionLabel())
                    ->color(fn (string $state): string => match ($state) {
                        AssetQrLabelHistory::ACTION_PRINT => 'info',
                        AssetQrLabelHistory::ACTION_DOWNLOAD_PDF => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('asset_count')
                    ->label('Jumlah')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('asset_summary')
                    ->label('Aset')
                    ->searchable()
                    ->wrap()
                    ->limit(80)
                    ->tooltip(fn (AssetQrLabelHistory $record): ?string => $record->asset_summary),
                TextColumn::make('file_name')
                    ->label('File')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('Di-generate oleh')
                    ->searchable(query: RelationSearch::on('user', 'name'))
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Aksi')
                    ->options([
                        AssetQrLabelHistory::ACTION_PRINT => 'Print Label QR',
                        AssetQrLabelHistory::ACTION_DOWNLOAD_PDF => 'Download Label QR (PDF)',
                    ]),
                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('downloadFile')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    // Cek file baru saat diklik supaya render tabel tidak memanggil storage per baris.
                    ->visible(fn (AssetQrLabelHistory $record): bool => filled($record->file_path))
                    ->action(function (AssetQrLabelHistory $record) {
                        try {
                            return app(AssetQrLabelHistoryService::class)->download($record);
                        } catch (NotFoundHttpException) {
                            Notification::make()
                                ->title('File tidak ditemukan')
                                ->body('File PDF label QR ini tidak tersedia di storage.')
                                ->danger()
                                ->send();

                            return null;
                        }
                    }),
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetQrLabelHistories::route('/'),
            'view' => Pages\ViewAssetQrLabelHistory::route('/{record}'),
        ];
    }
}
