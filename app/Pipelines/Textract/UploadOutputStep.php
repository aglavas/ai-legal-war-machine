<?php

namespace App\Pipelines\Textract;

use App\Actions\Textract\UploadOutputToS3;
use Closure;

/**
 * Step: UploadOutputStep
 * Upload the reconstructed PDF to S3 output prefix.
 * Adds: outKey
 */
class UploadOutputStep
{
    public function handle(array $payload, Closure $next): mixed
    {
        $payload['outKey'] = UploadOutputToS3::run($payload['driveFileId'], $payload['targetLocalPath']);
        return $next($payload);
    }
}
