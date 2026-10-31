<?php

namespace App\Filament\Widgets;

use App\Enums\AssetCondition;
use App\Enums\NbhStatus;
use App\Filament\Resources\AssetResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\Asset;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Dasbor > Aset: aset Rusak / Hilang yang masih butuh tindak lanjut NBH
 * (menunggu dulu, lalu yang terbaru), 5 teratas.
 */
class AssetIncidentsTable extends TableWidget
{
    use GuardsWidgetWithPermission;

    protected int|string|array $columnSpan = 6;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Aset Rusak / Hilang')
            ->description('Insiden yang menunggu NBH ditampilkan lebih dulu.')
            ->query(fn () => AssetResource::getEloquentQuery()
                ->whereIn('condition_status', AssetCondition::incidentValues())
                ->with(['category', 'recipient', 'assetLocation'])
                ->orderByRaw("CASE WHEN nbh_status = '".NbhStatus::Pending->value."' THEN 0 ELSE 1 END")
                ->orderByDesc('nbh_reported_at')
                ->orderByDesc('id')
                ->limit(5))
            ->paginated(false)
            ->emptyStateHeading('Tidak ada aset rusak atau hilang')
            ->emptyStateDescription('Semua aset yang bisa Anda lihat dalam kondisi operasional.')
            ->columns([
                TextColumn::make('name')->label('Aset')->weight('bold')->limit(28)->url(fn (Asset $record): string => AssetResource::getUrl('view', ['record' => $record])),
                TextColumn::make('category.name')->label('Kategori')->placeholder('-')->limit(18),
                TextColumn::make('condition_status')->label('Kondisi')->badge()
                    ->formatStateUsing(fn (AssetCondition $state): string => $state->label())
                    ->color(fn (AssetCondition $state): string => $state->color()),
                TextColumn::make('nbh_status')->label('NBH')->badge()
                    ->formatStateUsing(fn (NbhStatus $state): string => $state->label())
                    ->color(fn (NbhStatus $state): string => $state->color()),
                TextColumn::make('nbh_reported_at')->label('Tgl Insiden')->date('d/m/Y')->placeholder('-'),
                TextColumn::make('recipient.name')->label('Pemegang / Lokasi')->state(fn (Asset $record): string => $record->recipient?->name ?? $record->assetLocation?->name ?? '-')->limit(20),
            ])
            ->headerActions([
                Action::make('all')
                    ->label('Lihat semua')
                    ->link()
                    ->url(AssetResource::getUrl(parameters: ['tableFilters' => ['condition_status' => ['values' => AssetCondition::incidentValues()]]])),
            ]);
    }
}
