<?php

namespace App\Pipelines\Textract;

use App\Actions\Textract\ReconstructPdfV2;
use Closure;

/**
 * Step: ReconstructPdfStep
 * Build the searchable PDF from OcrDocument using new TextractPdfReconstructor.
 * Adds: targetLocalPath
 */
class ReconstructPdfStep
{
    public function handle(array $payload, Closure $next): mixed
    {
        $payload['targetLocalPath'] = ReconstructPdfV2::run(
            $payload['ocrDocument'],
            $payload['driveFileId']
        );
        return $next($payload);
    }
}
