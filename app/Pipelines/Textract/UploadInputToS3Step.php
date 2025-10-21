<?php

namespace App\Pipelines\Textract;

use App\Actions\Textract\UploadInputToS3;
use Closure;

/**
 * Step: UploadInputToS3Step
 * Upload original PDF to S3 input prefix.
 * Adds: s3Key
 */
class UploadInputToS3Step
{
    public function handle(array $payload, Closure $next): mixed
    {
        $payload['s3Key'] = UploadInputToS3::run($payload['localPath'], $payload['driveFileId']);
        $payload['job']->update(['s3_key' => $payload['s3Key'], 'status' => 'started']);
        return $next($payload);
    }
}

