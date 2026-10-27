<?php

namespace App\Filament\Resources\ItemRequestResource\Pages;

use App\Enums\ItemRequestClientStatus;
use App\Filament\Concerns\MemoizesTableQuery;
use App\Filament\Resources\ItemRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListItemRequests extends ListRecords
{
    use MemoizesTableQuery;

    protected static string $resource = ItemRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat Pengajuan'),
        ];
    }

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('Semua')];

        foreach (ItemRequestClientStatus::cases() as $status) {
            $tabs[strtolower($status->name)] = Tab::make($status->label())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('client_status', $status->value));
        }

        return $tabs;
    }
}
