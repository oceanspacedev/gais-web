<?php

namespace App\Filament\Resources\VehicleInsuranceResource\RelationManagers;

use App\Enums\ContractStatus;
use App\Models\VehicleInsurance;
use App\Models\VehicleInsuranceUpdate;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pembaharuan polis asuransi kendaraan. Pembaharuan terakhir menentukan jatuh
 * tempo dan status efektif polis, sama seperti asuransi bangunan.
 */
class UpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'updates';

    protected static ?string $title = 'Riwayat Pembaharuan';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $form): Schema
    {
        /** @var VehicleInsurance $insurance */
        $insurance = $this->getOwnerRecord();

        return $form
            ->columns(['default' => 1, 'sm' => 2])
            ->schema([
                TextInput::make('policy_number')->label('Nomor Polis')->default($insurance->policy_number)->required()->maxLength(255),
                Select::make('status')->label('Status')->options(ContractStatus::class)->default(ContractStatus::Running->value)->required(),
                DatePicker::make('join_date')->label('Tanggal Mulai')->native(false)->required(),
                DatePicker::make('expired_date')->label('Tanggal Berakhir')->native(false)->required()->afterOrEqual('join_date'),
                Select::make('insurance_provider_id')->label('Provider Asuransi')->relationship('provider', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutTrashed())->default($insurance->insurance_provider_id)->preload()->searchable(),
                TextInput::make('extension_of_policy')->label('Perluasan / Jenis Pertanggungan')->default($insurance->extension_of_policy)->maxLength(255),
                TextInput::make('insured_worth')->label('Nilai Pertanggungan')->numeric()->prefix('Rp')->default($insurance->insured_worth),
                TextInput::make('premium')->label('Premi')->numeric()->prefix('Rp'),
                TextInput::make('payment_evidence')->label('Bukti Pembayaran')->maxLength(255)->columnSpanFull(),
                Textarea::make('notes')->label('Catatan')->rows(3)->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel('Pembaharuan')
            ->pluralModelLabel('Pembaharuan')
            ->modifyQueryUsing(fn ($query) => $query->with(['provider', 'user']))
            ->columns([
                TextColumn::make('policy_number')->label('Nomor Polis')->weight('bold'),
                TextColumn::make('join_date')->label('Mulai')->date('d/m/Y'),
                TextColumn::make('expired_date')->label('Berakhir')->date('d/m/Y')->weight('bold')
                    ->color(fn (VehicleInsuranceUpdate $record): ?string => $record->dueColor()),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('provider.name')->label('Provider')->placeholder('-'),
                TextColumn::make('extension_of_policy')->label('Jenis')->placeholder('-')->toggleable(),
                TextColumn::make('insured_worth')->label('Nilai Pertanggungan')->money('IDR', decimalPlaces: 0)->placeholder('-')->toggleable(),
                TextColumn::make('premium')->label('Premi')->money('IDR', decimalPlaces: 0)->placeholder('-'),
                TextColumn::make('user.name')->label('Diinput Oleh')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')->label('Catatan')->limit(40)->placeholder('-'),
            ])
            ->defaultSort('expired_date', 'desc')
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->slideOver()
                    ->modalWidth(Width::TwoExtraLarge)
                    ->label('Tambah Pembaharuan')
                    ->mutateDataUsing(fn (array $data): array => $data + ['user_id' => auth()->id()])
                    ->visible(fn (): bool => $this->canManage()),
            ])
            ->recordActions([
                EditAction::make()->slideOver()->modalWidth(Width::TwoExtraLarge)->visible(fn (): bool => $this->canManage()),
                DeleteAction::make()->visible(fn (): bool => $this->canManage()),
                RestoreAction::make()->hidden(fn (): bool => ! $this->canManage()),
                ForceDeleteAction::make()->hidden(fn (): bool => ! $this->canManage()),
            ]);
    }

    private function canManage(): bool
    {
        return auth()->user()?->can('update', $this->getOwnerRecord()) ?? false;
    }
}
