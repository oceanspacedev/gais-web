<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\VehicleChecksheetResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\VehicleChecksheet;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Dasbor > Operasional: kendaraan yang checksheet keberangkatannya belum
 * ditutup dengan waktu kembali, yang paling lama lebih dulu, 5 teratas.
 */
class VehiclesOutTable extends TableWidget
{
    use GuardsWidgetWithPermission;

    protected int|string|array $columnSpan = 6;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Kendaraan Belum Kembali')
            ->description('Checksheet tanpa waktu kembali, yang paling lama berangkat lebih dulu.')
            ->query(fn () => VehicleChecksheetResource::getEloquentQuery()
                ->whereNull('return_time')
                ->orderBy('departure_time')
                ->orderBy('id')
                ->limit(5))
            ->paginated(false)
            ->emptyStateHeading('Semua kendaraan sudah kembali')
            ->columns([
                TextColumn::make('reference_number')->label('Referensi')->weight('bold')
                    ->url(fn (VehicleChecksheet $record): ?string => VehicleChecksheetResource::canEdit($record) ? VehicleChecksheetResource::getUrl('edit', ['record' => $record]) : null),
                TextColumn::make('license_plate')->label('Plat')->badge()->color('gray'),
                TextColumn::make('pic')->label('PIC')->limit(18)->placeholder('-'),
                TextColumn::make('destination')->label('Tujuan')->limit(20)->placeholder('-'),
                TextColumn::make('departure_time')->label('Berangkat')->dateTime('d/m/Y H:i')->description(fn (VehicleChecksheet $record): string => $record->departure_time?->diffForHumans() ?? ''),
            ])
            ->headerActions([
                Action::make('all')->label('Lihat semua')->link()->url(VehicleChecksheetResource::getUrl()),
            ]);
    }
}
