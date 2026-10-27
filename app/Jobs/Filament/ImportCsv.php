<?php

namespace App\Jobs\Filament;

use App\Queue\RestoresQueuedModels;
use Filament\Actions\Imports\Jobs\ImportCsv as BaseImportCsv;

class ImportCsv extends BaseImportCsv
{
    use RestoresQueuedModels;

    public $afterCommit = true;
}
