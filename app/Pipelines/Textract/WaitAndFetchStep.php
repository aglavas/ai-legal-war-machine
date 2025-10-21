<?php

namespace App\Pipelines\Textract;

use App\Actions\Textract\WaitAndFetchTextract;
use Closure;

/**
 * Step: WaitAndFetchStep
 * Wait for Textract analysis and collect blocks.
 * Adds: blocks
 */
class WaitAndFetchStep
{
    public function handle(array $payload, Closure $next): mixed
    {
        $payload['blocks'] = WaitAndFetchTextract::run($payload['jobId']);
        return $next($payload);
    }
}

