<?php

namespace App\Filament\Resources\ItemRequestResource\RelationManagers;

use App\Enums\ItemRequestClientStatus;
use App\Models\ItemRequest;
use App\Models\ItemRequestDetail;
use App\Models\Product;
use App\Services\ItemRequestWorkflow;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Barang yang diajukan. Jumlah disetujui diisi manager; barang hanya bisa
 * ditambah/dihapus selama pengajuan masih menunggu.
 */
class DetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'details';

    protected static ?string $title = 'Barang yang Diajukan';

    /**
     * Relation manager di halaman detail bawaan Filament hanya-baca; di sini
     * riwayat memang diisi dari halaman detail, jadi hak akses mengikuti policy.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->columns(2)
            ->schema([
                Select::make('product_id')
                    ->label('Barang')
                    ->options(fn (): array => Product::query()->with('unitType')->orderBy('name')->get()->mapWithKeys(fn (Product $product): array => [$product->id => $product->label()])->all())
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('qty_request')
                    ->label('Jumlah Diminta')
                    ->numeric()
                    ->minValue(0.01)
                    ->required(),
                TextInput::make('qty_approved')
                    ->label('Jumlah Disetujui')
                    ->numeric()
                    ->minValue(0)
                    ->visible(fn (): bool => $this->canApproveQty()),
                TextInput::make('description')
                    ->label('Keterangan')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('product.unitType'))
            ->columns([
                TextColumn::make('product.name')->label('Barang')->weight('bold')->placeholder('(barang dihapus)'),
                TextColumn::make('product.unitType.name')->label('Satuan')->placeholder('-'),
                TextColumn::make('qty_request')->label('Diminta')->numeric(decimalPlaces: 0),
                TextColumn::make('qty_approved')->label('Disetujui')->numeric(decimalPlaces: 0)->placeholder('Belum'),
                TextColumn::make('product.price')->label('Harga')->money('IDR', decimalPlaces: 0)->placeholder('-'),
                TextColumn::make('line_total')->label('Subtotal')->state(fn (ItemRequestDetail $record): string => 'Rp '.number_format($record->lineTotal(), 0, ',', '.')),
                TextColumn::make('description')->label('Keterangan')->wrap()->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Barang')
                    ->visible(fn (): bool => $this->canEditItems()),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => $this->canEditItems() || $this->canApproveQty()),
                DeleteAction::make()
                    ->visible(fn (): bool => $this->canEditItems()),
            ]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof ItemRequest && (bool) $ownerRecord->type?->requires_products
            || ($ownerRecord instanceof ItemRequest && $ownerRecord->details()->exists());
    }

    private function request(): ItemRequest
    {
        /** @var ItemRequest $record */
        $record = $this->getOwnerRecord();

        return $record;
    }

    private function isFinal(): bool
    {
        return in_array($this->request()->client_status, [ItemRequestClientStatus::Accepted, ItemRequestClientStatus::Cancelled], true);
    }

    private function canEditItems(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $this->request()->client_status === ItemRequestClientStatus::Waiting
            && ($this->request()->isOwnedBy($user) || $user->can('update', $this->request()));
    }

    private function canApproveQty(): bool
    {
        $user = auth()->user();

        return ! $this->isFinal()
            && ($user?->can('approveManager', ItemRequest::class) ?? false)
            && app(ItemRequestWorkflow::class)->canApproveQuantity($user, $this->request());
    }
}
