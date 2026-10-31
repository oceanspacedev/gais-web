<?php

namespace App\Filament\Widgets;

use App\Enums\AssetServiceStatus;
use App\Filament\Resources\AssetServiceResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\AssetService;
use App\Support\Dashboard\Rupiah;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Dasbor > Aset: servis aset yang belum selesai (menunggu atau sedang
 * diservis), yang paling lama lebih dulu, 5 teratas.
 */
class ActiveAssetServicesTable extends TableWidget
{
    use GuardsWidgetWithPermission;

    protected int|string|array $columnSpan = 6;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Servis Aset Berjalan')
            ->description('Servis yang menunggu atau sedang dikerjakan, yang paling lama lebih dulu.')
            ->query(fn () => AssetServiceResource::getEloquentQuery()
                ->whereIn('status', [AssetServiceStatus::Pending->value, AssetServiceStatus::InProgress->value])
                ->with(['asset', 'vendor', 'servicedByUser'])
                ->orderBy('service_date')
                ->orderBy('id')
                ->limit(5))
            ->paginated(false)
            ->emptyStateHeading('Tidak ada servis yang berjalan')
            ->emptyStateDescription('Semua servis aset sudah selesai atau belum ada yang dicatat.')
            ->columns([
                TextColumn::make('service_number')->label('No. Servis')->weight('bold')->url(fn (AssetService $record): string => AssetServiceResource::getUrl('view', ['record' => $record])),
                TextColumn::make('asset.name')->label('Aset')->placeholder('-')->limit(24),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (AssetServiceStatus $state): string => $state->label())
                    ->color(fn (AssetServiceStatus $state): string => $state->color()),
                TextColumn::make('provider')->label('Pelaksana')->state(fn (AssetService $record): string => $record->vendor?->name ?? $record->servicedByUser?->name ?? $record->technician_name ?? '-')->limit(20),
                TextColumn::make('service_date')->label('Tgl Servis')->date('d/m/Y')->placeholder('-'),
                TextColumn::make('total_cost')->label('Biaya')->state(fn (AssetService $record): string => Rupiah::compact($record->total_cost))->tooltip(fn (AssetService $record): string => Rupiah::full($record->total_cost))->alignEnd(),
            ])
            ->headerActions([
                Action::make('all')->label('Lihat semua')->link()->url(AssetServiceResource::getUrl()),
            ]);
    }
}
