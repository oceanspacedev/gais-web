<?php

namespace App\Filament\Resources\ProblemReportResource\Pages;

use App\Filament\Resources\ProblemReportResource;
use Filament\Resources\Pages\ViewRecord;

class ViewProblemReport extends ViewRecord
{
    protected static string $resource = ProblemReportResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->code ?: 'Detail Laporan';
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return ($record->category?->name ?? '-').' · '.$record->status->label();
    }

    protected function getHeaderActions(): array
    {
        return ProblemReportResource::workflowActions();
    }
}
