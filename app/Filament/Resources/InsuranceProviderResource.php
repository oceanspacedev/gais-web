<?php

namespace App\Filament\Resources;

use App\Access\ScopesVisibleRecords;
use App\Filament\Resources\InsuranceProviderResource\Pages;
use App\Models\InsuranceProvider;
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
use Illuminate\Validation\Rules\Unique;

class InsuranceProviderResource extends Resource
{
    use ScopesVisibleRecords;

    protected static ?string $model = InsuranceProvider::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Asuransi & Sewa';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('Provider Asuransi');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Provider Asuransi');
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

                TextColumn::make('stockInsurances_count')
                    ->label('Polis')
                    ->counts('stockInsurances')
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
            'index' => Pages\ManageInsuranceProviders::route('/'),
        ];
    }
}
