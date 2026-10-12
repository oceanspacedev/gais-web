<?php

namespace App\Http\Controllers;

use App\Models\ItemRequest;
use App\Models\Product;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Cetak label QR pengajuan (satu per barang, isi = id detail) dan QR barang
 * (isi = id barang), kompatibel dengan label lama dari sumo.
 */
class SumoQrLabelController extends Controller
{
    public function itemRequest(ItemRequest $itemRequest): View
    {
        Gate::authorize('view', $itemRequest);

        $itemRequest->loadMissing(['details.product.unitType', 'user', 'type']);

        $labels = $itemRequest->details->map(fn ($detail): array => [
            'title' => $detail->product?->label() ?? 'Barang',
            'subtitle' => $itemRequest->request_code.' · '.number_format((float) ($detail->qty_approved ?? $detail->qty_request), 0, ',', '.'),
            'code' => (string) $detail->id,
            'png' => $this->png((string) $detail->id),
        ])->all();

        return view('qr.sumo-labels', [
            'heading' => 'QR Pengajuan '.$itemRequest->request_code,
            'labels' => $labels,
        ]);
    }

    public function product(Product $product): View
    {
        Gate::authorize('view', $product);

        return view('qr.sumo-labels', [
            'heading' => 'QR Barang',
            'labels' => [[
                'title' => $product->name,
                'subtitle' => $product->item_code ?? '',
                'code' => (string) $product->id,
                'png' => $this->png((string) $product->id),
            ]],
        ]);
    }

    private function png(string $data): string
    {
        return base64_encode(Builder::create()->writer(new PngWriter)->data($data)->size(220)->margin(8)->build()->getString());
    }
}
