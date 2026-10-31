<?php

namespace App\Filament\Resources;

use App\Access\ScopesVisibleRecords;
use App\Filament\Resources\AreaResource\Pages;
use App\Models\Area;
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

class AreaResource extends Resource
{
    use ScopesVisibleRecords;

    protected static ?string $model = Area::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-map';

    protected static string|\UnitEnum|null $navigationGroup = 'Organisasi';

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('Area');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Area');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255)
                    // Index unik database ikut menghitung area di Sampah, jadi validasi pun begitu.
                    ->unique(ignoreRecord: true),
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

                TextColumn::make('divisions_count')
                    ->label('Divisi')
                    ->counts('divisions')
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
            'index' => Pages\ManageAreas::route('/'),
        ];
    }
}
