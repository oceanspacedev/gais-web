<?php

namespace App\Jobs\Filament;

use App\Queue\RestoresQueuedModels;
use Filament\Actions\Exports\Jobs\PrepareCsvExport as BasePrepareCsvExport;

class PrepareCsvExport extends BasePrepareCsvExport
{
    use RestoresQueuedModels;

    public $afterCommit = true;
}
