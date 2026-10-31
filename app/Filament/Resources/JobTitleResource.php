<?php

namespace App\Filament\Resources;

use App\Access\ScopesVisibleRecords;
use App\Filament\Resources\JobTitleResource\Pages;
use App\Models\JobTitle;
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
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JobTitleResource extends Resource
{
    use ScopesVisibleRecords;

    protected static ?string $model = JobTitle::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|\UnitEnum|null $navigationGroup = 'Organisasi';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
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
            'index' => Pages\ManageJobTitles::route('/'),
        ];
    }

    public static function getModelLabel(): string
    {
        return __('Job Title');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Job Titles');
    }
}
