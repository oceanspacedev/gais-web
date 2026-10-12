<?php

namespace App\Services;

use App\Models\Insurance;
use App\Models\Rent;
use App\Models\WaNotificationSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Mengganti atau menghapus satu nomor WA pengingat di semua data sewa dan
 * asuransi sekaligus (sumo: Notifikasi WA > ganti nomor).
 */
class ReminderPhoneReplacer
{
    /**
     * Semua nomor yang tersimpan di data sewa/asuransi beserta jumlah pemakaiannya.
     *
     * @return array<string, array{phone: string, rent: int, insurance: int}>
     */
    public function groups(): array
    {
        $groups = [];

        foreach (['rent' => Rent::query(), 'insurance' => Insurance::query()] as $kind => $query) {
            foreach ($query->whereNotNull('reminder_phones')->where('reminder_phones', '<>', '')->pluck('reminder_phones') as $raw) {
                foreach (WaNotificationSetting::parsePhoneList($raw) as $phone) {
                    $groups[$phone] ??= ['phone' => $phone, 'rent' => 0, 'insurance' => 0];
                    $groups[$phone][$kind]++;
                }
            }
        }

        ksort($groups);

        return $groups;
    }

    /**
     * @return array{rent: int, insurance: int}
     */
    public function replace(string $oldPhone, ?string $newPhone): array
    {
        return [
            'rent' => $this->rewrite(Rent::query(), $oldPhone, $newPhone),
            'insurance' => $this->rewrite(Insurance::query(), $oldPhone, $newPhone),
        ];
    }

    /**
     * @param  Builder<Rent|Insurance>  $query
     */
    private function rewrite(Builder $query, string $oldPhone, ?string $newPhone): int
    {
        $old = preg_replace('/[^0-9]/', '', $oldPhone) ?? '';
        $new = $newPhone === null ? null : (preg_replace('/[^0-9]/', '', $newPhone) ?: null);
        $count = 0;

        if ($old === '') {
            return 0;
        }

        $query->whereNotNull('reminder_phones')->where('reminder_phones', 'like', "%{$old}%")->each(function (Model $record) use ($old, $new, &$count): void {
            $phones = WaNotificationSetting::parsePhoneList($record->reminder_phones);

            if (! in_array($old, $phones, true)) {
                return;
            }

            $phones = array_values(array_unique(array_filter(array_map(fn (string $phone): ?string => $phone === $old ? $new : $phone, $phones))));
            $record->forceFill(['reminder_phones' => $phones === [] ? null : implode(', ', $phones)])->save();
            $count++;
        });

        return $count;
    }
}
