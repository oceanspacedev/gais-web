<?php

namespace App\Filament\Widgets;

use App\Enums\ProblemReportStatus;
use App\Filament\Resources\ProblemReportResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\ProblemReport;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Dasbor > Lapor Gangguan (sumo: "Laporan Gangguan Belum Ditutup"): yang paling
 * lama menunggu lebih dulu, 5 teratas.
 */
class OpenProblemReportsTable extends TableWidget
{
    use GuardsWidgetWithPermission;

    protected int|string|array $columnSpan = 6;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Laporan Gangguan Belum Ditutup')
            ->description('Yang paling lama menunggu ditampilkan lebih dulu.')
            ->query(fn () => ProblemReportResource::getEloquentQuery()->whereNull('closed_by_user_id')->with(['user', 'category'])->oldest('reported_at')->limit(5))
            ->paginated(false)
            ->emptyStateHeading('Tidak ada laporan gangguan yang terbuka')
            ->columns([
                TextColumn::make('code')->label('Kode')->badge()->color('primary')->url(fn (ProblemReport $record): string => ProblemReportResource::getUrl('view', ['record' => $record])),
                TextColumn::make('user.name')->label('Pelapor')->placeholder('-')->limit(20),
                TextColumn::make('category.name')->label('Jenis')->placeholder('-')->limit(18),
                TextColumn::make('reported_at')->label('Dilaporkan')->dateTime('d/m/Y')->description(fn (ProblemReport $record): string => $record->reported_at?->diffForHumans() ?? ''),
                TextColumn::make('scheduled_at')->label('Jadwal')->date('d/m/Y')->placeholder('belum dijadwalkan'),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->headerActions([
                Action::make('all')
                    ->label('Lihat semua')
                    ->link()
                    ->url(ProblemReportResource::getUrl(parameters: ['tableFilters' => ['status' => ['value' => ProblemReportStatus::Pending->value]]])),
            ]);
    }
}
