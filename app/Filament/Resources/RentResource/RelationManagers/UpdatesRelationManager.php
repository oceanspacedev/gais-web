<?php

namespace App\Filament\Resources\RentResource\RelationManagers;

use App\Enums\ContractStatus;
use App\Filament\Resources\RentResource;
use App\Models\Rent;
use App\Support\LegacyUpload;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Perpanjangan perjanjian sewa (sumo: rent_updates).
 */
class UpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'updates';

    protected static ?string $title = 'Riwayat Perpanjangan';

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
        /** @var Rent $rent */
        $rent = $this->getOwnerRecord();

        return $form
            ->columns(2)
            ->schema([
                TextInput::make('rent_code')->label('Kode Perpanjangan')->default('RENTUP'.($rent->updates()->count() + 1).'-'.$rent->rent_code)->required()->maxLength(255),
                Select::make('status')->label('Status')->options(ContractStatus::class)->default(ContractStatus::Running->value)->required(),
                TextInput::make('first_party')->label('Pihak Pertama')->default($rent->first_party)->required()->maxLength(255),
                TextInput::make('second_party')->label('Pihak Kedua')->default($rent->second_party)->required()->maxLength(255),
                DatePicker::make('join_date')->label('Tanggal Mulai')->native(false)->required(),
                DatePicker::make('expired_date')->label('Tanggal Berakhir')->native(false)->required()->afterOrEqual('join_date'),
                TextInput::make('rent_per_year')->label('Sewa per Tahun')->numeric()->prefix('Rp')->default($rent->rent_per_year),
                TextInput::make('month_before_reminder')->label('Pengingat (bulan sebelum)')->maxLength(255),
                TextInput::make('cvcs_fund')->label('Dana CVCS')->numeric()->prefix('Rp'),
                TextInput::make('online_fund')->label('Dana Online')->numeric()->prefix('Rp'),
                Select::make('deduction_evidence')->label('Bukti Potong')->options(RentResource::AVAILABILITY_OPTIONS),
                FileUpload::make('deduction_evidence_file')->label('File Bukti Potong')->directory(LegacyUpload::RENT_DIR)->maxSize(5120)->openable()
                    ->formatStateUsing(fn (?string $state): ?string => LegacyUpload::path(LegacyUpload::RENT_DIR, $state)),
                Select::make('document')->label('Dokumen Perjanjian')->options(RentResource::AVAILABILITY_OPTIONS),
                FileUpload::make('document_file')->label('File Dokumen')->directory(LegacyUpload::RENT_DIR)->maxSize(5120)->openable()
                    ->formatStateUsing(fn (?string $state): ?string => LegacyUpload::path(LegacyUpload::RENT_DIR, $state)),
                FileUpload::make('payment_evidence_file')->label('File Bukti Pembayaran')->directory(LegacyUpload::RENT_DIR)->maxSize(5120)->openable()
                    ->formatStateUsing(fn (?string $state): ?string => LegacyUpload::path(LegacyUpload::RENT_DIR, $state)),
                Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rent_code')->label('Kode')->weight('bold'),
                TextColumn::make('join_date')->label('Mulai')->date('d/m/Y'),
                TextColumn::make('expired_date')->label('Berakhir')->date('d/m/Y'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('rent_per_year')->label('Sewa / Tahun')->money('IDR', decimalPlaces: 0)->placeholder('-'),
                TextColumn::make('cvcs_fund')->label('Dana CVCS')->money('IDR', decimalPlaces: 0)->placeholder('-'),
                TextColumn::make('online_fund')->label('Dana Online')->money('IDR', decimalPlaces: 0)->placeholder('-'),
                TextColumn::make('user.name')->label('Diinput Oleh')->placeholder('-'),
            ])
            ->defaultSort('expired_date', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Perpanjangan')
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
