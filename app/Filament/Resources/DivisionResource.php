<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DivisionResource\Pages;
use App\Models\Area;
use App\Models\Division;
use App\Models\ItemRequestType;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DivisionResource extends Resource
{
    protected static ?string $model = Division::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|\UnitEnum|null $navigationGroup = 'Organisasi';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Divisi')
                    ->unique(ignoreRecord: true)
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('area_id')
                    ->label('Area')
                    ->helperText('Kelompok divisi (HO, RETAIL, TOP, ...), dipakai cakupan pengajuan barang.')
                    ->options(fn (): array => Area::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),

                Forms\Components\Repeater::make('approvers')
                    ->label('Alur Persetujuan (Approval Flow)')
                    ->helperText('Tambahkan approver sesuai jabatan yang harus menyetujui. Seret untuk mengatur alur.')
                    ->relationship('approvers')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Approver')
                            ->relationship('user', 'name', function ($query, $get, $state) {
                                $selectedUsers = collect($get('../../approvers'))
                                    ->pluck('user_id')
                                    ->filter()
                                    ->all();

                                return $query
                                    ->with('jobTitle')
                                    ->when($selectedUsers, function ($q) use ($selectedUsers, $state) {
                                        $q->whereNotIn('id', array_diff($selectedUsers, [$state]));
                                    })
                                    ->orderBy('name');
                            })
                            ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->nameWithJobTitle())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),
                        Forms\Components\Hidden::make('level')
                            ->default(fn ($livewire, $get) => count($get('../../approvers') ?? []) + 1),
                    ])
                    ->columns(1)
                    ->defaultItems(1)
                    ->reorderable('level')
                    ->orderColumn('level')
                    ->itemLabel(function (array $state): ?string {
                        if (! isset($state['user_id'])) {
                            return 'Approver baru';
                        }

                        $user = User::with('jobTitle')->find($state['user_id']);

                        return $user?->nameWithJobTitle() ?? 'Approver';
                    }),

                Section::make('Cakupan Pengajuan Barang')
                    ->description('Pengajuan mana yang boleh dilihat dan diproses executor/manager di divisi ini. Kosongkan tipe atau area untuk "semua". Tanpa baris, anggota divisi hanya melihat pengajuannya sendiri.')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Repeater::make('itemRequestScopes')
                            ->hiddenLabel()
                            ->relationship('itemRequestScopes')
                            ->schema([
                                Forms\Components\Select::make('item_request_type_id')
                                    ->label('Tipe pengajuan')
                                    ->placeholder('Semua tipe')
                                    ->options(fn (): array => ItemRequestType::query()->orderBy('id')->pluck('name', 'id')->all()),
                                Forms\Components\Select::make('area_id')
                                    ->label('Area pemohon')
                                    ->placeholder('Semua area')
                                    ->options(fn (): array => Area::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Tambah cakupan'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Divisi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('area.name')
                    ->label('Area')
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Anggota')
                    ->counts('users')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('approvers')
                    ->label('Alur Persetujuan')
                    ->getStateUsing(function (Division $record) {
                        $record->loadMissing('approvers.user.jobTitle');

                        return $record->approvers
                            ->map(fn ($approver) => $approver->user?->nameWithJobTitle() ?? 'Unknown')
                            ->implode(' ➔ ');
                    }),
                Tables\Columns\TextColumn::make('item_request_scopes_count')
                    ->label('Cakupan Pengajuan')
                    ->counts('itemRequestScopes')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('area_id')
                    ->label('Area')
                    ->relationship('area', 'name')
                    ->preload(),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('lg'),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageDivisions::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
