<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InsuranceScopeResource\Pages;
use App\Models\InsuranceScope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class InsuranceScopeResource extends Resource
{
    protected static ?string $model = InsuranceScope::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Asuransi & Sewa';

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return __('Cakupan Asuransi');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Cakupan Asuransi');
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
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule->whereNull('deleted_at')),
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

                TextColumn::make('insurances_count')
                    ->label('Polis')
                    ->counts('insurances')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('updated_at')
                    ->label('Diubah')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('md'),
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
            'index' => Pages\ManageInsuranceScopes::route('/'),
        ];
    }
}
