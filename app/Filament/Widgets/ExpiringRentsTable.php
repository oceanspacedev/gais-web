<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\RentResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\Rent;
use App\Support\ContractDue;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Dasbor > Asuransi & Sewa (sumo: "Sewa Terdekat Jatuh Tempo"), 5 teratas,
 * mengikuti hak akses pembaca.
 */
class ExpiringRentsTable extends TableWidget
{
    use GuardsWidgetWithPermission;

    protected int|string|array $columnSpan = 6;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Sewa Terdekat Jatuh Tempo')
            ->description('Kontrak berjalan dengan tanggal berakhir efektif terdekat (perpanjangan terakhir bila ada).')
            ->query(fn () => RentResource::orderByEffectiveExpiry(RentResource::getEloquentQuery()->activeContracts()->with('updates')->withMax('updates', 'expired_date'))->limit(5))
            ->paginated(false)
            ->emptyStateHeading('Tidak ada sewa yang berjalan')
            ->columns([
                TextColumn::make('rent_code')->label('Kode')->weight('bold')->url(fn (Rent $record): string => RentResource::getUrl('view', ['record' => $record])),
                TextColumn::make('rented_detail')->label('Objek Sewa')->limit(28),
                TextColumn::make('effective_expired_date')->label('Berakhir')
                    ->state(fn (Rent $record): ?string => $record->effectiveExpiredDate()?->format('d/m/Y'))
                    ->description(fn (Rent $record): string => self::remaining($record))
                    ->color(fn (Rent $record): ?string => $record->effectiveDueColor()),
                TextColumn::make('billing')->label('Tagihan')->badge()
                    ->state(fn (Rent $record): string => $record->effectiveBillingSummary()['status'])
                    ->color(fn (string $state): string => Rent::billingColor($state)),
                TextColumn::make('effective_status')->label('Status')->state(fn (Rent $record) => $record->effectiveStatus())->badge(),
            ])
            ->headerActions([
                Action::make('all')
                    ->label('Lihat semua')
                    ->link()
                    ->url(RentResource::getUrl(parameters: ['tableFilters' => ['due_soon' => ['isActive' => true]]])),
            ]);
    }

    public static function remaining(Rent $record): string
    {
        $expiry = $record->effectiveExpiredDate();

        if ($expiry === null) {
            return '';
        }

        $days = ContractDue::daysUntil($expiry);

        return $days < 0 ? 'lewat '.abs($days).' hari' : ($days === 0 ? 'hari ini' : $days.' hari lagi');
    }
}
