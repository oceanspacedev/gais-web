<?php

namespace App\Services;

use App\Models\ObChecksheet;
use App\Models\ObRoom;
use App\Models\ObShift;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ObTaskAssignmentService
{
    /**
     * Buat satu tugas pending per ruangan shift. $roomIds membatasi ke ruangan yang
     * dicentang atasan; null berarti semua ruangan shift. Tanpa OB, tugas masuk kolam bersama.
     *
     * @param  array<int, int|string>|null  $roomIds
     * @return array{created: int, skipped: int}
     */
    public function assignShift(ObShift $shift, ?array $roomIds, ?User $officeBoy, CarbonInterface $date, User $assigner): array
    {
        $rooms = $shift->rooms()
            ->where('ob_rooms.is_active', true)
            ->when($roomIds !== null, fn ($query) => $query->whereKey(array_map('intval', $roomIds)))
            ->get()
            ->all();

        return $this->assignRooms($rooms, $officeBoy, $date, $assigner, $shift);
    }

    /**
     * Ruangan yang sudah ditugaskan pada tanggal yang sama dilewati, supaya menekan tombol
     * dua kali tidak menggandakan tugas. Untuk satu OB yang dicek hanya miliknya; untuk
     * kolam bersama dicek semua tugas atasan di tanggal itu, termasuk yang sudah diambil.
     * Ruangan master dibandingkan per id (nama sama di lokasi lain tetap dibuat), nama
     * ketikan di luar master dibandingkan per nama.
     *
     * @param  array<int, ObRoom|string|null>  $rooms
     * @return array{created: int, skipped: int}
     */
    public function assignRooms(array $rooms, ?User $officeBoy, CarbonInterface $date, User $assigner, ?ObShift $shift = null): array
    {
        $rooms = $this->resolveRooms($rooms, $shift);

        return DB::transaction(function () use ($rooms, $officeBoy, $date, $assigner, $shift): array {
            $existing = ObChecksheet::query()
                ->when($officeBoy !== null, fn ($query) => $query->where('user_id', $officeBoy->id))
                ->where('source', ObChecksheet::SOURCE_ASSIGNED)
                ->whereDate('scheduled_date', $date->toDateString())
                ->get(['room', 'ob_room_id']);

            $existingRoomIds = $existing->pluck('ob_room_id')->filter()->all();
            $existingNames = $existing
                ->whereNull('ob_room_id')
                ->pluck('room')
                ->map(fn (?string $room): string => mb_strtolower(ObRoom::normalizeName($room)))
                ->all();

            $created = 0;
            $skipped = 0;

            foreach ($rooms as ['name' => $name, 'id' => $roomId]) {
                $duplicate = $roomId !== null
                    ? in_array($roomId, $existingRoomIds, true)
                    : in_array(mb_strtolower($name), $existingNames, true);

                if ($duplicate) {
                    $skipped++;

                    continue;
                }

                ObChecksheet::create([
                    'user_id' => $officeBoy?->id,
                    'assigned_by' => $assigner->id,
                    'source' => ObChecksheet::SOURCE_ASSIGNED,
                    'status' => ObChecksheet::STATUS_PENDING,
                    'scheduled_date' => $date->toDateString(),
                    'ob_shift_id' => $shift?->id,
                    'room' => $name,
                    'ob_room_id' => $roomId,
                ]);

                $created++;
            }

            return ['created' => $created, 'skipped' => $skipped];
        });
    }

    /**
     * @param  array<int, ObRoom|string|null>  $rooms
     * @return Collection<int, array{name: string, id: int|null}>
     */
    private function resolveRooms(array $rooms, ?ObShift $shift): Collection
    {
        return collect($rooms)
            ->map(function (mixed $room) use ($shift): ?array {
                if ($room instanceof ObRoom) {
                    return ['name' => $room->name, 'id' => $room->id];
                }

                $name = ObRoom::normalizeName(is_string($room) ? $room : null);

                if ($name === '') {
                    return null;
                }

                $master = ObRoom::findByName($name, $shift?->asset_location_id);

                return ['name' => $master?->name ?? $name, 'id' => $master?->id];
            })
            ->filter()
            ->unique(fn (array $room): string => $room['id'] !== null ? 'id:'.$room['id'] : 'name:'.mb_strtolower($room['name']))
            ->values();
    }
}
