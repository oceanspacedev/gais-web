<?php

namespace App\Filament\Widgets;

use App\Enums\ItemRequestApprovalType;
use App\Enums\ItemRequestClientStatus;
use App\Filament\Resources\ItemRequestResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Support\Dashboard\ChartOptions;
use App\Support\Dashboard\ChartPalette;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dasbor > Pengajuan Barang: pengajuan terbuka per tahap alur, dari persetujuan
 * Accounting sampai konfirmasi pemohon, untuk melihat di mana antrean menumpuk.
 */
class ItemRequestStageChart extends ChartWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Antrean Pengajuan per Tahap';

    protected ?string $description = 'Pengajuan yang masih terbuka, menurut tahap yang sedang ditunggu.';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 4;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $approvalIs = fn (ItemRequestApprovalType $type, bool $approved): \Closure => fn (Builder $approvals) => $approvals
            ->where('approval_type', $type->value)
            ->{$approved ? 'whereNotNull' : 'whereNull'}('approved_by_user_id');

        $waiting = fn (): Builder => ItemRequestResource::getEloquentQuery()->where('client_status', ItemRequestClientStatus::Waiting->value);
        $pastAccounting = fn (): Builder => $waiting()->where(fn (Builder $ready) => $ready
            ->whereHas('type', fn (Builder $type) => $type->where('requires_accounting_approval', false))
            ->orWhereHas('approvals', $approvalIs(ItemRequestApprovalType::Accounting, true)));

        $stages = [
            'Menunggu Accounting' => $waiting()
                ->whereHas('type', fn (Builder $type) => $type->where('requires_accounting_approval', true))
                ->whereHas('approvals', $approvalIs(ItemRequestApprovalType::Accounting, false))
                ->count(),
            'Menunggu Manager' => $pastAccounting()
                ->whereHas('approvals', $approvalIs(ItemRequestApprovalType::Manager, false))
                ->whereHas('approvals', $approvalIs(ItemRequestApprovalType::Executor, false))
                ->count(),
            'Siap diproses Executor' => $pastAccounting()
                ->whereHas('approvals', $approvalIs(ItemRequestApprovalType::Manager, true))
                ->whereHas('approvals', $approvalIs(ItemRequestApprovalType::Executor, false))
                ->count(),
            'Sedang diproses' => ItemRequestResource::getEloquentQuery()->where('client_status', ItemRequestClientStatus::Processed->value)->count(),
            'Menunggu konfirmasi pemohon' => ItemRequestResource::getEloquentQuery()->where('client_status', ItemRequestClientStatus::Closed->value)->count(),
        ];

        return [
            'datasets' => [[
                'label' => 'Pengajuan',
                'data' => array_values($stages),
                'backgroundColor' => [ChartPalette::WARNING, ChartPalette::ORANGE, ChartPalette::PRIMARY, ChartPalette::INFO, ChartPalette::VIOLET],
                'borderRadius' => 4,
            ]],
            'labels' => array_keys($stages),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return ChartOptions::value('int', horizontal: true);
    }
}
