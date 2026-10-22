<?php

namespace App\Filament\Resources\ProblemReportResource\Pages;

use App\Exceptions\ItemRequestException;
use App\Filament\Resources\ProblemReportResource;
use App\Services\ProblemReportWorkflow;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProblemReport extends CreateRecord
{
    protected static string $resource = ProblemReportResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(ProblemReportWorkflow::class)->create(auth()->user(), $data);
        } catch (ItemRequestException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
