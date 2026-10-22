<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\InsuranceResource;
use App\Models\Insurance;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Beranda sumo: Asuransi Terdekat Jatuh Tempo (5 teratas), mengikuti hak akses pembaca.
 */
class ExpiringInsurancesTable extends TableWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 18;

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
            ->heading('Asuransi Terdekat Jatuh Tempo')
            ->query(fn () => InsuranceResource::getEloquentQuery()->activeContracts()->with('updates')->withMax('updates', 'expired_date')->orderByRaw('COALESCE(updates_max_expired_date, expired_date) ASC')->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('policy_number')->label('Polis')->weight('bold')->url(fn (Insurance $record): string => InsuranceResource::getUrl('view', ['record' => $record])),
                TextColumn::make('insured_name')->label('Tertanggung'),
                TextColumn::make('effective_expired_date')->label('Jatuh Tempo')->state(fn (Insurance $record): ?string => $record->effectiveExpiredDate()?->format('d/m/Y'))->color(fn (Insurance $record): ?string => InsuranceResource::dueColor($record->effectiveExpiredDate())),
                TextColumn::make('effective_status')->label('Status')->state(fn (Insurance $record) => $record->effectiveStatus())->badge(),
            ]);
    }
}
