<?php

namespace App\Jobs\Filament;

use App\Queue\RestoresQueuedModels;
use Filament\Actions\Exports\Jobs\ExportCompletion as BaseExportCompletion;

class ExportCompletion extends BaseExportCompletion
{
    use RestoresQueuedModels;

    public $afterCommit = true;
}
