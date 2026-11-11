<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Enums\AssetTransferDocumentType;
use App\Filament\Resources\AssetTransferResource;
use App\Models\AssetTransfer;
use App\Models\User;
use App\Support\StoredFile;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * BA tempat user ini menjadi pengirim atau penerima, dibatasi badan usaha yang
 * boleh dilihat viewer (sama dengan menu Asset Transfer). Hanya dibaca.
 */
class AssetTransfersRelationManager extends RelationManager
{
    protected static string $relationship = 'assetTransfersFrom';

    protected static ?string $title = 'Berita Acara (BA)';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return AssetTransferResource::canViewAny();
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
        $query = AssetTransfer::query()->where(fn (Builder $involved) => $involved
            ->where('asset_transfers.from_user_id', $owner->getKey())
            ->orWhere('asset_transfers.to_user_id', $owner->getKey()));

        return AssetTransferResource::scopeToViewer($query, $viewer);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $ownerId = $this->getOwnerRecord()->getKey();

        return $table
            ->query(fn (): Builder => static::visibleQuery($this->getOwnerRecord())->with(['businessEntity', 'fromUser', 'toUser'])->withCount('details'))
            ->columns([
                TextColumn::make('letter_number')->label('Nomor BA')->badge()->searchable(),
                TextColumn::make('role')
                    ->label('Peran')
                    ->state(fn (AssetTransfer $record): string => (int) $record->from_user_id === (int) $ownerId ? 'Pengirim' : 'Penerima')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Pengirim' ? 'danger' : 'success'),
                TextColumn::make('document_type')
                    ->label('Jenis BA')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof AssetTransferDocumentType ? $state->label() : '-')
                    ->color(fn ($state): string => $state instanceof AssetTransferDocumentType ? $state->color() : 'gray'),
                TextColumn::make('fromUser.name')->label('Dari')->placeholder('-'),
                TextColumn::make('toUser.name')->label('Kepada')->placeholder('-'),
                TextColumn::make('details_count')->label('Aset')->badge()->color('gray'),
                TextColumn::make('businessEntity.name')->label('Badan Usaha')->placeholder('-')->toggleable(),
                TextColumn::make('transfer_date')->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('document')
                    ->label('Dokumen')
                    ->state(fn (AssetTransfer $record): string => $record->document ? 'Dokumen' : '-')
                    ->url(fn (AssetTransfer $record): ?string => $record->document ? StoredFile::url($record->document) : null, true)
                    ->icon('heroicon-o-document-text')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Peran')
                    ->options(['from' => 'Pengirim', 'to' => 'Penerima'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'from' => $query->where('asset_transfers.from_user_id', $ownerId),
                        'to' => $query->where('asset_transfers.to_user_id', $ownerId),
                        default => $query,
                    }),
            ])
            ->defaultSort('transfer_date', 'desc')
            ->recordUrl(fn (AssetTransfer $record): ?string => AssetTransferResource::canView($record) ? AssetTransferResource::getUrl('view', ['record' => $record]) : null)
            ->emptyStateHeading('Belum ada BA untuk user ini');
    }
}
