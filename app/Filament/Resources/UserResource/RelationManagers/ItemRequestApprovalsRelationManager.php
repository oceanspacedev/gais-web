<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Enums\ItemRequestApprovalType;
use App\Filament\Resources\ItemRequestResource;
use App\Models\ItemRequest;
use App\Models\ItemRequestApproval;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tahap approval yang disetujui user ini, hanya untuk pengajuan yang boleh
 * dilihat viewer di menu Pengajuan Barang. Hanya dibaca.
 */
class ItemRequestApprovalsRelationManager extends RelationManager
{
    protected static string $relationship = 'itemRequestApprovals';

    protected static ?string $title = 'Approval Pengajuan';

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
        return $owner->itemRequestApprovals()->getQuery()->whereIn(
            'item_request_approvals.item_request_id',
            ItemRequestResource::scopeToViewer(ItemRequest::query(), auth()->user())->select('item_requests.id'),
        );
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => static::visibleQuery($this->getOwnerRecord())->with(['request.user', 'request.type']))
            ->columns([
                TextColumn::make('request.request_code')->label('Pengajuan')->badge()->color('primary')->searchable(),
                TextColumn::make('approval_type')->label('Tahap')->badge(),
                TextColumn::make('request.user.name')->label('Pemohon')->placeholder('-'),
                TextColumn::make('request.type.name')->label('Tipe')->placeholder('-'),
                TextColumn::make('approved_at')->label('Disetujui')->dateTime('d/m/Y H:i')->sortable()->placeholder('-'),
                TextColumn::make('request.client_status')->label('Status Akhir')->badge(),
            ])
            ->filters([
                SelectFilter::make('approval_type')->label('Tahap')->options(ItemRequestApprovalType::class),
            ])
            ->defaultSort('approved_at', 'desc')
            ->recordUrl(fn (ItemRequestApproval $record): ?string => $record->request && ItemRequestResource::canView($record->request)
                ? ItemRequestResource::getUrl('view', ['record' => $record->request])
                : null)
            ->emptyStateHeading('Belum ada approval dari user ini');
    }
}
