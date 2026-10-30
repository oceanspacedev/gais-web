<?php

namespace App\Filament\Resources\RentResource\RelationManagers;

use App\Enums\ContractStatus;
use App\Filament\Resources\RentResource;
use App\Models\Rent;
use App\Models\RentUpdate;
use App\Support\LegacyUpload;
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
 * Perpanjangan perjanjian sewa (sumo: rent_updates). Kode RENTUP#### dibuat
 * otomatis bila dikosongkan; tagihan tiap baris dihitung seperti daftar sumo.
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
                TextInput::make('rent_code')
                    ->label('Kode Perpanjangan')
                    ->placeholder('Otomatis, mis. '.RentUpdate::CODE_PREFIX.'0001')
                    ->helperText('Kosongkan untuk kode otomatis seperti di sumo.')
                    ->maxLength(255),
                Select::make('status')->label('Status')->options(ContractStatus::class)->default(ContractStatus::Running->value)->required(),
                TextInput::make('first_party')->label('Pihak Pertama')->default($rent->first_party)->required()->maxLength(255),
                TextInput::make('second_party')->label('Pihak Kedua')->default($rent->second_party)->required()->maxLength(255),
                DatePicker::make('join_date')->label('Tanggal Mulai')->native(false)->required(),
                DatePicker::make('expired_date')->label('Tanggal Berakhir')->native(false)->required()->afterOrEqual('join_date'),
                TextInput::make('rent_per_year')->label('Sewa per Tahun')->numeric()->prefix('Rp')->default($rent->rent_per_year)->required(),
                TextInput::make('month_before_reminder')->label('Pengingat (bulan sebelum)')->numeric()->minValue(0)->default($rent->month_before_reminder),
                TextInput::make('cvcs_fund')->label('Dana PKP (CV.CS)')->numeric()->prefix('Rp'),
                TextInput::make('online_fund')->label('Dana NON-PKP (Online)')->numeric()->prefix('Rp'),
                Select::make('deduction_evidence')->label('Bukti Potong')->options(RentResource::AVAILABILITY_OPTIONS),
                RentResource::fileUpload('deduction_evidence_file', 'File Bukti Potong'),
                Select::make('document')->label('Dokumen Perjanjian')->options(RentResource::AVAILABILITY_OPTIONS),
                RentResource::fileUpload('document_file', 'File Dokumen'),
                RentResource::fileUpload('payment_evidence_file', 'File Bukti Pembayaran'),
                Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('rent_code')->label('Kode')->weight('bold'),
                TextColumn::make('first_party')->label('Pihak Pertama')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('second_party')->label('Pihak Kedua')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('join_date')->label('Mulai')->date('d/m/Y'),
                TextColumn::make('expired_date')->label('Berakhir')->date('d/m/Y')->weight('bold')
                    // Sumo: kuning 30 hari sebelum tanggal reminder, merah sejak tanggal reminder.
                    ->color(fn (RentUpdate $record): ?string => $record->dueColor()),
                TextColumn::make('reminder')->label('Reminder')
                    ->state(fn (RentUpdate $record): string => ((int) $record->month_before_reminder).' bulan')
                    ->description(fn (RentUpdate $record): ?string => $record->reminderDate()?->format('d/m/Y')),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('rent_per_year')->label('Sewa / Tahun')->money('IDR', decimalPlaces: 0)->placeholder('-'),
                TextColumn::make('billing_total')->label('Total Tagihan')->state(fn (RentUpdate $record): int => $record->billingSummary()['total'])->money('IDR', decimalPlaces: 0)->toggleable(),
                TextColumn::make('cvcs_fund')->label('Dana PKP')->money('IDR', decimalPlaces: 0)->placeholder('-'),
                TextColumn::make('online_fund')->label('Dana NON-PKP')->money('IDR', decimalPlaces: 0)->placeholder('-'),
                TextColumn::make('billing_remaining')->label('Sisa Tagihan')->state(fn (RentUpdate $record): int => $record->billingSummary()['remaining'])->money('IDR', decimalPlaces: 0)->toggleable(),
                TextColumn::make('billing_status')->label('Status Tagihan')
                    ->state(fn (RentUpdate $record): string => $record->billingSummary()['status'])
                    ->badge()
                    ->color(fn (string $state): string => RentUpdate::billingColor($state)),
                TextColumn::make('deduction_evidence')->label('Bukti Potong')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('document')->label('Berkas')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_evidence_file')->label('Bukti Bayar')
                    ->state(fn (RentUpdate $record): string => filled($record->payment_evidence_file) ? 'Lihat Dokumen' : 'Tidak ada file')
                    ->url(fn (RentUpdate $record): ?string => LegacyUpload::url(LegacyUpload::RENT_DIR, $record->payment_evidence_file), shouldOpenInNewTab: true)
                    ->color(fn (RentUpdate $record): string => filled($record->payment_evidence_file) ? 'primary' : 'gray'),
                TextColumn::make('user.name')->label('Diinput Oleh')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')->label('Catatan')->limit(30)->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
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
