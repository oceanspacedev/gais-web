<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemRequestLogResource\Pages;
use App\Models\ItemRequestLog;
use App\Support\RelationSearch;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Jejak perubahan pengajuan (mis. ganti pemohon). Hanya dibaca.
 */
class ItemRequestLogResource extends Resource
{
    protected static ?string $model = ItemRequestLog::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengajuan Barang';

    protected static ?int $navigationSort = 8;

    public static function getModelLabel(): string
    {
        return __('Log Pengajuan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Log Pengajuan');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('request.request_code')->label('Pengajuan')->searchable(query: RelationSearch::on('request', 'request_code'))->badge(),
                TextColumn::make('user.name')->label('Oleh')->placeholder('-'),
                TextColumn::make('activity')->label('Aktivitas')->wrap()->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListItemRequestLogs::route('/'),
        ];
    }
}
