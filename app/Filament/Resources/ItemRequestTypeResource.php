<?php

namespace App\Filament\Resources;

use App\Access\ScopesVisibleRecords;
use App\Filament\Resources\ItemRequestTypeResource\Pages;
use App\Models\ItemRequestType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tipe pengajuan (sumo: ASSET/NONASSET, ATK, NOTA) beserta aturan alurnya.
 */
class ItemRequestTypeResource extends Resource
{
    use ScopesVisibleRecords;

    protected static ?string $model = ItemRequestType::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengajuan Barang';

    protected static ?int $navigationSort = 6;

    public static function getModelLabel(): string
    {
        return __('Tipe Pengajuan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Tipe Pengajuan');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                TextInput::make('name')
                    ->label('Nama Tipe')
                    ->required()
                    ->maxLength(255),

                Select::make('pic_division_id')
                    ->label('Divisi PIC (executor)')
                    ->relationship('picDivision', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutTrashed())
                    ->searchable()
                    ->preload(),

                Toggle::make('requires_attachment')
                    ->label('Wajib lampiran persetujuan atasan')
                    ->helperText('Pemohon harus mengunggah file persetujuan saat membuat pengajuan.'),

                Toggle::make('requires_products')
                    ->label('Wajib daftar barang')
                    ->helperText('Pemohon memilih barang dan jumlahnya (mis. ATK).'),

                Toggle::make('requires_accounting_approval')
                    ->label('Lewat tahap Accounting (status PO)')
                    ->helperText('Accounting mengisi status dan nomor PO sebelum diproses executor.'),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Tipe')->searchable()->sortable()->weight('bold'),
                TextColumn::make('picDivision.name')->label('Divisi PIC')->placeholder('-'),
                IconColumn::make('requires_attachment')->label('Lampiran')->boolean(),
                IconColumn::make('requires_products')->label('Daftar Barang')->boolean(),
                IconColumn::make('requires_accounting_approval')->label('Accounting')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('requests_count')->label('Pengajuan')->counts('requests')->badge()->color('gray'),
            ])
            ->defaultSort('id')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                RestoreAction::make(),
                EditAction::make()->slideOver()->modalWidth('md'),
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
            'index' => Pages\ManageItemRequestTypes::route('/'),
        ];
    }
}
