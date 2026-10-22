<?php

namespace App\Filament\Resources\ProblemReportResource\Pages;

use App\Enums\ProblemReportStatus;
use App\Filament\Resources\ProblemReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProblemReports extends ListRecords
{
    protected static string $resource = ProblemReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
