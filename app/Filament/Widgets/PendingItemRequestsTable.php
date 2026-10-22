<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ItemRequestResource;
use App\Models\ItemRequest;
use App\Services\ItemRequestWorkflow;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Beranda sumo: Pengajuan Menunggu Executor (5 teratas), mengikuti hak akses pembaca.
 */
class PendingItemRequestsTable extends TableWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 16;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $permission = static::getWidgetPermission();
        $user = Filament::auth()?->user();

        return $permission && $user ? $user->can($permission) : parent::canView();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pengajuan Menunggu Executor')
            ->query(fn () => app(ItemRequestWorkflow::class)->scopeAwaitingExecutor(ItemRequestResource::getEloquentQuery())->with(['user', 'type'])->latest('requested_at')->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('request_code')->label('Kode')->badge()->color('primary')->url(fn (ItemRequest $record): string => ItemRequestResource::getUrl('view', ['record' => $record])),
                TextColumn::make('user.name')->label('Pemohon')->placeholder('-'),
                TextColumn::make('type.name')->label('Tipe'),
                TextColumn::make('requested_at')->label('Tanggal')->dateTime('d/m/Y H:i'),
                TextColumn::make('client_status')->label('Status')->badge(),
            ]);
    }
}
