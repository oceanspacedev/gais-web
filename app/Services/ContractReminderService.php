<?php

namespace App\Services;

use App\Enums\WaNotificationType;
use App\Models\Insurance;
use App\Models\ItemRequestSetting;
use App\Models\Rent;
use App\Models\WaNotificationLog;
use App\Models\WaNotificationSetting;
use App\Support\ContractDue;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Pengingat WhatsApp jatuh tempo sewa, asuransi, dan cutoff pengajuan (sumo:
 * command reminder:whatsapp). Dikirim pada hari H dan pada hari-hari sebelum
 * jatuh tempo yang diatur di Notifikasi WA; satu nomor hanya menerima satu
 * pengingat per referensi per hari (wa_notification_logs).
 */
class ContractReminderService
{
    /** @var array{sent: int, skipped: int, failed: int, lines: list<string>} */
    private array $summary = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'lines' => []];

    public function __construct(private readonly WhatsAppGateway $gateway) {}

    /**
     * @return array{sent: int, skipped: int, failed: int, lines: list<string>}
     */
    public function run(bool $dryRun = false, ?CarbonInterface $today = null): array
    {
        $this->summary = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'lines' => []];
        $today = ($today ?? today())->copy()->startOfDay();
        $setting = WaNotificationSetting::current();

        if (! $setting->is_enabled) {
            $this->summary['lines'][] = 'Notifikasi WhatsApp nonaktif; tidak ada yang dikirim.';

            return $this->summary;
        }

        $global = $setting->phoneList();

        if ($setting->remind_rent) {
            $this->remindRents($setting, $global, $today, $dryRun);
        }

        if ($setting->remind_insurance) {
            $this->remindInsurances($setting, $global, $today, $dryRun);
        }

        if ($setting->remind_request_cutoff) {
            $this->remindRequestCutoff($setting, $global, $today, $dryRun);
        }

        return $this->summary;
    }

    public function testMessage(): string
    {
        return '🔔 *UJI COBA NOTIFIKASI WHATSAPP - '.strtoupper((string) config('app.name'))."*\n\n"
            ."Halo Admin,\nIni adalah pesan uji coba dari Sistem Pengingat Jatuh Tempo.\n\n"
            .'• *Waktu:* '.now()->locale('id')->isoFormat('dddd, D MMMM YYYY HH:mm')." WIB\n\n"
            .'Jika pesan ini diterima, berarti integrasi WhatsApp telah berjalan normal! ✅';
    }

    /**
     * Kirim satu pesan dan catat hasilnya. Mengembalikan true bila terkirim.
     */
    public function send(string $phone, string $message, WaNotificationType $type, ?int $referenceId, bool $dryRun = false): bool
    {
        if ($dryRun) {
            return true;
        }

        $success = $this->gateway->send($phone, $message);

        WaNotificationLog::create([
            'notif_type' => $type,
            'reference_id' => $referenceId,
            'phone' => $phone,
            'sent_date' => today(),
            'status' => $success ? WaNotificationLog::STATUS_SUCCESS : WaNotificationLog::STATUS_FAILED,
            'message_preview' => mb_substr($message, 0, 500),
        ]);

        return $success;
    }

    /**
     * @param  list<string>  $global
     */
    private function remindRents(WaNotificationSetting $setting, array $global, CarbonInterface $today, bool $dryRun): void
    {
        $milestones = $setting->daysBefore('rent_days_before');

        Rent::query()->activeContracts()->with('updates')->each(function (Rent $rent) use ($milestones, $global, $today, $dryRun): void {
            $expiry = $rent->effectiveExpiredDate();
            $days = $this->daysUntil($today, $expiry);

            if ($days === null || ! $this->isMilestone($days, $milestones)) {
                return;
            }

            $code = $rent->latestUpdate()?->rent_code ?? $rent->rent_code;
            $message = "🔔 *PENGINGAT JATUH TEMPO SEWA*\n\n"
                ."Halo Admin, kontrak sewa berikut mendekati masa berakhir:\n\n"
                ."• *Kode Sewa:* {$code}\n"
                ."• *Objek Sewa:* {$rent->rented_detail}\n"
                ."• *Pihak Kedua:* {$rent->second_party}\n"
                .'• *Tgl Berakhir:* '.$this->formatDate($expiry)."\n"
                .'• *Sisa Waktu:* '.$this->remainingLabel($days, 'JATUH TEMPO HARI INI')."\n\n"
                ."Mohon segera koordinasikan perpanjangan kontrak atau konfirmasi status sewa.\n"
                .'Buka sistem: '.url('/admin/rents');

            $this->dispatch(WaNotificationType::Rent, $rent->id, "Sewa [{$code}]", $this->phonesFor($rent->reminder_phones, $global), $message, $today, $dryRun);
        });
    }

    /**
     * @param  list<string>  $global
     */
    private function remindInsurances(WaNotificationSetting $setting, array $global, CarbonInterface $today, bool $dryRun): void
    {
        $milestones = $setting->daysBefore('insurance_days_before');

        Insurance::query()->activeContracts()->with('updates')->each(function (Insurance $insurance) use ($milestones, $global, $today, $dryRun): void {
            $expiry = $insurance->effectiveExpiredDate();
            $days = $this->daysUntil($today, $expiry);

            if ($days === null || ! $this->isMilestone($days, $milestones)) {
                return;
            }

            $policy = $insurance->latestUpdate()?->policy_number ?? $insurance->policy_number;
            $message = "🔔 *PENGINGAT JATUH TEMPO ASURANSI*\n\n"
                ."Halo Admin, polis asuransi berikut mendekati masa kedaluwarsa:\n\n"
                ."• *No. Polis:* {$policy}\n"
                ."• *Tertanggung:* {$insurance->insured_name}\n"
                .'• *Objek/Gudang:* '.($insurance->insured_detail ?? '-')."\n"
                .'• *Tgl Berakhir:* '.$this->formatDate($expiry)."\n"
                .'• *Sisa Waktu:* '.$this->remainingLabel($days, 'JATUH TEMPO HARI INI')."\n\n"
                ."Mohon segera proses pembaharuan polis atau konfirmasi perpanjangan asuransi.\n"
                .'Buka sistem: '.url('/admin/insurances');

            $this->dispatch(WaNotificationType::Insurance, $insurance->id, "Asuransi [{$policy}]", $this->phonesFor($insurance->reminder_phones, $global), $message, $today, $dryRun);
        });
    }

    /**
     * @param  list<string>  $global
     */
    private function remindRequestCutoff(WaNotificationSetting $setting, array $global, CarbonInterface $today, bool $dryRun): void
    {
        $milestones = $setting->daysBefore('request_cutoff_days_before');

        ItemRequestSetting::query()->whereNotNull('closed_date')->each(function (ItemRequestSetting $period) use ($milestones, $global, $today, $dryRun): void {
            $days = $this->daysUntil($today, $period->closed_date);

            if ($days === null || ! $this->isMilestone($days, $milestones)) {
                return;
            }

            $month = $period->request_month?->locale('id')->isoFormat('MMMM YYYY') ?? '-';
            $message = "⚠️ *PENGINGAT BATAS AKHIR PENGAJUAN BARANG*\n\n"
                ."Halo Admin & Tim,\n"
                .'Periode pengajuan barang untuk *'.($period->request_detail ?? 'Pengajuan')."* (Bulan {$month}) akan segera ditutup:\n\n"
                .'• *Tanggal Penutupan:* '.$this->formatDate($period->closed_date)."\n"
                .'• *Sisa Waktu:* '.$this->remainingLabel($days, 'PENUTUPAN HARI INI')."\n\n"
                ."Mohon pastikan seluruh divisi telah menyelesaikan pengajuan sebelum batas waktu tersebut.\n"
                .'Buka sistem: '.url('/admin/item-requests');

            $this->dispatch(WaNotificationType::RequestCutoff, $period->id, "Cutoff pengajuan [{$period->id}]", $global, $message, $today, $dryRun);
        });
    }

    /**
     * @param  list<string>  $phones
     */
    private function dispatch(WaNotificationType $type, int $referenceId, string $label, array $phones, string $message, CarbonInterface $today, bool $dryRun): void
    {
        if ($phones === []) {
            $this->summary['skipped']++;
            $this->summary['lines'][] = "{$label} tidak punya nomor tujuan; dilewati.";

            return;
        }

        foreach ($phones as $phone) {
            if (WaNotificationLog::query()->alreadySent($type, $referenceId, $phone, $today)->exists()) {
                $this->summary['skipped']++;
                $this->summary['lines'][] = "{$label} sudah dikirim ke {$phone} hari ini; dilewati.";

                continue;
            }

            if ($this->send($phone, $message, $type, $referenceId, $dryRun)) {
                $this->summary['sent']++;
                $this->summary['lines'][] = ($dryRun ? '[UJI] ' : '')."{$label} dikirim ke {$phone}.";
            } else {
                $this->summary['failed']++;
                $this->summary['lines'][] = "{$label} gagal dikirim ke {$phone}.";
            }
        }
    }

    /**
     * @param  list<string>  $global
     * @return list<string>
     */
    private function phonesFor(?string $own, array $global): array
    {
        $ownList = WaNotificationSetting::parsePhoneList($own);

        return $ownList !== [] ? $ownList : $global;
    }

    private function daysUntil(CarbonInterface $today, ?CarbonInterface $date): ?int
    {
        return $date === null ? null : ContractDue::daysUntil($date, $today);
    }

    /**
     * @param  list<int>  $milestones
     */
    private function isMilestone(int $days, array $milestones): bool
    {
        return $days === 0 || in_array($days, $milestones, true);
    }

    private function remainingLabel(int $days, string $todayLabel): string
    {
        return $days === 0 ? "⚠️ *{$todayLabel}*" : "⚠️ *{$days} Hari Lagi*";
    }

    private function formatDate(?CarbonInterface $date): string
    {
        return $date === null ? '-' : Carbon::instance($date)->locale('id')->isoFormat('DD MMMM YYYY');
    }
}
