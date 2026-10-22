<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProblemReportResource;
use App\Models\ProblemReport;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Beranda sumo: Laporan Gangguan Belum Ditutup (5 teratas), mengikuti hak akses pembaca.
 */
class OpenProblemReportsTable extends TableWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 17;

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
            ->heading('Laporan Gangguan Belum Ditutup')
            ->query(fn () => ProblemReportResource::getEloquentQuery()->whereNull('closed_by_user_id')->with(['user', 'category'])->latest('reported_at')->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('code')->label('Kode')->badge()->color('primary')->url(fn (ProblemReport $record): string => ProblemReportResource::getUrl('view', ['record' => $record])),
                TextColumn::make('user.name')->label('Pelapor')->placeholder('-'),
                TextColumn::make('category.name')->label('Jenis')->placeholder('-'),
                TextColumn::make('reported_at')->label('Dilaporkan')->dateTime('d/m/Y H:i'),
                TextColumn::make('status')->label('Status')->badge(),
            ]);
    }
}
