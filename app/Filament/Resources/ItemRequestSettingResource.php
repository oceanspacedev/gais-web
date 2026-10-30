<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemRequestSettingResource\Pages;
use App\Models\ItemRequestSetting;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Periode cutoff pengajuan ATK bulanan (sumo: Pengaturan > Pengajuan).
 */
class ItemRequestSettingResource extends Resource
{
    protected static ?string $model = ItemRequestSetting::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengajuan Barang';

    protected static ?int $navigationSort = 7;

    public static function getModelLabel(): string
    {
        return __('Periode Pengajuan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Periode Pengajuan');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                DatePicker::make('request_month')
                    ->label('Bulan Pengajuan')
                    ->helperText('Tanggal berapa pun yang dipilih disimpan sebagai tanggal 1 bulan itu (sumo: pilihan bulan).')
                    ->displayFormat('F Y')
                    ->required()
                    ->native(false)
                    ->dehydrateStateUsing(fn (mixed $state): ?string => filled($state) ? Carbon::parse($state)->startOfMonth()->toDateString() : null),
                DatePicker::make('open_date')->label('Tanggal Buka')->displayFormat('d/m/Y')->required()->native(false),
                DatePicker::make('closed_date')->label('Tanggal Tutup')->displayFormat('d/m/Y')->required()->native(false)->afterOrEqual('open_date'),
                TextInput::make('request_detail')
                    ->label('Keterangan')
                    ->placeholder('Contoh: Pengajuan ATK Bulanan (di luar tanggal tidak diproses)')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_month')->label('Bulan')->date('F Y')->sortable(),
                TextColumn::make('open_date')->label('Buka')->date('d/m/Y'),
                TextColumn::make('closed_date')->label('Tutup')->date('d/m/Y'),
                TextColumn::make('request_detail')->label('Keterangan')->wrap(),
            ])
            ->defaultSort('request_month', 'desc')
            ->recordActions([
                EditAction::make()->slideOver()->modalWidth('md'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageItemRequestSettings::route('/'),
        ];
    }
}
