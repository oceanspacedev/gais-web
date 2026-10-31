<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ItemRequestResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\ItemRequest;
use App\Services\ItemRequestWorkflow;
use App\Support\Dashboard\Rupiah;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Dasbor > Pengajuan Barang (sumo: "Pengajuan Menunggu Executor"): yang paling
 * lama menunggu lebih dulu, beserta tahap yang masih ditunggu dan nilainya.
 */
class PendingItemRequestsTable extends TableWidget
{
    use GuardsWidgetWithPermission;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $workflow = app(ItemRequestWorkflow::class);

        return $table
            ->heading('Pengajuan Menunggu Executor')
            ->description('Yang paling lama menunggu ditampilkan lebih dulu.')
            ->query(fn () => $workflow->scopeAwaitingExecutor(ItemRequestResource::getEloquentQuery())
                ->with(['user', 'type', 'approvals', 'details.product'])
                ->oldest('requested_at')
                ->limit(5))
            ->paginated(false)
            ->emptyStateHeading('Tidak ada pengajuan yang menunggu executor')
            ->columns([
                TextColumn::make('request_code')->label('Kode')->badge()->color('primary')->url(fn (ItemRequest $record): string => ItemRequestResource::getUrl('view', ['record' => $record])),
                TextColumn::make('user.name')->label('Pemohon')->placeholder('-')->limit(24),
                TextColumn::make('type.name')->label('Tipe'),
                TextColumn::make('stage')->label('Tahap Ditunggu')->badge()->color('warning')
                    ->state(fn (ItemRequest $record): string => $workflow->pendingStageBeforeExecutor($record)?->label() ?? 'Executor'),
                TextColumn::make('requested_at')->label('Diajukan')->dateTime('d/m/Y H:i')->description(fn (ItemRequest $record): string => $record->requested_at?->diffForHumans() ?? ''),
                TextColumn::make('total')->label('Nilai')->state(fn (ItemRequest $record): string => Rupiah::full($record->grandTotal()))->alignEnd(),
                TextColumn::make('client_status')->label('Status')->badge(),
            ])
            ->headerActions([
                Action::make('all')
                    ->label('Lihat semua')
                    ->link()
                    ->url(ItemRequestResource::getUrl(parameters: ['tableFilters' => ['awaiting_executor' => ['isActive' => true]]])),
            ]);
    }
}
