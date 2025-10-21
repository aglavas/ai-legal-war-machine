<?php

namespace App\Pipelines\Textract;

use App\Actions\Textract\DownloadDriveFile;
use Closure;

/**
 * Step: DownloadDriveFileStep
 * Download the file from Google Drive and update status.
 * Adds: localPath
 */
class DownloadDriveFileStep
{
    public function handle(array $payload, Closure $next): mixed
    {
        $payload['job']->update(['status' => 'uploading']);
        $payload['localPath'] = DownloadDriveFile::run($payload['driveFileId']);
        return $next($payload);
    }
}

