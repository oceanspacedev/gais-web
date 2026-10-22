<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Support\LegacyUpload;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/**
 * Barang yang bisa diajukan (sumo: Data > Barang).
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengajuan Barang';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Barang');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Barang');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(2)
            ->schema([
                TextInput::make('name')
                    ->label('Nama Barang')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule->whereNull('deleted_at'))
                    ->columnSpanFull(),
                TextInput::make('item_code')
                    ->label('Kode Barang')
                    ->maxLength(255),
                TextInput::make('price')
                    ->label('Harga')
                    ->numeric()
                    ->prefix('Rp')
                    ->minValue(0),
                Select::make('product_category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('unit_type_id')
                    ->label('Satuan')
                    ->relationship('unitType', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('stock')
                    ->label('Stok')
                    ->numeric()
                    ->minValue(0),
                FileUpload::make('image')
                    ->label('Gambar')
                    ->image()
                    ->directory(LegacyUpload::PRODUCT_DIR)
                    ->maxSize(2048)
                    ->imagePreviewHeight('150')
                    ->formatStateUsing(fn (?string $state): ?string => LegacyUpload::path(LegacyUpload::PRODUCT_DIR, $state)),
                Textarea::make('description')
                    ->label('Keterangan')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->getStateUsing(fn (Product $record): ?string => LegacyUpload::path(LegacyUpload::PRODUCT_DIR, $record->image))
                    ->disk('public')
                    ->square()
                    ->toggleable(),
                TextColumn::make('item_code')->label('Kode')->searchable()->toggleable(),
                TextColumn::make('name')->label('Nama Barang')->searchable()->sortable()->weight('bold'),
                TextColumn::make('category.name')->label('Kategori')->sortable()->placeholder('-'),
                TextColumn::make('unitType.name')->label('Satuan')->placeholder('-'),
                TextColumn::make('price')->label('Harga')->money('IDR', decimalPlaces: 0)->sortable()->placeholder('-'),
                TextColumn::make('stock')->label('Stok')->numeric(decimalPlaces: 0)->placeholder('-')->toggleable(),
                TextColumn::make('request_details_count')->label('Diajukan')->counts('requestDetails')->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('product_category_id')->label('Kategori')->relationship('category', 'name')->preload(),
                SelectFilter::make('unit_type_id')->label('Satuan')->relationship('unitType', 'name')->preload(),
            ])
            ->recordActions([
                Action::make('printQr')
                    ->label('QR')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->url(fn (Product $record): string => route('products.qr-label', $record), shouldOpenInNewTab: true),
                EditAction::make()->slideOver()->modalWidth('lg'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->label('Ekspor Excel')
                        ->exports([
                            ExcelExport::make('barang')->fromTable()->withFilename('barang-'.now()->format('Ymd-His')),
                        ])
                        ->visible(fn (): bool => auth()->user()?->can('export', Product::class) ?? false),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageProducts::route('/'),
        ];
    }
}
