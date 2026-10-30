<?php

namespace App\Filament\Resources\ItemRequestResource\Pages;

use App\Enums\ItemRequestClientStatus;
use App\Enums\ItemRequestRecapStatus;
use App\Filament\Concerns\MemoizesTableQuery;
use App\Filament\Resources\ItemRequestResource;
use App\Models\Area;
use App\Models\ItemRequest;
use App\Models\ItemRequestType;
use App\Services\ItemRequestMasterQrExport;
use App\Services\ItemRequestRecapExport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListItemRequests extends ListRecords
{
    use MemoizesTableQuery;

    protected static string $resource = ItemRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->exportRecapAction(),
            $this->exportMasterQrAction(),
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

    /**
     * Rekap per Divisi (sumo: RequestExport): pivot barang x divisi untuk satu
     * area, tipe, rentang tanggal pengajuan, dan filter status.
     */
    protected function exportRecapAction(): Action
    {
        return Action::make('exportRecap')
            ->label('Rekap per Divisi')
            ->icon('heroicon-o-table-cells')
            ->color('gray')
            ->visible(fn (): bool => $this->canExport())
            ->modalHeading('Rekap Pengajuan per Divisi')
            ->modalDescription('Unduh Excel jumlah barang per divisi pemohon. Jumlah memakai jumlah disetujui bila sudah diisi, kalau belum jumlah diminta.')
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Unduh')
            ->schema([
                Select::make('area_id')
                    ->label('Area')
                    ->options(fn (): array => Area::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
                Select::make('item_request_type_id')
                    ->label('Tipe pengajuan')
                    ->options(fn (): array => ItemRequestType::query()->orderBy('id')->pluck('name', 'id')->all())
                    ->required(),
                ...$this->dateRangeFields(),
                Select::make('status')
                    ->label('Filter status')
                    ->options(ItemRequestRecapStatus::class)
                    ->default(ItemRequestRecapStatus::NotCancelled->value)
                    ->selectablePlaceholder(false)
                    ->required(),
            ])
            ->action(function (array $data): BinaryFileResponse {
                abort_unless($this->canExport(), 403);

                $export = new ItemRequestRecapExport(
                    auth()->user(),
                    Area::query()->findOrFail($data['area_id']),
                    ItemRequestType::query()->findOrFail($data['item_request_type_id']),
                    Carbon::parse($data['from']),
                    Carbon::parse($data['until']),
                    ItemRequestRecapStatus::coerce($data['status'] ?? ItemRequestRecapStatus::NotCancelled->value),
                );

                return Excel::download($export, $export->fileName());
            });
    }

    /**
     * Ekspor Master QR (sumo: RequestMasterQRExport): id detail, nama barang,
     * pemohon untuk cetak label QR massal.
     */
    protected function exportMasterQrAction(): Action
    {
        return Action::make('exportMasterQr')
            ->label('Ekspor Master QR')
            ->icon('heroicon-o-qr-code')
            ->color('gray')
            ->visible(fn (): bool => $this->canExport())
            ->modalHeading('Ekspor Master QR')
            ->modalDescription('Unduh Excel berisi id detail pengajuan (isi QR), nama barang, dan pemohon, satu baris per barang, untuk cetak label QR massal.')
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Unduh')
            ->schema([
                Select::make('item_request_type_id')
                    ->label('Tipe pengajuan')
                    ->options(fn (): array => ItemRequestType::query()->orderBy('id')->pluck('name', 'id')->all())
                    ->required(),
                ...$this->dateRangeFields(),
            ])
            ->action(function (array $data): BinaryFileResponse {
                abort_unless($this->canExport(), 403);

                $export = new ItemRequestMasterQrExport(
                    auth()->user(),
                    ItemRequestType::query()->findOrFail($data['item_request_type_id']),
                    Carbon::parse($data['from']),
                    Carbon::parse($data['until']),
                );

                return Excel::download($export, $export->fileName());
            });
    }

    /**
     * Rentang tanggal pengajuan (requested_at), default bulan berjalan.
     *
     * @return list<Grid>
     */
    private function dateRangeFields(): array
    {
        return [
            Grid::make(2)->schema([
                DatePicker::make('from')
                    ->label('Dari tanggal')
                    ->native(false)
                    ->default(now()->startOfMonth())
                    ->required(),
                DatePicker::make('until')
                    ->label('Sampai tanggal')
                    ->native(false)
                    ->default(now()->endOfMonth())
                    ->afterOrEqual('from')
                    ->required(),
            ]),
        ];
    }

    private function canExport(): bool
    {
        return auth()->user()?->can('export', ItemRequest::class) ?? false;
    }
}
