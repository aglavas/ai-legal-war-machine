<?php

namespace App\Actions\Textract;

use App\Services\GoogleDriveService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Action: ListDrivePdfs
 * Purpose: Encapsulate listing PDFs from a Google Drive folder so it's easy to unit test
 *          without touching the Artisan command or other orchestration code.
 */
class ListDrivePdfs
{
    use AsAction;

    public GoogleDriveService $drive;

    public function __construct(GoogleDriveService $drive)
    {
        $this->drive = $drive;
    }

    /**
     * Contract
     * - Input: $folderId (Google Drive folder ID)
     * - Output: array of files with keys: id, name, mimeType, size
     */
    public function handle(string $folderId): array
    {
        return $this->drive->listPdfsInFolder($folderId);
    }
}

