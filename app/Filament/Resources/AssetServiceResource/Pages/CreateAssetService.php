<?php

namespace App\Filament\Resources\AssetServiceResource\Pages;

use App\Filament\Resources\AssetServiceResource;
use App\Models\AssetService;
use Filament\Resources\Pages\CreateRecord;

class CreateAssetService extends CreateRecord
{
    protected static string $resource = AssetServiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return AssetService::normalizeAssetSource($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
