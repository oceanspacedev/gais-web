<?php

namespace App\Filament\Resources\InsuranceResource\RelationManagers;

use App\Enums\ContractStatus;
use App\Exports\InsuranceUpdateExport;
use App\Filament\Actions\ContractUpdateExcelActions;
use App\Imports\InsuranceUpdateImport;
use App\Models\Insurance;
use App\Models\InsuranceUpdate;
use App\Support\ContractDue;
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
 * jatuh tempo dan status efektif polis. Baris baru berstatus BERJALAN seperti di
 * sumo; PEMBAHARUAN dipakai untuk menandai baris yang sudah digantikan.
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
                Select::make('status')->label('Status')->options(ContractStatus::class)->default(ContractStatus::Running->value)->required(),
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
        /** @var Insurance $insurance */
        $insurance = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['stockProvider', 'buildingProvider', 'user']))
            ->columns([
                TextColumn::make('policy_number')->label('Nomor Polis')->weight('bold'),
                TextColumn::make('join_date')->label('Mulai')->date('d/m/Y'),
                TextColumn::make('expired_date')->label('Berakhir')->date('d/m/Y')->weight('bold')
                    // Sumo: baris pembaharuan merah bila 14 hari atau kurang, kuning 15-30 hari.
                    ->color(fn (InsuranceUpdate $record): ?string => ContractDue::insuranceColor($record->expired_date)),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('stockProvider.name')->label('Asuransi Stok')->placeholder('-')->toggleable(),
                TextColumn::make('stock_worth')->label('Nilai Stok')->money('IDR', decimalPlaces: 0)->placeholder('-')->toggleable(),
                TextColumn::make('stock_premium')->label('Premi Stok')->money('IDR', decimalPlaces: 0)->placeholder('-'),
                TextColumn::make('actual_stock_worth')->label('Aktual Stok')->money('IDR', decimalPlaces: 0)->placeholder('-')->toggleable(),
                TextColumn::make('stock_difference')->label('Selisih')->state(fn (InsuranceUpdate $record): ?int => $record->stockDifference())->money('IDR', decimalPlaces: 0)->color('danger')->placeholder('-')->toggleable(),
                TextColumn::make('buildingProvider.name')->label('Asuransi Bangunan')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('building_worth')->label('Nilai Bangunan')->money('IDR', decimalPlaces: 0)->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('building_premium')->label('Premi Bangunan')->money('IDR', decimalPlaces: 0)->placeholder('-'),
                TextColumn::make('user.name')->label('Diinput Oleh')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')->label('Catatan')->limit(40)->placeholder('-'),
            ])
            ->defaultSort('expired_date', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Pembaharuan')
                    ->mutateDataUsing(fn (array $data): array => $data + ['user_id' => auth()->id()])
                    ->visible(fn (): bool => $this->canManage()),
                // Sumo: importUpdate/templateUpdate/exportUpdate per polis.
                ContractUpdateExcelActions::import(
                    name: 'importUpdates',
                    label: 'Impor Pembaharuan',
                    importClass: InsuranceUpdateImport::class,
                    owner: $insurance,
                    templateFileName: 'template-pembaharuan-asuransi.xlsx',
                    description: 'Unggah Excel berkolom template. Kolom no_polis_induk harus berisi nomor polis ini; baris dengan no_polis yang sudah ada diperbarui dan sel kosong dibiarkan seperti semula. Baris baru wajib punya tanggal mulai dan akhir; status kosong dianggap BERJALAN.',
                )->visible(fn (): bool => auth()->user()?->can('import', Insurance::class) ?? false),
                ContractUpdateExcelActions::export(
                    name: 'exportUpdates',
                    label: 'Ekspor Pembaharuan',
                    export: fn (): InsuranceUpdateExport => new InsuranceUpdateExport($insurance),
                )->visible(fn (): bool => auth()->user()?->can('export', Insurance::class) ?? false),
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
