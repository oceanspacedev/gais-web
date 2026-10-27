<?php

namespace App\Jobs\Filament;

use App\Queue\RestoresQueuedModels;
use Filament\Actions\Exports\Jobs\ExportCsv as BaseExportCsv;

class ExportCsv extends BaseExportCsv
{
    use RestoresQueuedModels;

    public $afterCommit = true;
}
