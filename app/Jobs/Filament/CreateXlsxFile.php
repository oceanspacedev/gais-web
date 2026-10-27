<?php

namespace App\Jobs\Filament;

use App\Queue\RestoresQueuedModels;
use Filament\Actions\Exports\Jobs\CreateXlsxFile as BaseCreateXlsxFile;

class CreateXlsxFile extends BaseCreateXlsxFile
{
    use RestoresQueuedModels;

    public $afterCommit = true;
}
