<?php

namespace App\Filament\Pages;

use App\Enums\WaNotificationType;
use App\Models\WaNotificationLog;
use App\Models\WaNotificationSetting;
use App\Services\ContractReminderService;
use App\Services\ReminderPhoneReplacer;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;

/**
 * Pengaturan pengingat WhatsApp (sumo: Pengaturan > Notifikasi WA): nomor
 * tujuan global, jam kirim, hari-hari pengingat, uji kirim, dan ganti nomor.
 */
class WaNotificationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 60;

    protected static ?string $title = 'Notifikasi WA';

    protected string $view = 'filament.pages.wa-notification-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:WaNotificationSettings') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(WaNotificationSetting::current()->only([
            'is_enabled', 'target_phones', 'send_time', 'remind_rent', 'rent_days_before',
            'remind_insurance', 'insurance_days_before', 'remind_request_cutoff', 'request_cutoff_days_before',
        ]));
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->statePath('data')
            ->columns(1)
            ->schema([
                Section::make('Umum')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_enabled')->label('Aktifkan pengingat WhatsApp')->columnSpanFull(),
                        TextInput::make('target_phones')
                            ->label('Nomor tujuan global')
                            ->helperText('Pisahkan dengan koma. Dipakai bila data sewa/asuransi tidak punya nomor sendiri.')
                            ->maxLength(255),
                        TimePicker::make('send_time')->label('Jam kirim')->seconds(false)->required(),
                    ]),
                Section::make('Pengingat')
                    ->columns(2)
                    ->schema([
                        Toggle::make('remind_rent')->label('Jatuh tempo sewa'),
                        TextInput::make('rent_days_before')->label('Hari sebelum (sewa)')->helperText('Contoh: 30,14,7,1')->regex('/^\d+(,\s*\d+)*$/'),
                        Toggle::make('remind_insurance')->label('Jatuh tempo asuransi'),
                        TextInput::make('insurance_days_before')->label('Hari sebelum (asuransi)')->regex('/^\d+(,\s*\d+)*$/'),
                        Toggle::make('remind_request_cutoff')->label('Cutoff pengajuan barang'),
                        TextInput::make('request_cutoff_days_before')->label('Hari sebelum (cutoff)')->regex('/^\d+(,\s*\d+)*$/'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $data['target_phones'] = implode(', ', WaNotificationSetting::parsePhoneList($data['target_phones'] ?? null));

        WaNotificationSetting::current()->update($data);

        Notification::make()->title('Pengaturan notifikasi disimpan.')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testSend')
                ->label('Kirim Uji Coba')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->form([
                    TextInput::make('phone')->label('Nomor WhatsApp')->required()->maxLength(30),
                ])
                ->action(function (array $data): void {
                    $service = app(ContractReminderService::class);
                    $phone = preg_replace('/[^0-9]/', '', (string) $data['phone']) ?? '';
                    $sent = $phone !== '' && $service->send($phone, $service->testMessage(), WaNotificationType::Test, null);

                    Notification::make()
                        ->title($sent ? "Pesan uji coba terkirim ke {$phone}." : 'Gagal mengirim pesan uji coba; periksa WAG_URL/WAG_TOKEN dan nomor.')
                        ->{$sent ? 'success' : 'danger'}()
                        ->send();
                }),

            Action::make('replacePhone')
                ->label('Ganti Nomor')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->modalDescription('Nomor yang dipilih diganti (atau dihapus bila nomor baru kosong) di semua data sewa dan asuransi.')
                ->form([
                    Select::make('old_phone')
                        ->label('Nomor lama')
                        ->options(fn (): array => collect(app(ReminderPhoneReplacer::class)->groups())
                            ->mapWithKeys(fn (array $group): array => [$group['phone'] => "{$group['phone']} (sewa {$group['rent']}, asuransi {$group['insurance']})"])
                            ->all())
                        ->required()
                        ->searchable(),
                    TextInput::make('new_phone')->label('Nomor baru (kosongkan untuk menghapus)')->maxLength(30),
                ])
                ->action(function (array $data): void {
                    $result = app(ReminderPhoneReplacer::class)->replace((string) $data['old_phone'], filled($data['new_phone'] ?? null) ? (string) $data['new_phone'] : null);

                    Notification::make()
                        ->title("Nomor diperbarui pada {$result['rent']} sewa dan {$result['insurance']} asuransi.")
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @return Collection<int, WaNotificationLog>
     */
    public function getRecentLogsProperty()
    {
        return WaNotificationLog::query()->latest('id')->limit(20)->get();
    }
}
