<?php

namespace App\Console\Commands;

use App\Services\ProblemReportWorkflow;
use Illuminate\Console\Command;

/**
 * sumo: command `autoapprove`. Laporan gangguan yang sudah ditutup lebih dari
 * sehari tanpa konfirmasi pelapor dianggap selesai.
 */
class AutoConfirmProblemReports extends Command
{
    protected $signature = 'problem-reports:auto-confirm';

    protected $description = 'Tandai selesai laporan gangguan yang ditutup lebih dari 24 jam lalu tanpa konfirmasi pelapor.';

    public function handle(ProblemReportWorkflow $workflow): int
    {
        $count = $workflow->autoConfirmClosed();

        $this->info("{$count} laporan ditandai selesai.");

        return self::SUCCESS;
    }
}
