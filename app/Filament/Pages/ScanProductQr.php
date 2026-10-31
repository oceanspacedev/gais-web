<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ProductResource;
use App\Support\LegacyUpload;
use Filament\Pages\Page;

/**
 * Scan QR barang (sumo: SCAN QR > QR Barang). Isi QR adalah id barang.
 */
class ScanProductQr extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-qr-code';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengajuan Barang';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Scan QR Barang';

    protected string $view = 'filament.pages.scan-product-qr';

    public string $code = '';

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public ?string $error = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:ScanProductQr') ?? false;
    }

    public function lookup(?string $code = null): void
    {
        $this->code = trim((string) ($code ?? $this->code));
        $this->result = null;
        $this->error = null;

        $id = (int) preg_replace('/[^0-9]/', '', $this->code);
        $product = $id > 0 ? ProductResource::getEloquentQuery()->with(['category', 'unitType'])->find($id) : null;

        if ($product === null) {
            $this->error = 'Barang tidak ditemukan.';

            return;
        }

        $this->result = [
            'name' => $product->name,
            'item_code' => $product->item_code ?? '-',
            'category' => $product->category?->name ?? '-',
            'unit' => $product->unitType?->name ?? '-',
            'price' => $product->price === null ? '-' : 'Rp '.number_format((float) $product->price, 0, ',', '.'),
            'stock' => $product->stock === null ? '-' : (float) $product->stock,
            'description' => $product->description ?? '-',
            'image' => LegacyUpload::url(LegacyUpload::PRODUCT_DIR, $product->image),
            'print_url' => route('products.qr-label', $product),
        ];
    }
}
