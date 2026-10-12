<?php

namespace App\Filament\Pages;

use App\Enums\ItemRequestApprovalType;
use App\Filament\Resources\ItemRequestResource;
use App\Models\ItemRequestDetail;
use App\Models\User;
use Filament\Pages\Page;

/**
 * Scan QR pengajuan (sumo: SCAN QR > QR Pengajuan). Isi QR adalah id detail
 * barang pengajuan, sama dengan label yang sudah dicetak dari sumo.
 */
class ScanItemRequestQr extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-qr-code';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengajuan Barang';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Scan QR Pengajuan';

    protected string $view = 'filament.pages.scan-item-request-qr';

    public string $code = '';

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public ?string $error = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:ScanItemRequestQr') ?? false;
    }

    public function lookup(?string $code = null): void
    {
        $this->code = trim((string) ($code ?? $this->code));
        $this->result = null;
        $this->error = null;

        $id = (int) preg_replace('/[^0-9]/', '', $this->code);

        if ($id <= 0) {
            $this->error = 'Kode QR tidak dikenali.';

            return;
        }

        $detail = ItemRequestDetail::query()->with(['product.unitType', 'request.user.division', 'request.type', 'request.approvals.approver'])->find($id);
        $request = $detail?->request;
        $viewer = auth()->user();

        if ($detail === null || $request === null || ! $viewer instanceof User || ! $viewer->can('view', $request)) {
            $this->error = 'Detail pengajuan tidak ditemukan atau tidak boleh Anda lihat.';

            return;
        }

        $executor = $request->approval(ItemRequestApprovalType::Executor);

        $this->result = [
            'url' => ItemRequestResource::getUrl('view', ['record' => $request]),
            'request_code' => $request->request_code,
            'type' => $request->type?->name ?? '-',
            'requester' => $request->user?->name ?? '-',
            'division' => $request->user?->division?->name ?? '-',
            'requested_at' => $request->requested_at?->format('d/m/Y H:i') ?? '-',
            'product' => $detail->product?->label() ?? '(barang dihapus)',
            'qty_request' => (float) $detail->qty_request,
            'qty_approved' => $detail->qty_approved === null ? '-' : (float) $detail->qty_approved,
            'qty_remaining' => $detail->qty_remaining === null ? '-' : (float) $detail->qty_remaining,
            'description' => $detail->description ?? '-',
            'po_status' => $request->po_status ? 'YA' : 'TIDAK',
            'closed_by' => $executor?->approver?->name ?? '-',
            'closed_at' => $executor?->approved_at?->format('d/m/Y H:i') ?? '-',
            'client_status' => $request->client_status->label(),
            'notes' => $request->notes ?? '-',
            'user_notes' => $request->user_notes ?? '-',
        ];
    }
}
