<?php

namespace App\Services\Sumo\Concerns;

/**
 * Laporan gangguan (sumo: problem_report). Pelapor wajib ada di shelf.
 */
trait ImportsProblemReports
{
    protected function importProblemReports(): void
    {
        $this->source('problem_report')->orderBy('id')->chunk(500, function ($reports): void {
            $rows = [];

            foreach ($reports as $report) {
                $userId = $this->mapUser($report->user_id ?? null);

                if ($userId === null) {
                    $this->report->warn("Laporan gangguan sumo #{$report->id} dilewati: pelapor #{$report->user_id} tidak terpetakan.");

                    continue;
                }

                $status = strtoupper($this->str($report->status ?? null) ?? 'PENDING');

                $rows[] = [
                    'id' => (int) $report->id,
                    'code' => $this->str($report->problem_report_code ?? null) ?? config('item-request.problem_report_code_prefix', 'PR').$report->id,
                    'user_id' => $userId,
                    'problem_report_category_id' => $this->idOrNull('problem_report_categories', $report->pr_category_id ?? null),
                    'business_entity_id' => $this->entityOfSumoUser($report->user_id ?? null),
                    'reported_at' => $this->dateOrNull($report->date ?? null) ?? $this->dateOrNull($report->created_at ?? null) ?? now()->format('Y-m-d H:i:s'),
                    'title' => $this->str($report->title ?? null),
                    'description' => $this->str($report->description ?? null),
                    'photo_before' => $this->str($report->photo_before ?? null),
                    'photo_after' => $this->str($report->photo_after ?? null),
                    'status' => in_array($status, ['PENDING', 'CLOSED', 'CANCELLED'], true) ? $status : 'PENDING',
                    'scheduled_at' => $this->dateOrNull($report->scheduled_at ?? null, true),
                    'client_status' => (int) ($report->status_client ?? 0),
                    'review_user' => $this->str($report->review_user ?? null),
                    'closed_by_user_id' => $this->mapUser($report->closed_by ?? null),
                    'closed_at' => $this->dateOrNull($report->closed_at ?? null),
                    'result_desc' => $this->str($report->result_desc ?? null),
                ] + $this->timestamps($report);
            }

            $this->upsert('problem_reports', $rows, [
                'code', 'user_id', 'problem_report_category_id', 'business_entity_id', 'reported_at', 'title', 'description',
                'photo_before', 'photo_after', 'status', 'scheduled_at', 'client_status', 'review_user', 'closed_by_user_id',
                'closed_at', 'result_desc', 'updated_at', 'deleted_at',
            ], 'problem_reports');
        });
    }
}
