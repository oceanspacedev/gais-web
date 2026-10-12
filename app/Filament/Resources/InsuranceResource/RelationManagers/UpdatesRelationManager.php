<?php

namespace App\Filament\Resources\InsuranceResource\RelationManagers;

use App\Enums\ContractStatus;
use App\Models\Insurance;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Pembaharuan polis (sumo: insurance_updates). Pembaharuan terakhir menentukan
 * jatuh tempo dan status efektif polis.
 */
class UpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'updates';

    protected static ?string $title = 'Riwayat Pembaharuan';

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
        /** @var Insurance $insurance */
        $insurance = $this->getOwnerRecord();

        return $form
            ->columns(2)
            ->schema([
                TextInput::make('policy_number')->label('Nomor Polis')->default($insurance->policy_number)->required()->maxLength(255),
                Select::make('status')->label('Status')->options(ContractStatus::class)->default(ContractStatus::Renewed->value)->required(),
                DatePicker::make('join_date')->label('Tanggal Mulai')->native(false)->required(),
                DatePicker::make('expired_date')->label('Tanggal Berakhir')->native(false)->required()->afterOrEqual('join_date'),
                Select::make('stock_provider_id')->label('Provider Stok')->relationship('stockProvider', 'name')->default($insurance->stock_provider_id)->preload()->searchable(),
                Select::make('building_provider_id')->label('Provider Bangunan')->relationship('buildingProvider', 'name')->default($insurance->building_provider_id)->preload()->searchable(),
                TextInput::make('stock_worth')->label('Nilai Stok')->numeric()->prefix('Rp')->default($insurance->stock_worth),
                TextInput::make('building_worth')->label('Nilai Bangunan')->numeric()->prefix('Rp')->default($insurance->building_worth),
                TextInput::make('actual_stock_worth')->label('Nilai Stok Aktual')->numeric()->prefix('Rp'),
                TextInput::make('extension_of_policy')->label('Perluasan Polis')->maxLength(255),
                TextInput::make('stock_premium')->label('Premi Stok')->numeric()->prefix('Rp'),
                TextInput::make('building_premium')->label('Premi Bangunan')->numeric()->prefix('Rp'),
                TextInput::make('payment_evidence')->label('Bukti Pembayaran')->maxLength(255),
                Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('policy_number')->label('Nomor Polis')->weight('bold'),
                TextColumn::make('join_date')->label('Mulai')->date('d/m/Y'),
                TextColumn::make('expired_date')->label('Berakhir')->date('d/m/Y'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('stock_premium')->label('Premi Stok')->money('IDR', decimalPlaces: 0)->placeholder('-'),
                TextColumn::make('building_premium')->label('Premi Bangunan')->money('IDR', decimalPlaces: 0)->placeholder('-'),
                TextColumn::make('user.name')->label('Diinput Oleh')->placeholder('-'),
                TextColumn::make('notes')->label('Catatan')->limit(40)->placeholder('-'),
            ])
            ->defaultSort('expired_date', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Pembaharuan')
                    ->mutateDataUsing(fn (array $data): array => $data + ['user_id' => auth()->id()])
                    ->visible(fn (): bool => $this->canManage()),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (): bool => $this->canManage()),
                DeleteAction::make()->visible(fn (): bool => $this->canManage()),
            ]);
    }

    private function canManage(): bool
    {
        return auth()->user()?->can('update', $this->getOwnerRecord()) ?? false;
    }
}
