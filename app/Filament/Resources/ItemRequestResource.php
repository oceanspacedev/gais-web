<?php

namespace App\Filament\Resources;

use App\Enums\ItemRequestApprovalType;
use App\Enums\ItemRequestClientStatus;
use App\Enums\ItemRequestExecutorDecision;
use App\Exceptions\ItemRequestException;
use App\Filament\Resources\ItemRequestResource\Pages;
use App\Filament\Resources\ItemRequestResource\RelationManagers\DetailsRelationManager;
use App\Models\ItemRequest;
use App\Models\ItemRequestType;
use App\Models\Product;
use App\Models\User;
use App\Services\ItemRequestWorkflow;
use App\Support\LegacyUpload;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/**
 * Pengajuan barang (sumo: menu PENGAJUAN). Alur per tahap ada di
 * ItemRequestWorkflow; siapa melihat apa ada di ItemRequestVisibility.
 */
class ItemRequestResource extends Resource
{
    protected static ?string $model = ItemRequest::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengajuan Barang';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'request_code';

    public static function getModelLabel(): string
    {
        return __('Pengajuan Barang');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Pengajuan Barang');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('client_status', ItemRequestClientStatus::Waiting->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);

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
                ->whereNull('item_requests.business_entity_id')
                ->orWhereIn('item_requests.business_entity_id', $viewer->accessibleBusinessEntityIds()));
        }

        return $query;
    }

    public static function form(Schema $form): Schema
    {
        $viewer = auth()->user();
        $canPickApplicant = $viewer?->can('changeApplicant', ItemRequest::class) ?? false;

        return $form
            ->columns(1)
            ->schema([
                Section::make('Pengajuan')
                    ->schema([
                        Select::make('user_id')
                            ->label('Pemohon')
                            ->relationship('user', 'name', fn (Builder $query): Builder => $query->orderBy('name'))
                            ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->division ? "{$record->name} ({$record->division->name})" : $record->name)
                            ->default($viewer?->id)
                            ->searchable()
                            ->preload(false)
                            ->required()
                            ->live()
                            ->visible($canPickApplicant),

                        Select::make('item_request_type_id')
                            ->label('Tipe Pengajuan')
                            ->options(fn (Get $get, string $operation): array => static::typeOptions($get('user_id'), $operation))
                            ->helperText('Tipe yang kuota pengajuan terbukanya sudah penuh tidak ditampilkan.')
                            ->required()
                            ->live()
                            ->disabled(fn (string $operation): bool => $operation === 'edit'),

                        FileUpload::make('request_file')
                            ->label('File Persetujuan Atasan')
                            ->helperText('Foto atau PDF persetujuan (JPG, PNG, PDF, maks. 5 MB).')
                            ->directory(LegacyUpload::REQUEST_DIR)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                            ->maxSize(5120)
                            ->openable()
                            ->required(fn (Get $get): bool => static::typeRequires($get('item_request_type_id'), 'requires_attachment'))
                            ->visible(fn (Get $get): bool => static::typeRequires($get('item_request_type_id'), 'requires_attachment')),

                        FileUpload::make('request_file_2')
                            ->label('File Tambahan (opsional)')
                            ->directory(LegacyUpload::REQUEST_DIR)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                            ->maxSize(5120)
                            ->openable()
                            ->visible(fn (Get $get): bool => static::typeRequires($get('item_request_type_id'), 'requires_attachment')),

                        Repeater::make('items')
                            ->label('Barang yang diajukan')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Barang')
                                    ->options(fn (): array => Product::query()->with('unitType')->orderBy('name')->get()->mapWithKeys(fn (Product $product): array => [$product->id => $product->label()])->all())
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(2),
                                TextInput::make('qty_request')
                                    ->label('Jumlah')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->required(),
                                TextInput::make('description')
                                    ->label('Keterangan')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                            ])
                            ->columns(5)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Tambah barang')
                            ->required(fn (Get $get): bool => static::typeRequires($get('item_request_type_id'), 'requires_products'))
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && static::typeRequires($get('item_request_type_id'), 'requires_products')),

                        Textarea::make('notes')
                            ->label('Catatan Executor')
                            ->rows(3)
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                    ]),
            ]);
    }

    public static function infolist(Schema $infolist): Schema
    {
        return $infolist
            ->columns(1)
            ->schema([
                Section::make('Ringkasan')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('request_code')->label('Kode')->badge()->color('primary'),
                            TextEntry::make('type.name')->label('Tipe'),
                            TextEntry::make('client_status')->label('Status Akhir')->badge(),
                            TextEntry::make('user.name')->label('Pemohon')->placeholder('-'),
                            TextEntry::make('user.division.name')->label('Divisi')->placeholder('-'),
                            TextEntry::make('requested_at')->label('Tanggal')->dateTime('d/m/Y H:i'),
                            IconEntry::make('po_status')->label('Status PO')->boolean()->visible(fn (ItemRequest $record): bool => (bool) $record->type?->requires_accounting_approval),
                            TextEntry::make('po_number')->label('Nomor PO')->placeholder('-')->visible(fn (ItemRequest $record): bool => (bool) $record->type?->requires_accounting_approval),
                            TextEntry::make('grand_total')->label('Total')->state(fn (ItemRequest $record): string => 'Rp '.number_format($record->grandTotal(), 0, ',', '.')),
                            TextEntry::make('request_file')->label('File Persetujuan')
                                ->state(fn (ItemRequest $record): string => LegacyUpload::fileName($record->request_file) ?? '-')
                                ->url(fn (ItemRequest $record): ?string => LegacyUpload::url(LegacyUpload::REQUEST_DIR, $record->request_file), true)
                                ->icon(fn (ItemRequest $record): ?string => filled($record->request_file) ? 'heroicon-o-paper-clip' : null),
                            TextEntry::make('request_file_2')->label('File Tambahan')
                                ->state(fn (ItemRequest $record): string => LegacyUpload::fileName($record->request_file_2) ?? '-')
                                ->url(fn (ItemRequest $record): ?string => LegacyUpload::url(LegacyUpload::REQUEST_DIR, $record->request_file_2), true)
                                ->icon(fn (ItemRequest $record): ?string => filled($record->request_file_2) ? 'heroicon-o-paper-clip' : null),
                            TextEntry::make('closedBy.name')->label('Ditutup Oleh')->placeholder('-'),
                        ]),
                        TextEntry::make('notes')->label('Catatan Executor')->placeholder('-')->columnSpanFull(),
                        TextEntry::make('user_notes')->label('Catatan Pemohon')->placeholder('-')->columnSpanFull(),
                        TextEntry::make('audit_notes')->label('Catatan Audit')->placeholder('-')->columnSpanFull()
                            ->visible(fn (): bool => auth()->user()?->can('audit', ItemRequest::class) ?? false),
                    ]),

                Section::make('Riwayat Approval')
                    ->schema([
                        RepeatableEntry::make('approvals')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('approval_type')->label('Tahap')->badge(),
                                TextEntry::make('approver.name')->label('Oleh')->placeholder('Belum'),
                                TextEntry::make('approved_at')->label('Waktu')->dateTime('d/m/Y H:i')->placeholder('-'),
                            ])
                            ->columns(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $workflow = app(ItemRequestWorkflow::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user.division', 'type', 'approvals', 'closedBy']))
            ->columns([
                TextColumn::make('request_code')->label('Kode')->searchable()->sortable()->badge()->color('primary'),
                TextColumn::make('user.name')->label('Pemohon')->searchable()->sortable()->description(fn (ItemRequest $record): ?string => $record->user?->division?->name),
                TextColumn::make('type.name')->label('Tipe')->sortable(),
                TextColumn::make('requested_at')->label('Tanggal')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('details_count')->label('Item')->counts('details')->badge()->color('gray'),
                IconColumn::make('po_status')->label('PO')->boolean()->toggleable(),
                TextColumn::make('approval_progress')->label('Tahap')
                    ->state(fn (ItemRequest $record): string => static::approvalSummary($record))
                    ->wrap(),
                TextColumn::make('client_status')->label('Status Akhir')->badge()->sortable(),
                TextColumn::make('closedBy.name')->label('Ditutup Oleh')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                SelectFilter::make('item_request_type_id')->label('Tipe')->relationship('type', 'name')->preload(),
                SelectFilter::make('client_status')->label('Status Akhir')->options(ItemRequestClientStatus::options()),
                Filter::make('awaiting_executor')
                    ->label('Belum diproses executor')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $workflow->scopeAwaitingExecutor($query)),
                Filter::make('requested_at')
                    ->schema([
                        DatePicker::make('from')->label('Dari tanggal')->native(false),
                        DatePicker::make('until')->label('Sampai tanggal')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('requested_at', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $q, string $until) => $q->whereDate('requested_at', '<=', $until))),
                SelectFilter::make('user_id')
                    ->label('Pemohon')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->visible(fn (): bool => auth()->user()?->can('viewAll', ItemRequest::class) ?? false),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    ...static::workflowActions(),
                    Action::make('printQr')
                        ->label('Cetak QR')
                        ->icon('heroicon-o-qr-code')
                        ->color('gray')
                        ->url(fn (ItemRequest $record): string => route('item-requests.qr-labels', $record), shouldOpenInNewTab: true)
                        // details_count dari kolom Item; query hanya bila record dimuat tanpa hitungan itu.
                        ->visible(fn (ItemRequest $record): bool => isset($record->details_count) ? $record->details_count > 0 : $record->details()->exists()),
                    EditAction::make()->visible(fn (ItemRequest $record): bool => (auth()->user()?->can('update', $record) ?? false) && (auth()->user()?->can('changeApplicant', ItemRequest::class) ?? false)),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->label('Ekspor Excel')
                        ->exports([
                            ExcelExport::make('pengajuan')->fromTable()->withFilename('pengajuan-barang-'.now()->format('Ymd-His')),
                        ])
                        ->visible(fn (): bool => auth()->user()?->can('export', ItemRequest::class) ?? false),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Aksi per tahap; dipakai tabel dan halaman detail.
     *
     * @return list<Action>
     */
    public static function workflowActions(): array
    {
        $workflow = app(ItemRequestWorkflow::class);
        $user = fn (): ?User => auth()->user();

        return [
            Action::make('statusPo')
                ->label('Status PO (Accounting)')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->modalHeading('Status PO')
                ->modalWidth('md')
                ->visible(fn (ItemRequest $record) => ($user()?->can('approveAccounting', ItemRequest::class) ?? false)
                    && (bool) $record->type?->requires_accounting_approval
                    && ! in_array($record->client_status, [ItemRequestClientStatus::Accepted, ItemRequestClientStatus::Cancelled], true))
                ->form(fn (ItemRequest $record): array => [
                    Toggle::make('po_status')->label('PO sudah diterbitkan')->default($record->po_status),
                    TextInput::make('po_number')->label('Nomor PO')->default($record->po_number)->maxLength(255),
                ])
                ->action(fn (ItemRequest $record, array $data) => static::runWorkflow(
                    fn () => $workflow->approveAccounting($record, $user(), (bool) ($data['po_status'] ?? false), $data['po_number'] ?? null),
                    'Status PO disimpan.',
                )),

            Action::make('approveManager')
                ->label('Setujui Jumlah (Manager)')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Setujui pengajuan ini?')
                ->modalDescription('Jumlah barang yang belum diisi akan disetujui sesuai jumlah yang diminta. Ubah jumlah per barang lebih dulu bila perlu.')
                ->visible(fn (ItemRequest $record) => ($user()?->can('approveManager', ItemRequest::class) ?? false)
                    && ! $record->isApproved(ItemRequestApprovalType::Manager)
                    && ! in_array($record->client_status, [ItemRequestClientStatus::Accepted, ItemRequestClientStatus::Cancelled], true))
                ->action(fn (ItemRequest $record) => static::runWorkflow(fn () => $workflow->approveManager($record, $user()), 'Pengajuan telah disetujui.')),

            Action::make('process')
                ->label('Proses (Executor)')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('info')
                ->modalHeading('Proses pengajuan')
                ->modalWidth('md')
                ->visible(fn (ItemRequest $record) => ($user()?->can('process', ItemRequest::class) ?? false)
                    && ! in_array($record->client_status, [ItemRequestClientStatus::Accepted], true))
                ->form(fn (ItemRequest $record): array => [
                    Radio::make('decision')->label('Keputusan')->options(ItemRequestExecutorDecision::class)->default(static::defaultDecision($record))->required(),
                    Textarea::make('notes')->label('Catatan')->default($record->notes)->rows(3),
                ])
                ->action(fn (ItemRequest $record, array $data) => static::runWorkflow(
                    fn () => $workflow->process($record, $user(), ($data['decision'] instanceof ItemRequestExecutorDecision ? $data['decision'] : ItemRequestExecutorDecision::from($data['decision'])), $data['notes'] ?? null),
                    'Status pengajuan diperbarui.',
                )),

            Action::make('confirm')
                ->label('Konfirmasi Penerimaan')
                ->icon('heroicon-o-hand-thumb-up')
                ->color('success')
                ->modalHeading('Konfirmasi penerimaan barang')
                ->modalWidth('md')
                ->visible(fn (ItemRequest $record) => $record->isOwnedBy($user())
                    && $record->isApproved(ItemRequestApprovalType::Executor)
                    && ! $record->isApproved(ItemRequestApprovalType::EndUser)
                    && in_array($record->client_status, [ItemRequestClientStatus::Closed, ItemRequestClientStatus::Waiting], true))
                ->form(fn (ItemRequest $record): array => [
                    Radio::make('accepted')->label('Status')->options(['1' => 'Diterima', '0' => 'Masih menunggu'])->default('1')->required(),
                    Textarea::make('user_notes')->label('Catatan')->default($record->user_notes)->rows(3),
                ])
                ->action(fn (ItemRequest $record, array $data) => static::runWorkflow(
                    fn () => $workflow->confirm($record, $user(), (string) ($data['accepted'] ?? '0') === '1', $data['user_notes'] ?? null),
                    'Konfirmasi disimpan.',
                )),

            Action::make('cancel')
                ->label('Batalkan')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Batalkan pengajuan ini?')
                ->visible(fn (ItemRequest $record) => ($record->isOwnedBy($user()) || ($user()?->can('delete', $record) ?? false))
                    && ! in_array($record->client_status, [ItemRequestClientStatus::Accepted, ItemRequestClientStatus::Cancelled], true))
                ->form([
                    Textarea::make('reason')->label('Alasan (opsional)')->rows(2),
                ])
                ->action(fn (ItemRequest $record, array $data) => static::runWorkflow(fn () => $workflow->cancel($record, $user(), $data['reason'] ?? null), 'Pengajuan dibatalkan.')),

            Action::make('audit')
                ->label('Catatan Audit')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('gray')
                ->modalHeading('Catatan Audit')
                ->modalWidth('md')
                ->visible(fn () => $user()?->can('audit', ItemRequest::class) ?? false)
                ->form(fn (ItemRequest $record): array => [
                    Textarea::make('audit_notes')->label('Catatan')->default($record->audit_notes)->rows(4),
                ])
                ->action(fn (ItemRequest $record, array $data) => static::runWorkflow(fn () => $workflow->updateAuditNotes($record, $data['audit_notes'] ?? null), 'Catatan audit disimpan.')),

            Action::make('changeApplicant')
                ->label('Ubah Pemohon')
                ->icon('heroicon-o-user')
                ->color('gray')
                ->modalHeading('Ubah pemohon')
                ->modalWidth('md')
                ->visible(fn (ItemRequest $record) => ($user()?->can('changeApplicant', ItemRequest::class) ?? false)
                    && $record->client_status !== ItemRequestClientStatus::Cancelled)
                ->form([
                    Select::make('new_user_id')
                        ->label('Pemohon baru')
                        ->options(fn (): array => User::query()->whereNotNull('username')->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required(),
                ])
                ->action(fn (ItemRequest $record, array $data) => static::runWorkflow(
                    fn () => $workflow->changeApplicant($record, $user(), User::query()->findOrFail($data['new_user_id'])),
                    'Pemohon diubah dan dicatat di log.',
                )),
        ];
    }

    public static function getRelations(): array
    {
        return [
            DetailsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListItemRequests::route('/'),
            'create' => Pages\CreateItemRequest::route('/create'),
            'view' => Pages\ViewItemRequest::route('/{record}'),
            'edit' => Pages\EditItemRequest::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function typeOptions(mixed $requesterId, string $operation): array
    {
        if ($operation !== 'create') {
            return ItemRequestType::query()->orderBy('id')->pluck('name', 'id')->all();
        }

        $requester = filled($requesterId) ? User::query()->find($requesterId) : auth()->user();

        if (! $requester instanceof User) {
            return [];
        }

        return app(ItemRequestWorkflow::class)->availableTypes($requester)->pluck('name', 'id')->all();
    }

    private static function typeRequires(mixed $typeId, string $flag): bool
    {
        if (blank($typeId)) {
            return false;
        }

        return (bool) ItemRequestType::query()->whereKey($typeId)->value($flag);
    }

    private static function approvalSummary(ItemRequest $record): string
    {
        return collect(ItemRequestApprovalType::cases())
            ->filter(fn (ItemRequestApprovalType $type): bool => $type !== ItemRequestApprovalType::Accounting || (bool) $record->type?->requires_accounting_approval)
            ->map(fn (ItemRequestApprovalType $type): string => ($record->isApproved($type) ? '✓ ' : '○ ').$type->label())
            ->implode('  ');
    }

    private static function defaultDecision(ItemRequest $record): string
    {
        return match ($record->client_status) {
            ItemRequestClientStatus::Processed => ItemRequestExecutorDecision::Processed->value,
            ItemRequestClientStatus::Closed => ItemRequestExecutorDecision::Closed->value,
            ItemRequestClientStatus::Cancelled => ItemRequestExecutorDecision::Cancelled->value,
            default => ItemRequestExecutorDecision::Pending->value,
        };
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
