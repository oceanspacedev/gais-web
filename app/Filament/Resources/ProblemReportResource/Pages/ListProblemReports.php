<?php

namespace App\Filament\Resources\ProblemReportResource\Pages;

use App\Enums\ProblemReportStatus;
use App\Exports\ProblemReportExport;
use App\Filament\Concerns\MemoizesTableQuery;
use App\Filament\Resources\ProblemReportResource;
use App\Models\ProblemReport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListProblemReports extends ListRecords
{
    use MemoizesTableQuery;

    protected static string $resource = ProblemReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Sumo: /problemReport/export untuk satu rentang tanggal pelaporan.
            Action::make('exportReports')
                ->label('Ekspor Laporan')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('export', ProblemReport::class) ?? false)
                ->modalHeading('Ekspor Laporan Gangguan')
                ->modalDescription('Unduh Excel laporan dalam rentang tanggal pelaporan, dengan kolom yang sama seperti ekspor sumo.')
                ->modalWidth('md')
                ->modalSubmitActionLabel('Unduh')
                ->schema([
                    DatePicker::make('from')->label('Dari tanggal')->native(false)->default(now()->startOfMonth())->required(),
                    DatePicker::make('until')->label('Sampai tanggal')->native(false)->default(now()->endOfMonth())->afterOrEqual('from')->required(),
                ])
                ->action(function (array $data): BinaryFileResponse {
                    abort_unless(auth()->user()?->can('export', ProblemReport::class) ?? false, 403);

                    $export = new ProblemReportExport(Carbon::parse($data['from']), Carbon::parse($data['until']));

                    return Excel::download($export, $export->fileName());
                }),
            CreateAction::make()->label('Lapor Gangguan'),
        ];
    }

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('Semua')];

        foreach (ProblemReportStatus::cases() as $status) {
            $tabs[strtolower($status->name)] = Tab::make($status->label())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status->value));
        }

        return $tabs;
    }
}
