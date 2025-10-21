<?php

namespace App\Pipelines\Textract;

use App\Actions\Textract\SaveAnalysisResults;
use Closure;

/**
 * Step: SaveResultsStep
 * Save JSON blocks to S3 and local; payload not modified except for optional meta.
 */
class SaveResultsStep
{
    public function handle(array $payload, Closure $next): mixed
    {
        $meta = SaveAnalysisResults::run($payload['driveFileId'], $payload['blocks']);
        $payload['resultsMeta'] = $meta;
        return $next($payload);
    }
}

