<?php

namespace App\Filament\Resources\AssetSaleResource\Pages;

use App\Filament\Resources\AssetSaleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewAssetSale extends ViewRecord
{
    protected static string $resource = AssetSaleResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->loadMissing(['asset', 'fromBusinessEntity', 'toBusinessEntity', 'createdBy']);
    }

    public function getTitle(): string
    {
        return $this->getRecord()->asset?->name ?? 'Detail Penjualan Aset';
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return collect([
            ($record->fromBusinessEntity?->name ?? '-').' → '.($record->toBusinessEntity?->name ?? '-'),
            $record->sold_at?->translatedFormat('d M Y'),
        ])->filter()->implode(' · ');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('Riwayat ini masuk Sampah dan bisa dipulihkan. Kepemilikan aset tidak dibalik otomatis; perbaiki badan usaha aset lewat form aset bila perlu.'),
            EditAction::make()
                ->slideOver()
                ->modalWidth('lg'),
        ];
    }
}
