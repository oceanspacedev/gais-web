<?php

namespace App\Filament\Resources\ItemRequestResource\Pages;

use App\Enums\ItemRequestClientStatus;
use App\Enums\ItemRequestRecapStatus;
use App\Filament\Concerns\MemoizesTableQuery;
use App\Filament\Resources\ItemRequestResource;
use App\Models\Area;
use App\Models\Division;
use App\Models\ItemRequest;
use App\Models\ItemRequestType;
use App\Models\User;
use App\Services\ItemRequestMasterQrExport;
use App\Services\ItemRequestRecapExport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListItemRequests extends ListRecords
{
    use MemoizesTableQuery;

    protected static string $resource = ItemRequestResource::class;

    public function mount(): void
    {
        parent::mount();

        // Karyawan tanpa divisi langsung diminta memilih divisinya.
        if ($this->needsDivision()) {
            $this->defaultAction = 'chooseDivision';
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->chooseDivisionAction(),
            $this->exportRecapAction(),
            $this->exportMasterQrAction(),
            CreateAction::make()->label('Buat Pengajuan')->visible(fn (): bool => ! $this->needsDivision()),
        ];
    }

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('Semua')];

        foreach (ItemRequestClientStatus::cases() as $status) {
            $tabs[strtolower($status->name)] = Tab::make($status->label())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('client_status', $status->value));
        }

        return $tabs;
    }

    /**
     * Karyawan yang belum punya divisi memilih divisi/toko tempatnya bekerja,
     * sekali saja. Setelah terisi, izin pemohon (BaselineAccess) aktif dan
     * perubahan berikutnya hanya lewat admin (menu Pengguna).
     */
    protected function chooseDivisionAction(): Action
    {
        return Action::make('chooseDivision')
            ->label('Pilih Divisi')
            ->icon('heroicon-o-building-storefront')
            ->visible(fn (): bool => $this->needsDivision())
            ->modalHeading('Pilih divisi Anda')
            ->modalDescription('Data divisi Anda belum ada. Pilih divisi/toko tempat Anda bekerja agar bisa membuat pengajuan barang. Pilihan ini hanya bisa dilakukan sekali; bila keliru, hubungi admin GA untuk mengubahnya.')
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Simpan')
            ->schema([
                Select::make('division_id')
                    ->label('Divisi / toko')
                    ->placeholder('Ketik nama divisi, mis. CS-TEGAL')
                    ->options(fn (): array => Division::query()
                        ->with('area')
                        ->where('name', '!=', '-') // divisi placeholder dari sumo
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Division $division): array => [
                            $division->id => $division->area ? "{$division->name} ({$division->area->name})" : $division->name,
                        ])
                        ->all())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var User $user */
                $user = auth()->user();
                $division = Division::query()->findOrFail($data['division_id']);

                // Dikunci: hanya mengisi bila masih kosong (cegah ganti lewat request ulang).
                $updated = DB::table('users')
                    ->where('id', $user->id)
                    ->whereNull('division_id')
                    ->update(['division_id' => $division->id, 'updated_at' => now()]);

                if ($updated === 0) {
                    Notification::make()->title('Divisi Anda sudah terisi')->body('Hubungi admin GA bila perlu mengubahnya.')->warning()->send();

                    return;
                }

                Log::info('User memilih divisinya sendiri', ['user_id' => $user->id, 'division_id' => $division->id, 'division' => $division->name]);

                Notification::make()->title("Divisi disimpan: {$division->name}")->body('Sekarang Anda bisa membuat pengajuan barang.')->success()->send();

                $this->redirect(ItemRequestResource::getUrl('index'));
            });
    }

    private function needsDivision(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->needsToChooseDivision();
    }

    /**
     * Rekap per Divisi (sumo: RequestExport): pivot barang x divisi untuk satu
     * area, tipe, rentang tanggal pengajuan, dan filter status.
     */
    protected function exportRecapAction(): Action
    {
        return Action::make('exportRecap')
            ->label('Rekap per Divisi')
            ->icon('heroicon-o-table-cells')
            ->color('gray')
            ->visible(fn (): bool => $this->canExport())
            ->modalHeading('Rekap Pengajuan per Divisi')
            ->modalDescription('Unduh Excel jumlah barang per divisi pemohon. Jumlah memakai jumlah disetujui bila sudah diisi, kalau belum jumlah diminta.')
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Unduh')
            ->schema([
                Select::make('area_id')
                    ->label('Area')
                    ->options(fn (): array => Area::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
                Select::make('item_request_type_id')
                    ->label('Tipe pengajuan')
                    ->options(fn (): array => ItemRequestType::query()->orderBy('id')->pluck('name', 'id')->all())
                    ->required(),
                ...$this->dateRangeFields(),
                Select::make('status')
                    ->label('Filter status')
                    ->options(ItemRequestRecapStatus::class)
                    ->default(ItemRequestRecapStatus::NotCancelled->value)
                    ->selectablePlaceholder(false)
                    ->required(),
            ])
            ->action(function (array $data): BinaryFileResponse {
                abort_unless($this->canExport(), 403);

                $export = new ItemRequestRecapExport(
                    auth()->user(),
                    Area::query()->findOrFail($data['area_id']),
                    ItemRequestType::query()->findOrFail($data['item_request_type_id']),
                    Carbon::parse($data['from']),
                    Carbon::parse($data['until']),
                    ItemRequestRecapStatus::coerce($data['status'] ?? ItemRequestRecapStatus::NotCancelled->value),
                );

                return Excel::download($export, $export->fileName());
            });
    }

    /**
     * Ekspor Master QR (sumo: RequestMasterQRExport): id detail, nama barang,
     * pemohon untuk cetak label QR massal.
     */
    protected function exportMasterQrAction(): Action
    {
        return Action::make('exportMasterQr')
            ->label('Ekspor Master QR')
            ->icon('heroicon-o-qr-code')
            ->color('gray')
            ->visible(fn (): bool => $this->canExport())
            ->modalHeading('Ekspor Master QR')
            ->modalDescription('Unduh Excel berisi id detail pengajuan (isi QR), nama barang, dan pemohon, satu baris per barang, untuk cetak label QR massal.')
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Unduh')
            ->schema([
                Select::make('item_request_type_id')
                    ->label('Tipe pengajuan')
                    ->options(fn (): array => ItemRequestType::query()->orderBy('id')->pluck('name', 'id')->all())
                    ->required(),
                ...$this->dateRangeFields(),
            ])
            ->action(function (array $data): BinaryFileResponse {
                abort_unless($this->canExport(), 403);

                $export = new ItemRequestMasterQrExport(
                    auth()->user(),
                    ItemRequestType::query()->findOrFail($data['item_request_type_id']),
                    Carbon::parse($data['from']),
                    Carbon::parse($data['until']),
                );

                return Excel::download($export, $export->fileName());
            });
    }

    /**
     * Rentang tanggal pengajuan (requested_at), default bulan berjalan.
     *
     * @return list<Grid>
     */
    private function dateRangeFields(): array
    {
        return [
            Grid::make(2)->schema([
                DatePicker::make('from')
                    ->label('Dari tanggal')
                    ->native(false)
                    ->default(now()->startOfMonth())
                    ->required(),
                DatePicker::make('until')
                    ->label('Sampai tanggal')
                    ->native(false)
                    ->default(now()->endOfMonth())
                    ->afterOrEqual('from')
                    ->required(),
            ]),
        ];
    }

    private function canExport(): bool
    {
        return auth()->user()?->can('export', ItemRequest::class) ?? false;
    }
}
