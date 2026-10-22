<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\InsuranceResource;
use App\Filament\Resources\RentResource;
use App\Models\Rent;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Beranda sumo: Sewa Terdekat Jatuh Tempo (5 teratas), mengikuti hak akses pembaca.
 */
class ExpiringRentsTable extends TableWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 19;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $permission = static::getWidgetPermission();
        $user = Filament::auth()?->user();

        return $permission && $user ? $user->can($permission) : parent::canView();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Sewa Terdekat Jatuh Tempo')
            ->query(fn () => RentResource::getEloquentQuery()->activeContracts()->with('updates')->withMax('updates', 'expired_date')->orderByRaw('COALESCE(updates_max_expired_date, expired_date) ASC')->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('rent_code')->label('Kode')->weight('bold')->url(fn (Rent $record): string => RentResource::getUrl('view', ['record' => $record])),
                TextColumn::make('rented_detail')->label('Objek Sewa')->limit(30),
                TextColumn::make('effective_expired_date')->label('Jatuh Tempo')->state(fn (Rent $record): ?string => $record->effectiveExpiredDate()?->format('d/m/Y'))->color(fn (Rent $record): ?string => InsuranceResource::dueColor($record->effectiveExpiredDate())),
                TextColumn::make('effective_status')->label('Status')->state(fn (Rent $record) => $record->effectiveStatus())->badge(),
            ]);
    }
}
