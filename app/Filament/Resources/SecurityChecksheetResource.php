<?php

namespace App\Filament\Resources;

use App\Access\ScopesVisibleRecords;
use App\Filament\Exports\SecurityChecksheetExporter;
use App\Filament\Resources\SecurityChecksheetResource\Pages;
use App\Models\AssetLocation;
use App\Models\SecurityChecksheet;
use App\Support\RelationSearch;
use App\Support\SecurityChecklist;
use App\Support\StoredFile;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SecurityChecksheetResource extends Resource
{
    use ScopesVisibleRecords;

    /** Batas per lampiran (KB); video dari HP jauh lebih besar daripada foto. */
    public const MAX_ATTACHMENT_KB = 51200;

    public const MAX_ATTACHMENTS = 10;

    protected static ?string $model = SecurityChecksheet::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('Security Checksheet');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Security Checksheets');
    }

    public static function form(Schema $form): Schema
    {
        // Di layar lebar kotak checklist, temuan, dan lampiran berdampingan dua kolom; di HP satu kolom.
        return $form
            ->columns(['default' => 1, 'lg' => 2])
            ->schema([
                Section::make('Informasi Laporan')
                    ->compact()
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'md' => 3])
                            ->schema([
                                TextInput::make('location')
                                    ->label('Lokasi Kerja')
                                    ->placeholder('Contoh: HO Petra')
                                    ->datalist(fn (): array => AssetLocation::query()->orderBy('name')->pluck('name')->all())
                                    ->default(fn (): ?string => self::lastLocation())
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(['sm' => 2, 'md' => 1]),

                                TextInput::make('reference_number')
                                    ->label('Nomor Referensi')
                                    ->readOnly()
                                    ->hiddenOn('create'),

                                // Hanya atasan yang boleh mengganti petugas; field nonaktif tidak ikut tersimpan.
                                Select::make('user_id')
                                    ->label('Petugas Security')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->disabled(fn (): bool => ! (auth()->user()?->can('updateAll', SecurityChecksheet::class) ?? false))
                                    ->hiddenOn('create'),

                                DatePicker::make('report_date')
                                    ->label('Tanggal')
                                    ->hiddenOn('create'),

                                DateTimePicker::make('checked_at')
                                    ->label('Waktu Pengecekan')
                                    ->seconds(false)
                                    ->hiddenOn('create'),
                            ]),
                    ]),

                // Laporan baru memakai pertanyaan aktif; laporan lama memakai pertanyaan yang tersimpan di laporannya.
                Group::make()
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema(fn (?SecurityChecksheet $record): array => SecurityChecklist::formSections(
                        $record ? SecurityChecklist::recordDefinitions($record) : SecurityChecklist::activeDefinitions(),
                    )),

                Section::make('Kondisi Darurat / Temuan Kejadian')
                    ->compact()
                    ->columnSpan(1)
                    ->schema([
                        Textarea::make('findings')
                            ->hiddenLabel()
                            ->validationAttribute('Temuan kejadian')
                            ->placeholder('Kosongkan bila tidak ada kejadian. Bila ada, tulis apa, di mana, dan jam berapa.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Lampiran Foto / Video')
                    ->compact()
                    ->columnSpan(1)
                    ->schema([
                        FileUpload::make('attachments')
                            ->hiddenLabel()
                            ->validationAttribute('Lampiran')
                            ->helperText('Foto atau video, bisa lebih dari satu. Maks. '.self::MAX_ATTACHMENTS.' file, '.(self::MAX_ATTACHMENT_KB / 1024).' MB per file.')
                            ->multiple()
                            ->acceptedFileTypes(['image/*', 'video/*'])
                            ->maxFiles(self::MAX_ATTACHMENTS)
                            ->maxSize(self::MAX_ATTACHMENT_KB)
                            ->disk('public')
                            ->directory('security-checksheets')
                            ->visibility(fn (): string => StoredFile::uploadVisibility())
                            ->openable()
                            ->downloadable()
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Schema $infolist): Schema
    {
        // Satu kolom selebar layar; nomor referensi sudah jadi judul halaman.
        return $infolist
            ->columns(1)
            ->schema([
                Section::make('Informasi Laporan')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Grid::make(['default' => 2, 'lg' => 4])->schema([
                            TextEntry::make('location')
                                ->label('Lokasi Kerja')
                                ->weight(FontWeight::SemiBold)
                                ->icon('heroicon-o-map-pin'),
                            TextEntry::make('user.name')
                                ->label('Petugas Security')
                                ->icon('heroicon-o-user')
                                ->placeholder('-'),
                            TextEntry::make('report_date')->label('Tanggal')->date('d M Y')->placeholder('-'),
                            TextEntry::make('checked_at')->label('Waktu Pengecekan')->dateTime('d M Y, H:i')->placeholder('-'),
                        ]),
                    ]),
                Section::make('Checklist')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->schema([
                        ViewEntry::make('checklist')
                            ->hiddenLabel()
                            ->view('filament.security-checksheet.checklist'),
                    ]),
                Section::make('Kondisi Darurat / Temuan Kejadian')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->schema([
                        TextEntry::make('findings')
                            ->hiddenLabel()
                            ->placeholder('Tidak ada temuan.')
                            ->color(fn (?string $state): ?string => filled($state) ? 'danger' : null),
                    ]),
                Section::make('Lampiran Foto / Video')
                    ->icon('heroicon-o-camera')
                    ->schema([
                        ViewEntry::make('attachments')
                            ->hiddenLabel()
                            ->view('filament.security-checksheet.attachments'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Tampilan kartu ringkas seperti OB Checksheet; checklist dan lampiran dibuka dari halaman detail.
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('reference_number')
                            ->label('No. Referensi')
                            ->searchable()
                            ->sortable()
                            ->color('gray')
                            ->size(TextSize::ExtraSmall),
                        TextColumn::make('findings_status')
                            ->label('Temuan')
                            ->badge()
                            // "Aman" hanya bila tidak ada temuan dan semua jawaban sesuai kunci.
                            ->state(fn (SecurityChecksheet $record): ?string => match (true) {
                                $record->hasFindings() => 'Ada Temuan',
                                $record->hasMismatch() => null,
                                default => 'Aman',
                            })
                            ->color(fn (string $state): string => $state === 'Aman' ? 'success' : 'danger')
                            ->grow(false),
                        TextColumn::make('mismatch_count')
                            ->label('Tidak Sesuai')
                            ->badge()
                            ->color('warning')
                            ->icon('heroicon-m-exclamation-circle')
                            ->formatStateUsing(fn (int $state): string => "{$state} tidak sesuai")
                            ->state(fn (SecurityChecksheet $record): ?int => $record->hasMismatch() ? $record->mismatch_count : null)
                            ->grow(false),
                    ]),

                    TextColumn::make('location')
                        ->label('Lokasi Kerja')
                        ->searchable()
                        ->sortable()
                        ->weight(FontWeight::Bold)
                        ->description(fn (SecurityChecksheet $record): string => $record->checked_at?->format('d M H:i') ?? '')
                        ->wrap(),

                    Split::make([
                        TextColumn::make('user.name')
                            ->label('Petugas Security')
                            ->icon('heroicon-m-user')
                            ->placeholder('-')
                            ->searchable(query: RelationSearch::on('user', 'name'))
                            ->sortable(),
                        TextColumn::make('report_date')
                            ->label('Tanggal')
                            ->icon('heroicon-m-calendar-days')
                            ->date('d M Y')
                            ->sortable()
                            ->grow(false),
                    ]),

                    TextColumn::make('attachment_count')
                        ->label('Lampiran')
                        ->icon('heroicon-m-paper-clip')
                        ->color('gray')
                        ->state(fn (SecurityChecksheet $record): ?string => ($count = count($record->attachmentPaths())) > 0 ? "{$count} lampiran" : null),

                    TextColumn::make('findings')
                        ->label('Temuan Kejadian')
                        ->color('danger')
                        ->limit(80),
                ])->space(2),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->paginated([12, 24, 48])
            ->defaultPaginationPageOption(12)
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('report_date')->orderByDesc('id'))
            ->filters([
                TrashedFilter::make(),
                Filter::make('report_date')
                    ->label('Tanggal')
                    ->schema([
                        DatePicker::make('from')->label('Dari Tanggal'),
                        DatePicker::make('until')->label('Sampai Tanggal'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('report_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('report_date', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'Dari '.Carbon::parse($data['from'])->format('d M Y');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Sampai '.Carbon::parse($data['until'])->format('d M Y');
                        }

                        return $indicators;
                    }),

                Filter::make('has_mismatch')
                    ->label('Ada jawaban tidak sesuai')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('mismatch_count', '>', 0)),

                Filter::make('has_findings')
                    ->label('Ada temuan kejadian')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('findings')->where('findings', '!=', '')),

                SelectFilter::make('user_id')
                    ->label('Petugas Security')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => auth()->user()?->can('viewAll', SecurityChecksheet::class) ?? false),
            ])
            ->actions([
                RestoreAction::make(),
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    ExportBulkAction::make()
                        ->exporter(SecurityChecksheetExporter::class)
                        ->label('Export Dipilih')
                        ->visible(fn () => auth()->user()?->can('export', SecurityChecksheet::class) ?? false),
                    // Cek tiap record ke policy delete: data milik user lain butuh izin "Hapus Data Semua User".
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSecurityChecksheets::route('/'),
            'create' => Pages\CreateSecurityChecksheet::route('/create'),
            'view' => Pages\ViewSecurityChecksheet::route('/{record}'),
            'edit' => Pages\EditSecurityChecksheet::route('/{record}/edit'),
        ];
    }

    /**
     * Petugas biasanya berjaga di lokasi yang sama: lokasi laporan terakhirnya jadi isian awal.
     */
    private static function lastLocation(): ?string
    {
        return auth()->check()
            ? SecurityChecksheet::query()->where('user_id', auth()->id())->latest('id')->value('location')
            : null;
    }
}
