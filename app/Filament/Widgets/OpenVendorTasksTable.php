<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TaskResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\Task;
use App\Support\Dashboard\Rupiah;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Str;

/**
 * Dasbor > Operasional: pekerjaan vendor yang belum selesai, yang paling lama lebih dulu, 5 teratas.
 */
class OpenVendorTasksTable extends TableWidget
{
    use GuardsWidgetWithPermission;

    protected int|string|array $columnSpan = 6;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pekerjaan Vendor Belum Selesai')
            ->description('Status open dan in progress, yang paling lama lebih dulu.')
            ->query(fn () => TaskResource::getEloquentQuery()
                ->whereIn('status', ['open', 'in_progress'])
                ->with(['vendor', 'businessEntity'])
                ->orderBy('work_timestamp')
                ->orderBy('id')
                ->limit(5))
            ->paginated(false)
            ->emptyStateHeading('Tidak ada pekerjaan vendor yang berjalan')
            ->columns([
                TextColumn::make('name')->label('Pekerjaan')->weight('bold')->limit(22)->url(fn (Task $record): string => TaskResource::getUrl('view', ['record' => $record]))
                    ->description(fn (Task $record): string => Str::limit($record->businessEntity?->name ?? '', 22)),
                TextColumn::make('vendor.name')->label('Vendor')->placeholder('-')->limit(16),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'danger',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('work_timestamp')->label('Tgl Kerja')->dateTime('d/m/Y')->placeholder('-'),
                TextColumn::make('cost')->label('Biaya')->state(fn (Task $record): string => Rupiah::compact($record->cost))->tooltip(fn (Task $record): string => Rupiah::full($record->cost))->alignEnd(),
            ])
            ->headerActions([
                Action::make('all')->label('Lihat semua')->link()->url(TaskResource::getUrl()),
            ]);
    }
}
