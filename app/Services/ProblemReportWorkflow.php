<?php

namespace App\Services;

use App\Enums\ProblemReportStatus;
use App\Exceptions\ItemRequestException;
use App\Models\ProblemReport;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Aturan laporan gangguan (sumo: ProblemReportController + autoapprove):
 * satu laporan terbuka per pelapor, executor memproses, pelapor mengonfirmasi,
 * dan konfirmasi otomatis sehari setelah ditutup.
 */
class ProblemReportWorkflow
{
    /**
     * @param  array{user_id?: int|string|null, problem_report_category_id: int|string, description?: string|null, title?: string|null, photo_before?: string|null}  $data
     */
    public function create(User $actor, array $data): ProblemReport
    {
        $reporter = filled($data['user_id'] ?? null) ? User::query()->findOrFail($data['user_id']) : $actor;

        if (blank($data['problem_report_category_id'] ?? null)) {
            throw new ItemRequestException('Harap pilih jenis gangguan.');
        }

        if (blank($data['photo_before'] ?? null)) {
            throw new ItemRequestException('Harap unggah foto gangguan atau kerusakan.');
        }

        if (ProblemReport::query()->where('user_id', $reporter->id)->awaitingClient()->exists()) {
            throw new ItemRequestException('Harap menunggu hingga laporan sebelumnya diproses dan status akhirnya diselesaikan.');
        }

        return ProblemReport::create([
            'user_id' => $reporter->id,
            'problem_report_category_id' => $data['problem_report_category_id'],
            'business_entity_id' => $reporter->business_entity_id,
            'reported_at' => now(),
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'photo_before' => $data['photo_before'],
        ])->fresh();
    }

    /**
     * Executor: ubah status, jadwal, foto hasil, dan keterangan hasil.
     * Pembatalan langsung dianggap selesai bagi pelapor (sumo).
     */
    public function process(ProblemReport $report, User $actor, ProblemReportStatus $status, ?CarbonInterface $scheduledAt = null, ?string $photoAfter = null, ?string $resultDesc = null): void
    {
        DB::transaction(function () use ($report, $actor, $status, $scheduledAt, $photoAfter, $resultDesc): void {
            $attributes = [
                'status' => $status,
                'result_desc' => $resultDesc,
                'scheduled_at' => $scheduledAt ?? $report->scheduled_at,
            ];

            if (filled($photoAfter)) {
                $attributes['photo_after'] = $photoAfter;
            }

            if ($status === ProblemReportStatus::Pending) {
                $attributes['closed_by_user_id'] = null;
                $attributes['closed_at'] = null;
            } else {
                $attributes['closed_by_user_id'] = $actor->id;
                $attributes['closed_at'] = now();
            }

            if ($status === ProblemReportStatus::Cancelled) {
                $attributes['client_status'] = ProblemReport::CLIENT_CONFIRMED;
            }

            $report->forceFill($attributes)->save();
        });
    }

    public function confirm(ProblemReport $report, User $actor, ?string $review = null): void
    {
        if ($report->status !== ProblemReportStatus::Closed) {
            throw new ItemRequestException('Laporan belum diselesaikan oleh executor.');
        }

        $report->forceFill([
            'client_status' => ProblemReport::CLIENT_CONFIRMED,
            'review_user' => $review,
        ])->save();
    }

    /**
     * Laporan yang sudah ditutup lebih dari sehari tanpa konfirmasi pelapor
     * dianggap selesai (sumo: command autoapprove, dulu membandingkan tanggal saja).
     */
    public function autoConfirmClosed(?CarbonInterface $now = null): int
    {
        $threshold = ($now ?? now())->copy()->subDay();

        return ProblemReport::query()
            ->whereNotNull('closed_by_user_id')
            ->where('client_status', ProblemReport::CLIENT_WAITING)
            ->where('closed_at', '<=', $threshold)
            ->update(['client_status' => ProblemReport::CLIENT_CONFIRMED]);
    }
}
