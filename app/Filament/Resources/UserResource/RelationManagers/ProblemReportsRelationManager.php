<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\ProblemReportResource;
use App\Models\ProblemReport;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Laporan gangguan yang dibuat user ini, sebatas yang boleh dilihat viewer di
 * menu Lapor Gangguan. Hanya dibaca.
 */
class ProblemReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'problemReports';

    protected static ?string $title = 'Laporan Gangguan';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return ProblemReportResource::canViewAny();
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) static::visibleQuery($ownerRecord)->count();
    }

    /**
     * @param  User  $owner
     */
    public static function visibleQuery(Model $owner): Builder
    {
        return ProblemReportResource::scopeToViewer($owner->problemReports()->getQuery(), auth()->user());
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => static::visibleQuery($this->getOwnerRecord())->with('category'))
            ->columns([
                TextColumn::make('code')->label('Kode')->badge()->color('primary')->searchable(),
                TextColumn::make('category.name')->label('Jenis')->placeholder('-'),
                TextColumn::make('description')->label('Keterangan')->limit(50)->wrap(),
                TextColumn::make('reported_at')->label('Dilaporkan')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('client_status')->label('Konfirmasi')
                    ->state(fn (ProblemReport $record): string => $record->isConfirmed() ? 'Selesai' : 'Menunggu')
                    ->badge()
                    ->color(fn (ProblemReport $record): string => $record->isConfirmed() ? 'success' : 'warning'),
            ])
            ->defaultSort('reported_at', 'desc')
            ->recordUrl(fn (ProblemReport $record): ?string => ProblemReportResource::canView($record) ? ProblemReportResource::getUrl('view', ['record' => $record]) : null)
            ->emptyStateHeading('Belum ada laporan gangguan');
    }
}
