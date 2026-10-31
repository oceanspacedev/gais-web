<?php

namespace App\Filament\Resources;

use App\Access\ScopesVisibleRecords;
use App\Filament\Resources\VendorResource\Pages;
use App\Models\Vendor;
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
use Filament\Tables\Table;

class VendorResource extends Resource
{
    use ScopesVisibleRecords;

    protected static ?string $model = Vendor::class;

    public static function getModelLabel(): string
    {
        return __('Vendor');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Vendors');
    }

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Aset';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('contact_person')
                    ->label('Contact Person (CP)')
                    ->maxLength(255)
                    ->placeholder('Nama / No. HP contact person'),
                TextInput::make('location')
                    ->label('Lokasi')
                    ->maxLength(255)
                    ->placeholder('Alamat / Kota vendor'),
                TextInput::make('last_price')
                    ->label('Harga Terakhir (Rp)')
                    ->numeric() // Mengatur agar input hanya angka
                    ->prefix('Rp ') // Tambahkan prefix 'Rp ' di depan input
                    ->required()
                    ->placeholder('Masukkan harga terakhir'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('contact_person')
                    ->label('Contact Person')
                    ->searchable(),
                Tables\Columns\TextColumn::make('location')
                    ->label('Lokasi')
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_price')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ManageVendors::route('/'),
        ];
    }
}
