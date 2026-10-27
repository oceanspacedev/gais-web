<?php

namespace App\Queue;

use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Retry restoring a queued Eloquent model that was committed a moment earlier.
 *
 * Filament nests PrepareCsvExport inside Illuminate\Bus\ChainedBatch. That
 * wrapper does not set $deleteWhenMissingModels, so ModelNotFoundException
 * during unserialize fails the chain and is reported. The same lookup races
 * a still-open transaction or a read replica. handle() never runs, so job
 * middleware and $tries cannot help.
 */
trait RestoresQueuedModels
{
    public function restoreModel($value)
    {
        $attempts = max(1, (int) config('queue.missing_model_retry.attempts', 15));
        $delayMilliseconds = max(0, (int) config('queue.missing_model_retry.delay_ms', 200));
        $exception = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return parent::restoreModel($value);
            } catch (ModelNotFoundException $exception) {
                if ($attempt === $attempts) {
                    throw $exception;
                }

                if ($delayMilliseconds > 0) {
                    usleep($delayMilliseconds * 1000);
                }
            }
        }

        throw $exception ?? new ModelNotFoundException;
    }
}
