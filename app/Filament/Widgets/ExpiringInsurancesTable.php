<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\InsuranceResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\Insurance;
use App\Support\ContractDue;
use App\Support\Dashboard\Rupiah;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Dasbor > Asuransi & Sewa (sumo: "Asuransi Terdekat Jatuh Tempo"), 5 teratas,
 * mengikuti hak akses pembaca.
 */
class ExpiringInsurancesTable extends TableWidget
{
    use GuardsWidgetWithPermission;

    protected int|string|array $columnSpan = 6;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Asuransi Terdekat Jatuh Tempo')
            ->description('Polis berjalan dengan jatuh tempo efektif terdekat (pembaharuan terakhir bila ada).')
            ->query(fn () => InsuranceResource::orderByEffectiveExpiry(InsuranceResource::getEloquentQuery()->activeContracts()->with('updates')->withMax('updates', 'expired_date'))->limit(5))
            ->paginated(false)
            ->emptyStateHeading('Tidak ada polis yang berjalan')
            ->columns([
                TextColumn::make('policy_number')->label('Polis')->weight('bold')->limit(22)->url(fn (Insurance $record): string => InsuranceResource::getUrl('view', ['record' => $record])),
                TextColumn::make('insured_name')->label('Tertanggung')->limit(26),
                TextColumn::make('effective_expired_date')->label('Jatuh Tempo')
                    ->state(fn (Insurance $record): ?string => $record->effectiveExpiredDate()?->format('d/m/Y'))
                    ->description(fn (Insurance $record): string => self::remaining($record))
                    ->color(fn (Insurance $record): ?string => $record->effectiveDueColor()),
                TextColumn::make('premium')->label('Premi')->state(fn (Insurance $record): string => Rupiah::compact((int) $record->effective('stock_premium') + (int) $record->effective('building_premium')))->alignEnd(),
                TextColumn::make('effective_status')->label('Status')->state(fn (Insurance $record) => $record->effectiveStatus())->badge(),
            ])
            ->headerActions([
                Action::make('all')
                    ->label('Lihat semua')
                    ->link()
                    ->url(InsuranceResource::getUrl(parameters: ['tableFilters' => ['due_soon' => ['isActive' => true]]])),
            ]);
    }

    public static function remaining(Insurance $record): string
    {
        $expiry = $record->effectiveExpiredDate();

        if ($expiry === null) {
            return '';
        }

        $days = ContractDue::daysUntil($expiry);

        return $days < 0 ? 'lewat '.abs($days).' hari' : ($days === 0 ? 'hari ini' : $days.' hari lagi');
    }
}
