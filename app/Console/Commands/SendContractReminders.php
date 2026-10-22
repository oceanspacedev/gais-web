<?php

namespace App\Console\Commands;

use App\Models\WaNotificationSetting;
use App\Services\ContractReminderService;
use Illuminate\Console\Command;

/**
 * sumo: command reminder:whatsapp. Dijadwalkan tiap jam; mengirim sekali
 * sehari setelah jam kirim di pengaturan terlewati (log mencegah pengiriman ganda).
 */
class SendContractReminders extends Command
{
    protected $signature = 'reminder:whatsapp
        {--dry-run : Tampilkan yang akan dikirim tanpa mengirim}
        {--force : Abaikan jam kirim di pengaturan}';

    protected $description = 'Kirim pengingat WhatsApp jatuh tempo sewa, asuransi, dan cutoff pengajuan barang.';

    public function handle(ContractReminderService $reminders): int
    {
        $setting = WaNotificationSetting::current();
        $sendTime = (string) ($setting->send_time ?: '08:00');

        if (! $this->option('force') && ! $this->option('dry-run') && now()->format('H:i') < $sendTime) {
            $this->line("Belum waktunya mengirim (jam kirim {$sendTime}).");

            return self::SUCCESS;
        }

        $summary = $reminders->run((bool) $this->option('dry-run'));

        foreach ($summary['lines'] as $line) {
            $this->line('  '.$line);
        }

        $this->info("Terkirim: {$summary['sent']}, dilewati: {$summary['skipped']}, gagal: {$summary['failed']}.");

        return self::SUCCESS;
    }
}
