<?php

namespace App\Filament\Resources;

use App\Access\ScopesVisibleRecords;
use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoryResource extends Resource
{
    use ScopesVisibleRecords;

    protected static ?string $model = Category::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Aset';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                TextInput::make('name')
                    ->translateLabel()
                    ->required()
                    ->maxLength(255),
                Select::make('parent_id')
                    ->translateLabel()
                    ->options(Category::whereNull('parent_id')->pluck('name', 'id'))
                    ->nullable()
                    ->searchable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->translateLabel()->sortable()->searchable(),
                TextColumn::make('created_at')->translateLabel()->dateTime()->sortable(),
                TextColumn::make('updated_at')->translateLabel()->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                RestoreAction::make(),
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('md'),
                DeleteAction::make(),
            ])
            ->bulkActions([
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
            'index' => Pages\ManageCategories::route('/'),
        ];
    }

    /**
     * Daftar hanya menampilkan kategori yang punya induk.
     */
    protected static function baseVisibleQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('parent_id');
    }

    public static function getModelLabel(): string
    {
        return __('Category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Categories');
    }
}
