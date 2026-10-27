<?php

namespace App\Filament\Resources;

use App\Enums\ProblemReportStatus;
use App\Exceptions\ItemRequestException;
use App\Filament\Resources\ProblemReportResource\Pages;
use App\Models\ProblemReport;
use App\Models\User;
use App\Services\ProblemReportWorkflow;
use App\Support\LegacyUpload;
use App\Support\RelationSearch;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/**
 * Laporan gangguan (sumo: LAPOR GANGGUAN). Pelapor membuat dengan foto,
 * executor memproses, pelapor mengonfirmasi hasilnya.
 */
class ProblemReportResource extends Resource
{
    protected static ?string $model = ProblemReport::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    protected static string|\UnitEnum|null $navigationGroup = 'Lapor Gangguan';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getModelLabel(): string
    {
        return __('Laporan Gangguan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Laporan Gangguan');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', ProblemReportStatus::Pending->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return static::scopeToViewer($query, auth()->user());
    }

    /**
     * Aturan lihat yang sama dengan menu ini, juga dipakai tab di halaman user.
     */
    public static function scopeToViewer(Builder $query, mixed $viewer): Builder
    {
        if (! $viewer instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        $query->visibleTo($viewer);

        if (! $viewer->hasUnrestrictedBusinessEntityAccess()) {
            $query->where(fn (Builder $scoped) => $scoped
                ->whereNull('problem_reports.business_entity_id')
                ->orWhereIn('problem_reports.business_entity_id', $viewer->accessibleBusinessEntityIds()));
        }

        return $query;
    }

    public static function form(Schema $form): Schema
    {
        $viewer = auth()->user();
        $onBehalf = $viewer?->can('process', ProblemReport::class) ?? false;

        return $form
            ->columns(1)
            ->schema([
                Section::make('Laporan')
                    ->schema([
                        Select::make('user_id')
                            ->label('Pelapor')
                            ->relationship('user', 'name', fn (Builder $query): Builder => $query->orderBy('name'))
                            ->default($viewer?->id)
                            ->searchable()
                            ->preload(false)
                            ->required()
                            ->visible($onBehalf),
                        Select::make('problem_report_category_id')
                            ->label('Jenis Gangguan')
                            ->relationship('category', 'name')
                            ->preload()
                            ->required(),
                        TextInput::make('title')
                            ->label('Judul (opsional)')
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Keterangan Gangguan')
                            ->rows(4)
                            ->required(),
                        FileUpload::make('photo_before')
                            ->label('Foto Gangguan / Kerusakan')
                            ->image()
                            ->directory(LegacyUpload::PROBLEM_REPORT_DIR)
                            ->maxSize(5120)
                            ->imagePreviewHeight('200')
                            ->required(),
                    ]),
            ]);
    }

    public static function infolist(Schema $infolist): Schema
    {
        return $infolist
            ->columns(1)
            ->schema([
                Section::make('Ringkasan')->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('code')->label('Kode')->badge()->color('primary'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('client_status')->label('Konfirmasi Pelapor')
                            ->state(fn (ProblemReport $record): string => $record->isConfirmed() ? 'Selesai' : 'Menunggu')
                            ->badge()
                            ->color(fn (ProblemReport $record): string => $record->isConfirmed() ? 'success' : 'warning'),
                        TextEntry::make('user.name')->label('Pelapor'),
                        TextEntry::make('user.division.name')->label('Divisi')->placeholder('-'),
                        TextEntry::make('category.name')->label('Jenis')->placeholder('-'),
                        TextEntry::make('reported_at')->label('Dilaporkan')->dateTime('d/m/Y H:i'),
                        TextEntry::make('scheduled_at')->label('Jadwal Pengerjaan')->date('d/m/Y')->placeholder('-'),
                        TextEntry::make('closedBy.name')->label('Ditutup Oleh')->placeholder('-'),
                    ]),
                    TextEntry::make('title')->label('Judul')->placeholder('-'),
                    TextEntry::make('description')->label('Keterangan')->placeholder('-'),
                    TextEntry::make('result_desc')->label('Hasil Pengerjaan')->placeholder('-'),
                    TextEntry::make('review_user')->label('Ulasan Pelapor')->placeholder('-'),
                ]),
                Section::make('Foto')->schema([
                    Grid::make(2)->schema([
                        ImageEntry::make('photo_before')->label('Sebelum')->disk('public')
                            ->state(fn (ProblemReport $record): ?string => LegacyUpload::path(LegacyUpload::PROBLEM_REPORT_DIR, $record->photo_before))
                            ->height(260),
                        ImageEntry::make('photo_after')->label('Sesudah')->disk('public')
                            ->state(fn (ProblemReport $record): ?string => LegacyUpload::path(LegacyUpload::PROBLEM_REPORT_DIR, $record->photo_after))
                            ->height(260)
                            ->placeholder('Belum ada'),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user.division', 'category', 'closedBy']))
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable()->badge()->color('primary'),
                TextColumn::make('user.name')->label('Pelapor')->searchable(query: RelationSearch::on('user', 'name'))->sortable()->description(fn (ProblemReport $record): ?string => $record->user?->division?->name),
                TextColumn::make('category.name')->label('Jenis')->sortable()->placeholder('-'),
                TextColumn::make('description')->label('Keterangan')->limit(50)->wrap()->toggleable(),
                TextColumn::make('reported_at')->label('Dilaporkan')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('scheduled_at')->label('Jadwal')->date('d/m/Y')->placeholder('-')->toggleable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('client_status')->label('Konfirmasi')
                    ->state(fn (ProblemReport $record): string => $record->isConfirmed() ? 'Selesai' : 'Menunggu')
                    ->badge()
                    ->color(fn (ProblemReport $record): string => $record->isConfirmed() ? 'success' : 'warning'),
                TextColumn::make('closedBy.name')->label('Ditutup Oleh')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('reported_at', 'desc')
            ->filters([
                SelectFilter::make('problem_report_category_id')->label('Jenis')->relationship('category', 'name')->preload(),
                SelectFilter::make('status')->label('Status')->options(ProblemReportStatus::class),
                SelectFilter::make('client_status')->label('Konfirmasi')->options([0 => 'Menunggu', 1 => 'Selesai']),
                Filter::make('reported_at')
                    ->schema([
                        DatePicker::make('from')->label('Dari tanggal')->native(false),
                        DatePicker::make('until')->label('Sampai tanggal')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('reported_at', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $q, string $until) => $q->whereDate('reported_at', '<=', $until))),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    ...static::workflowActions(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->label('Ekspor Excel')
                        ->exports([
                            ExcelExport::make('gangguan')->fromTable()->withFilename('laporan-gangguan-'.now()->format('Ymd-His')),
                        ])
                        ->visible(fn (): bool => auth()->user()?->can('export', ProblemReport::class) ?? false),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return list<Action>
     */
    public static function workflowActions(): array
    {
        $workflow = app(ProblemReportWorkflow::class);
        $user = fn (): ?User => auth()->user();

        return [
            Action::make('process')
                ->label('Proses (Executor)')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('info')
                ->modalHeading('Proses laporan gangguan')
                ->modalWidth('lg')
                ->visible(fn (ProblemReport $record) => ($user()?->can('process', ProblemReport::class) ?? false)
                    && (! $record->status->isFinal() || ! $record->isConfirmed()))
                ->form(fn (ProblemReport $record): array => [
                    Radio::make('status')->label('Status')->options(ProblemReportStatus::class)->default($record->status->value)->required(),
                    DatePicker::make('scheduled_at')->label('Jadwal Pengerjaan')->default($record->scheduled_at)->native(false),
                    FileUpload::make('photo_after')->label('Foto Hasil')->image()->directory(LegacyUpload::PROBLEM_REPORT_DIR)->maxSize(5120),
                    Textarea::make('result_desc')->label('Hasil Pengerjaan')->default($record->result_desc)->rows(3),
                ])
                ->action(function (ProblemReport $record, array $data) use ($workflow, $user): void {
                    static::runWorkflow(fn () => $workflow->process(
                        $record,
                        $user(),
                        ($data['status'] instanceof ProblemReportStatus ? $data['status'] : ProblemReportStatus::from($data['status'])),
                        filled($data['scheduled_at'] ?? null) ? Carbon::parse($data['scheduled_at']) : null,
                        $data['photo_after'] ?? null,
                        $data['result_desc'] ?? null,
                    ), 'Laporan diperbarui.');
                }),

            Action::make('confirm')
                ->label('Konfirmasi Selesai')
                ->icon('heroicon-o-hand-thumb-up')
                ->color('success')
                ->modalHeading('Konfirmasi gangguan sudah selesai?')
                ->modalWidth('md')
                ->visible(fn (ProblemReport $record) => $record->isOwnedBy($user())
                    && $record->status === ProblemReportStatus::Closed
                    && ! $record->isConfirmed())
                ->form([
                    Textarea::make('review_user')->label('Ulasan (opsional)')->rows(2),
                ])
                ->action(fn (ProblemReport $record, array $data) => static::runWorkflow(
                    fn () => $workflow->confirm($record, $user(), $data['review_user'] ?? null),
                    'Terima kasih, laporan ditandai selesai.',
                )),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProblemReports::route('/'),
            'create' => Pages\CreateProblemReport::route('/create'),
            'view' => Pages\ViewProblemReport::route('/{record}'),
        ];
    }

    private static function runWorkflow(callable $callback, string $successMessage): void
    {
        try {
            $callback();
            Notification::make()->title($successMessage)->success()->send();
        } catch (ItemRequestException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }
}
