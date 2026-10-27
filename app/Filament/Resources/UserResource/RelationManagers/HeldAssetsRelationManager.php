<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\AssetResource;
use App\Models\Asset;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Aset yang saat ini dipegang user ini (assets.recipient_id), sebatas yang
 * boleh dilihat viewer di menu Aset. Hanya dibaca.
 */
class HeldAssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'heldAssets';

    protected static ?string $title = 'Aset Dipegang';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return AssetResource::canViewAny();
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
        $viewer = auth()->user();
        $query = $owner->heldAssets()->getQuery();

        return $viewer instanceof User ? $query->accessibleBy($viewer) : $query->whereRaw('1 = 0');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => static::visibleQuery($this->getOwnerRecord())->with(['businessEntity', 'category', 'assetLocation']))
            ->columns([
                TextColumn::make('name')->label('Nama Aset')->searchable()->wrap(),
                TextColumn::make('businessEntity.name')
                    ->label('Badan Usaha')
                    ->badge()
                    ->color(fn (Asset $record): ?string => $record->businessEntity?->color)
                    ->placeholder('-'),
                TextColumn::make('category.name')->label('Kategori')->placeholder('-'),
                TextColumn::make('serial_number')->label('Serial Number')->searchable()->placeholder('-'),
                TextColumn::make('assetLocation.name')->label('Lokasi')->placeholder('-'),
                TextColumn::make('condition_status_label')
                    ->label('Status Aset')
                    ->badge()
                    ->color(fn ($state, Asset $record): string => $record->condition_status_color ?? 'secondary'),
                TextColumn::make('qty')->label('Qty')->badge()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordUrl(fn (Asset $record): ?string => AssetResource::canView($record) ? AssetResource::getUrl('view', ['record' => $record]) : null)
            ->emptyStateHeading('Tidak ada aset yang dipegang');
    }
}
