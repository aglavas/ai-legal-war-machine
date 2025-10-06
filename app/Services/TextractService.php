<?php

namespace App\Services;

use Aws\Textract\TextractClient;
use Illuminate\Support\Facades\Storage;

class TextractService
{
    protected TextractClient $client;
    protected string $bucket;
    protected string $inputPrefix;
    protected string $jsonPrefix;

    public function __construct()
    {
        $this->client = new TextractClient([
            'version' => '2018-06-27',
            'region'  => env('AWS_DEFAULT_REGION'),
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);

        $this->bucket = (string) env('AWS_BUCKET');
        $this->inputPrefix = trim((string) env('S3_INPUT_PREFIX', 'textract/input'), '/');
        $this->jsonPrefix = trim((string) env('S3_JSON_PREFIX', 'textract/json'), '/');
    }

    public function uploadToS3(string $localPath, string $s3Key): string
    {
        $disk = Storage::disk('s3');
        $disk->put($s3Key, fopen($localPath, 'r'), ['visibility' => 'private']);
        return $s3Key;
    }

    /**
     * Start async text detection for PDFs/TIFFs stored on S3.
     */
    public function startDocumentTextDetection(string $s3Key, ?string $jobTag = null): string
    {
        $result = $this->client->startDocumentTextDetection([
            'DocumentLocation' => [
                'S3Object' => ['Bucket' => $this->bucket, 'Name' => $s3Key],
            ],
            'JobTag' => $jobTag ?? basename($s3Key),
        ]);

        return (string) $result->get('JobId');
    }

    /**
     * Poll results until the job completes; returns all blocks across pages.
     * @return array<int, array>
     */
    public function waitAndFetchTextDetection(string $jobId, int $sleepSeconds = 5, int $maxWaitSeconds = 1800): array
    {
        $elapsed = 0;
        do {
            $statusResp = $this->client->getDocumentTextDetection([
                'JobId' => $jobId,
                'MaxResults' => 1000,
            ]);

            $status = (string) $statusResp->get('JobStatus');
            if ($status === 'SUCCEEDED') {
                // gather all pages with pagination
                $blocks = [];
                $nextToken = null;
                do {
                    $pageResp = $this->client->getDocumentTextDetection([
                        'JobId' => $jobId,
                        'MaxResults' => 1000,
                        'NextToken' => $nextToken,
                    ]);

                    $blocks = array_merge($blocks, $pageResp->get('Blocks') ?? []);
                    $nextToken = $pageResp->get('NextToken');
                } while ($nextToken);

                return $blocks;
            }

            if ($status === 'FAILED' || $status === 'PARTIAL_SUCCESS') {
                throw new \RuntimeException("Textract job {$jobId} status: {$status}");
            }

            sleep($sleepSeconds);
            $elapsed += $sleepSeconds;

        } while ($elapsed < $maxWaitSeconds);

        throw new \RuntimeException("Textract job {$jobId} timed out after {$maxWaitSeconds}s.");
    }

    /**
     * Group LINE blocks by page number.
     * @return array<int, array<int, array{text:string,left:float,top:float,width:float,height:float}>>
     */
    public function collectLinesByPage(array $blocks): array
    {
        $pages = [];
        foreach ($blocks as $b) {
            if (($b['BlockType'] ?? '') === 'LINE') {
                $page = (int)($b['Page'] ?? 1);
                $text = (string)($b['Text'] ?? '');
                $bb = $b['Geometry']['BoundingBox'] ?? null;
                if (!$bb || $text === '') continue;

                $pages[$page][] = [
                    'text' => $text,
                    'left' => (float)$bb['Left'],
                    'top' => (float)$bb['Top'],
                    'width' => (float)$bb['Width'],
                    'height' => (float)$bb['Height'],
                ];
            }
        }
        ksort($pages);
        return $pages;
    }
}

