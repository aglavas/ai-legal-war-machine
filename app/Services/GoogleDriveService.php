<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Drive;
use Google\Service\Exception;
use GuzzleHttp\Exception\GuzzleException;

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
     * @throws Exception
     */
    public function listPdfsInFolder(string $folderId, int $pageSize = 100): array
    {
        $files = [];
        $pageToken = null;

        do {
            $response = $this->drive->files->listFiles([
                'q' => sprintf("'%s' in parents and mimeType='application/pdf' and trashed=false", $folderId),
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
     * Download a Drive file to a local path using authorized HTTP client.
     * @return string absolute local path
     * @throws GuzzleException
     */
    public function downloadFile(string $fileId, ?string $targetPath = null): string
    {
        $targetPath ??= storage_path('app/textract/source/' . $fileId . '.pdf');
        @mkdir(dirname($targetPath), 0775, true);

        // Use authorized HTTP client to download media content
        $client = $this->drive->getClient();
        $http = $client->authorize();
        $url = sprintf('https://www.googleapis.com/drive/v3/files/%s?alt=media', urlencode($fileId));
        $resp = $http->request('GET', $url, ['stream' => true]);
        $content = (string) $resp->getBody();

        file_put_contents($targetPath, $content);
        return $targetPath;
    }
}
