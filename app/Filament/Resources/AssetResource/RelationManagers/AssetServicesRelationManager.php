<?php

namespace App\Filament\Resources\AssetResource\RelationManagers;

use App\Enums\AssetServiceStatus;
use App\Filament\Resources\AssetServiceResource;
use App\Models\Asset;
use App\Models\AssetService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Riwayat servis aset ini, sebatas yang boleh dilihat viewer di menu Servis
 * Aset. Hanya dibaca; servis baru dicatat dari menu Servis Aset.
 */
class AssetServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'services';

    protected static ?string $title = 'Riwayat Servis';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return AssetServiceResource::canViewAny();
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) static::visibleQuery($ownerRecord)->count();
    }

    /**
     * @param  Asset  $owner
     */
    public static function visibleQuery(Model $owner): Builder
    {
        return AssetServiceResource::scopeToViewer($owner->services()->getQuery(), auth()->user());
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => static::visibleQuery($this->getOwnerRecord())
                ->with(['servicedByUser', 'vendor'])
                ->withCount('items'))
            ->columns([
                TextColumn::make('service_number')->label('No. Servis')->badge()->color('primary')->searchable(),
                TextColumn::make('service_date')->label('Tgl Servis')->date('d M Y')->sortable()->placeholder('-'),
                TextColumn::make('provider_label')->label('Pelaksana')->placeholder('-'),
                TextColumn::make('issue_description')->label('Kendala')->limit(60)->wrap()->placeholder('-'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => $state instanceof AssetServiceStatus ? $state->color() : 'gray')
                    ->formatStateUsing(fn ($state) => $state instanceof AssetServiceStatus ? $state->label() : (string) $state),
                TextColumn::make('items_count')->label('Item')->alignEnd()->placeholder('-')
                    ->state(fn (AssetService $record): ?int => $record->items_count ?: null),
                TextColumn::make('total_cost')
                    ->label('Biaya')
                    ->alignEnd()
                    ->placeholder('-')
                    ->state(fn (AssetService $record): ?string => $record->total_cost ? 'Rp '.number_format($record->total_cost, 0, ',', '.') : null),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('service_date')->orderByDesc('id'))
            ->recordUrl(fn (AssetService $record): ?string => AssetServiceResource::canView($record) ? AssetServiceResource::getUrl('view', ['record' => $record]) : null)
            ->emptyStateHeading('Belum ada riwayat servis');
    }
}
