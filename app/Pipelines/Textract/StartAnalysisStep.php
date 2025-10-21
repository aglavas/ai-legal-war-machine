<?php

namespace App\Pipelines\Textract;

use App\Actions\Textract\StartTextractAnalysis;
use Closure;

/**
 * Step: StartAnalysisStep
 * Start Textract analysis job.
 * Adds: jobId
 */
class StartAnalysisStep
{
    public function handle(array $payload, Closure $next): mixed
    {
        $featureTypes = $payload['featureTypes'] ?? ['LAYOUT', 'FORMS', 'TABLES', 'SIGNATURES'];
        $payload['jobId'] = StartTextractAnalysis::run($payload['s3Key'], $payload['driveFileName'], $featureTypes);
        $payload['job']->update(['job_id' => $payload['jobId'], 'status' => 'analyzing']);
        return $next($payload);
    }
}

