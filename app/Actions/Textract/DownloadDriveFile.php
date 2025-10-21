<?php

namespace App\Actions\Textract;

use App\Services\GoogleDriveService;
use Google\Service\Exception;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Action: DownloadDriveFile
 * Purpose: Download a file by its Google Drive ID to a local temporary path.
 */
class DownloadDriveFile
{
    use AsAction;

    public GoogleDriveService $drive;

    public function __construct(GoogleDriveService $drive)
    {
        $this->drive = $drive;
    }

    /**
     * @return string Absolute local file path
     * @throws Exception
     */
    public function handle(string $driveFileId): string
    {
        return $this->drive->downloadFile($driveFileId);
    }
}

