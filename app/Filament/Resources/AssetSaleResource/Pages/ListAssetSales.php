<?php

namespace App\Filament\Resources\AssetSaleResource\Pages;

use App\Exports\AssetSaleExport;
use App\Filament\Actions\DownloadExcelAction;
use App\Filament\Resources\AssetSaleResource;
use App\Imports\AssetSaleImport;
use App\Models\Asset;
use App\Models\AssetSale;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;

class ListAssetSales extends ListRecords
{
    protected static string $resource = AssetSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Penjualan massal: satu baris Excel = satu aset, lewat AssetSale::record().
            ExcelImportAction::make('importSales')
                ->label('Impor Excel')
                ->color('gray')
                ->modalHeading('Impor Penjualan Aset')
                ->modalDescription('Unggah Excel berkolom template: satu baris = satu aset yang dijual ke badan usaha lain. Aset dikenali dari id_aset atau serial_number; badan usaha ditulis sesuai nama di master. Kepemilikan langsung berpindah ke pembeli, dan satu baris salah membatalkan seluruh file.')
                ->use(AssetSaleImport::class)
                ->sampleExcel(sampleData: [AssetSaleImport::sampleRow()], fileName: 'template-penjualan-aset.xlsx', sampleButtonLabel: 'Unduh template')
                ->uploadField(fn (FileUpload $upload): FileUpload => $upload->label('File Excel'))
                ->visible(fn (): bool => auth()->user()?->can('import', AssetSale::class) ?? false),
            DownloadExcelAction::make('exportAll', 'Ekspor Semua Penjualan', fn (): AssetSaleExport => new AssetSaleExport)
                ->visible(fn (): bool => auth()->user()?->can('export', AssetSale::class) ?? false),
            Action::make('record')
                ->label('Catat Penjualan')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => auth()->user()?->can('create', AssetSale::class) ?? false)
                ->modalHeading('Catat Penjualan ke Badan Usaha Lain')
                ->modalDescription('Kepemilikan aset langsung berpindah ke badan usaha pembeli dan aset masuk stok pembeli (Tersedia, tanpa pemegang).')
                ->modalSubmitActionLabel('Catat Penjualan')
                ->slideOver()
                ->modalWidth('md')
                ->form([
                    Select::make('asset_id')
                        ->label('Aset')
                        ->options(fn (): array => AssetSaleResource::sellableAssetOptions())
                        ->getSearchResultsUsing(fn (string $search): array => AssetSaleResource::sellableAssetOptions($search))
                        ->getOptionLabelUsing(function (mixed $value): ?string {
                            $asset = static::sellableAsset($value);

                            return $asset ? AssetSaleResource::assetOptionLabel($asset) : null;
                        })
                        ->searchable()
                        ->required()
                        ->live()
                        ->helperText('Hanya aset Tersedia (stok) di badan usaha yang bisa Anda akses. Aset yang masih dipegang harus dikembalikan dulu lewat BA Pengembalian.'),
                    Placeholder::make('owner')
                        ->label('Pemilik Saat Ini')
                        ->content(fn (Get $get): string => static::sellableAsset($get('asset_id'))?->businessEntity?->name ?? '-'),
                    AssetSaleResource::buyerSelect(fn (Get $get): mixed => static::sellableAsset($get('asset_id'))?->business_entity_id),
                    ...AssetSaleResource::saleDetailFields(),
                ])
                ->action(function (array $data): void {
                    $asset = AssetSaleResource::sellableAssets()->findOrFail($data['asset_id']);
                    $sale = AssetSaleResource::recordSale($asset, $data);

                    Notification::make()
                        ->title('Penjualan dicatat')
                        ->body(sprintf('%s kini milik %s dan masuk stoknya.', $asset->name, $sale->toBusinessEntity?->name ?? '-'))
                        ->success()
                        ->send();
                }),
        ];
    }

    private static function sellableAsset(mixed $assetId): ?Asset
    {
        if (blank($assetId)) {
            return null;
        }

        return AssetSaleResource::sellableAssets()->with('businessEntity')->find($assetId);
    }
}
