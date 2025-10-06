<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Drive;

class GoogleDriveService
{
    protected Drive $drive;

    public function __construct()
    {
        $client = new GoogleClient();
        $credentialsPath = env('GOOGLE_APPLICATION_CREDENTIALS');
        if (!$credentialsPath || !is_file($credentialsPath)) {
            throw new \RuntimeException('GOOGLE_APPLICATION_CREDENTIALS not set or file not found.');
        }
        $client->setAuthConfig($credentialsPath);
        $client->setScopes([Drive::DRIVE_READONLY]);
        $impersonate = env('GOOGLE_IMPERSONATE_USER');
        if ($impersonate) {
            $client->setSubject($impersonate);
        }
        $this->drive = new Drive($client);
    }

    /**
     * Return list of PDFs in a folder.
     * @return array<int, array{id:string,name:string,mimeType:string,size:int}>
     */
    public function listPdfsInFolder(string $folderId, int $pageSize = 100): array
    {
        $files = [];
        $pageToken = null;
        $q = sprintf("'%s' in parents and mimeType='application/pdf' and trashed=false", $folderId);

        do {
            $response = $this->drive->files->listFiles([
                'q' => $q,
                'pageSize' => $pageSize,
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
                'fields' => 'nextPageToken, files(id, name, mimeType, size)',
                'pageToken' => $pageToken,
            ]);

            foreach ($response->getFiles() as $file) {
                $files[] = [
                    'id' => (string) $file->getId(),
                    'name' => (string) $file->getName(),
                    'mimeType' => (string) $file->getMimeType(),
                    'size' => (int) $file->getSize(),
                ];
            }
            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        return $files;
    }

    /**
     * Download a Drive file to a local temp path.
     * @return string absolute local path
     */
    public function downloadFile(string $fileId, ?string $targetPath = null): string
    {
        $targetPath ??= storage_path('app/tmp/' . $fileId . '.pdf');
        @mkdir(dirname($targetPath), 0775, true);

        $response = $this->drive->files->get($fileId, ['alt' => 'media']);
        $content = $response->getBody()->getContents();
        file_put_contents($targetPath, $content);

        return $targetPath;
    }
}

