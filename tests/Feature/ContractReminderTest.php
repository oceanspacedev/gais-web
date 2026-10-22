<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Enums\WaNotificationType;
use App\Models\Insurance;
use App\Models\ItemRequestSetting;
use App\Models\Rent;
use App\Models\WaNotificationLog;
use App\Models\WaNotificationSetting;
use App\Services\ContractReminderService;
use App\Services\ReminderPhoneReplacer;
use App\Services\WhatsAppGateway;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ContractReminderTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<array{0: string, 1: string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $sent = &$this->sent;
        $gateway = new class($sent) extends WhatsAppGateway
        {
            /** @param list<array{0: string, 1: string}> $sent */
            public function __construct(private array &$sent)
            {
                parent::__construct();
            }

            public function send($phoneNumber, string $message): bool
            {
                $this->sent[] = [(string) $phoneNumber, $message];

                return true;
            }
        };
        $this->app->instance(WhatsAppGateway::class, $gateway);

        WaNotificationSetting::current()->update([
            'is_enabled' => true,
            'target_phones' => '08111, 08222',
            'remind_rent' => true,
            'rent_days_before' => '30,7',
            'remind_insurance' => true,
            'insurance_days_before' => '30',
            'remind_request_cutoff' => true,
            'request_cutoff_days_before' => '3',
        ]);
        WaNotificationLog::query()->delete();
    }

    public function test_reminders_go_out_on_milestones_once_per_day_and_use_record_numbers(): void
    {
        $today = today();

        Rent::create(['rent_code' => 'R-7', 'rented_detail' => 'Ruko', 'first_party' => 'A', 'second_party' => 'B', 'join_date' => $today->copy()->subYear(), 'expired_date' => $today->copy()->addDays(7), 'status' => ContractStatus::Running->value, 'reminder_phones' => '08999']);
        Rent::create(['rent_code' => 'R-5', 'rented_detail' => 'Ruko', 'first_party' => 'A', 'second_party' => 'B', 'join_date' => $today->copy()->subYear(), 'expired_date' => $today->copy()->addDays(5), 'status' => ContractStatus::Running->value]);
        Rent::create(['rent_code' => 'R-TUTUP', 'rented_detail' => 'Ruko', 'first_party' => 'A', 'second_party' => 'B', 'join_date' => $today->copy()->subYear(), 'expired_date' => $today, 'status' => ContractStatus::Closed->value]);

        $insurance = Insurance::create(['policy_number' => 'P-OLD', 'insured_name' => 'Gudang', 'join_date' => $today->copy()->subYear(), 'expired_date' => $today->copy()->addDays(30), 'status' => ContractStatus::Running->value]);
        $insurance->updates()->create(['policy_number' => 'P-NEW', 'join_date' => $today, 'expired_date' => $today, 'status' => ContractStatus::Renewed->value]);

        ItemRequestSetting::create(['request_month' => $today->copy()->startOfMonth(), 'open_date' => $today, 'closed_date' => $today->copy()->addDays(3), 'request_detail' => 'Pengajuan ATK Bulanan']);

        $summary = app(ContractReminderService::class)->run();

        $messages = collect($this->sent);
        $this->assertSame(['08999'], $messages->filter(fn (array $m): bool => str_contains($m[1], 'R-7'))->pluck(0)->all(), 'Sewa memakai nomornya sendiri.');
        $this->assertCount(0, $messages->filter(fn (array $m): bool => str_contains($m[1], 'R-5')), 'Lima hari bukan hari pengingat.');
        $this->assertCount(0, $messages->filter(fn (array $m): bool => str_contains($m[1], 'R-TUTUP')), 'Kontrak tutup tidak diingatkan.');
        $this->assertCount(2, $messages->filter(fn (array $m): bool => str_contains($m[1], 'P-NEW') && str_contains($m[1], 'JATUH TEMPO HARI INI')), 'Asuransi memakai pembaharuan terakhir dan dua nomor global.');
        $this->assertCount(2, $messages->filter(fn (array $m): bool => str_contains($m[1], 'Pengajuan ATK Bulanan')), implode(' | ', $summary['lines']));
        // Data impor yang kebetulan jatuh tempo hari ini ikut terkirim, jadi batas bawah.
        $this->assertGreaterThanOrEqual(5, $summary['sent']);
        $this->assertGreaterThanOrEqual(5, WaNotificationLog::query()->where('status', WaNotificationLog::STATUS_SUCCESS)->count());

        $this->sent = [];
        $again = app(ContractReminderService::class)->run();
        $this->assertSame(0, $again['sent']);
        $this->assertGreaterThanOrEqual(5, $again['skipped']);
        $this->assertCount(0, $this->sent, 'Tidak dikirim dua kali di hari yang sama.');
    }

    public function test_dry_run_command_sends_nothing_and_phone_replacer_rewrites_records(): void
    {
        Rent::create(['rent_code' => 'R-X', 'rented_detail' => 'Ruko', 'first_party' => 'A', 'second_party' => 'B', 'join_date' => today()->subYear(), 'expired_date' => today(), 'status' => ContractStatus::Running->value, 'reminder_phones' => '08999, 08777']);

        $this->artisan('reminder:whatsapp', ['--dry-run' => true])->assertSuccessful();
        $this->assertCount(0, $this->sent);
        $this->assertSame(0, WaNotificationLog::query()->count());

        $result = app(ReminderPhoneReplacer::class)->replace('08999', '08555');
        $this->assertSame(1, $result['rent']);
        $this->assertSame('08555, 08777', Rent::query()->where('rent_code', 'R-X')->value('reminder_phones'));

        app(ReminderPhoneReplacer::class)->replace('08777', null);
        $this->assertSame('08555', Rent::query()->where('rent_code', 'R-X')->value('reminder_phones'));
    }

    public function test_disabled_setting_sends_nothing(): void
    {
        WaNotificationSetting::current()->update(['is_enabled' => false]);
        Rent::create(['rent_code' => 'R-Y', 'rented_detail' => 'Ruko', 'first_party' => 'A', 'second_party' => 'B', 'join_date' => today()->subYear(), 'expired_date' => today(), 'status' => ContractStatus::Running->value]);

        $summary = app(ContractReminderService::class)->run();

        $this->assertSame(0, $summary['sent']);
        $this->assertCount(0, $this->sent);
        $this->assertSame(WaNotificationType::Rent, WaNotificationType::from('RENT'));
    }
}
