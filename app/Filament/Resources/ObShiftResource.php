<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ObShiftResource\Pages;
use App\Models\ObRoom;
use App\Models\ObShift;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ObShiftResource extends Resource
{
    protected static ?string $model = ObShift::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'OB / Kebersihan';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('Shift OB');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Shift OB');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                TextInput::make('name')
                    ->label('Nama Shift')
                    ->placeholder('Contoh: Shift Pagi')
                    ->required()
                    ->maxLength(255),

                Select::make('asset_location_id')
                    ->label('Lokasi')
                    ->relationship('location', 'name')
                    ->placeholder('Semua lokasi')
                    ->helperText('Shift dengan nama sama boleh ada di beberapa lokasi, masing-masing dengan daftar ruangannya sendiri.')
                    ->searchable()
                    ->preload()
                    ->live(),

                Grid::make(2)
                    ->schema([
                        TimePicker::make('start_time')
                            ->label('Jam Mulai')
                            ->seconds(false),
                        TimePicker::make('end_time')
                            ->label('Jam Selesai')
                            ->seconds(false),
                    ]),

                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),

                Repeater::make('shiftRooms')
                    ->label('Daftar Ruangan')
                    ->helperText('Ruangan ini otomatis muncul saat shift dipilih di OB Checksheet.')
                    ->relationship('shiftRooms')
                    ->orderColumn('sort_order')
                    ->simple(
                        Select::make('ob_room_id')
                            ->relationship(
                                'room',
                                'name',
                                modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                                    ->active()
                                    ->availableAt(filled($get('../../asset_location_id')) ? (int) $get('../../asset_location_id') : null)
                                    ->with('location')
                                    ->orderBy('name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (ObRoom $record): string => $record->label())
                            ->searchable()
                            ->preload()
                            ->distinct()
                            ->required()
                            ->createOptionForm(ObRoomResource::formFields())
                            ->createOptionModalHeading('Tambah Ruangan'),
                    )
                    ->defaultItems(1)
                    ->addActionLabel('Tambah ruangan'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Shift')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (ObShift $record): ?string => $record->timeRange()),

                TextColumn::make('location.name')
                    ->label('Lokasi')
                    ->placeholder('Semua lokasi')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('shift_rooms_count')
                    ->label('Jumlah Ruangan')
                    ->counts('shiftRooms')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('asset_location_id')
                    ->label('Lokasi')
                    ->relationship('location', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('lg'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageObShifts::route('/'),
        ];
    }
}
