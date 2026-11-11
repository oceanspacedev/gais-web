<?php

namespace App\Filament\Resources;

use App\Access\ScopesVisibleRecords;
use App\Filament\Resources\UnitTypeResource\Pages;
use App\Models\UnitType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UnitTypeResource extends Resource
{
    use ScopesVisibleRecords;

    protected static ?string $model = UnitType::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-scale';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengajuan Barang';

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return __('Tipe Unit Barang');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Tipe Unit Barang');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('products_count')
                    ->label('Barang')
                    ->counts('products')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('updated_at')
                    ->label('Diubah')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                RestoreAction::make(),
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('md'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUnitTypes::route('/'),
        ];
    }
}
