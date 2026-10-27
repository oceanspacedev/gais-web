<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Enums\ItemRequestClientStatus;
use App\Filament\Resources\ItemRequestResource;
use App\Models\ItemRequest;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengajuan barang yang diajukan user ini, sebatas yang boleh dilihat viewer
 * di menu Pengajuan Barang. Hanya dibaca.
 */
class ItemRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'itemRequests';

    protected static ?string $title = 'Pengajuan Barang';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return ItemRequestResource::canViewAny();
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) static::visibleQuery($ownerRecord)->count();
    }

    /**
     * @param  User  $owner
     */
    public static function visibleQuery(Model $owner): Builder
    {
        return ItemRequestResource::scopeToViewer($owner->itemRequests()->getQuery(), auth()->user());
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => static::visibleQuery($this->getOwnerRecord())->with('type')->withCount('details'))
            ->columns([
                TextColumn::make('request_code')->label('Kode')->badge()->color('primary')->searchable(),
                TextColumn::make('type.name')->label('Tipe')->placeholder('-'),
                TextColumn::make('requested_at')->label('Tanggal')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('details_count')->label('Item')->badge()->color('gray'),
                TextColumn::make('client_status')->label('Status Akhir')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('client_status')->label('Status Akhir')->options(ItemRequestClientStatus::options()),
            ])
            ->defaultSort('requested_at', 'desc')
            ->recordUrl(fn (ItemRequest $record): ?string => ItemRequestResource::canView($record) ? ItemRequestResource::getUrl('view', ['record' => $record]) : null)
            ->emptyStateHeading('Belum ada pengajuan');
    }
}
