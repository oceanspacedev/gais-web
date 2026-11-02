<?php

namespace App\Filament\Resources\AssetQrLabelHistoryResource\Pages;

use App\Filament\Resources\AssetQrLabelHistoryResource;
use App\Models\AssetQrLabelHistory;
use App\Services\AssetQrLabelHistoryService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewAssetQrLabelHistory extends ViewRecord
{
    protected static string $resource = AssetQrLabelHistoryResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->actionLabel();
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return collect([
            $record->created_at?->translatedFormat('d M Y, H:i'),
            $record->user?->name ? 'oleh '.$record->user->name : null,
        ])->filter()->implode(' · ');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadFile')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn (): bool => $this->record instanceof AssetQrLabelHistory && $this->record->hasStoredFile())
                ->action(fn () => app(AssetQrLabelHistoryService::class)->download($this->record)),
        ];
    }
}
