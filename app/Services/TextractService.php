<?php

namespace App\Services;

use Aws\Textract\TextractClient;
use Illuminate\Support\Facades\Log;
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

    /**
     * Sanitize a JobTag for AWS Textract async APIs.
     * Allowed characters generally include A-Z a-z 0-9 and _-+=.@:/
     * We also enforce a conservative max length of 64 characters.
     */
    private function sanitizeJobTag(string $tag): string
    {
        // Replace disallowed characters with '-'
        $sanitized = preg_replace('/[^A-Za-z0-9_\-+=.@:\/]/', '-', $tag ?? '');
        // Collapse multiple dashes
        $sanitized = preg_replace('/-+/', '-', (string) $sanitized);
        // Trim dashes
        $sanitized = trim((string) $sanitized, '-');
        // Enforce max length (conservative 64 chars)
        $max = 64;
        if (strlen($sanitized) > $max) {
            $sanitized = substr($sanitized, 0, $max);
        }
        // Fallback if empty
        if ($sanitized === '' || $sanitized === null) {
            $sanitized = 'job-' . substr(sha1((string) microtime(true)), 0, 12);
        }
        return $sanitized;
    }

    /**
     * @return array
     */
    public function getFromS3(): array
    {
        $disk = Storage::disk('s3');
        return $disk->allFiles();
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
        $originalTag = $jobTag ?? basename($s3Key);
        $safeTag = $this->sanitizeJobTag($originalTag);
        Log::info('Textract: starting text detection', [
            's3Key' => $s3Key,
            'bucket' => $this->bucket,
            'jobTagOriginal' => $originalTag,
            'jobTag' => $safeTag,
        ]);

        $result = $this->client->startDocumentTextDetection([
            'DocumentLocation' => [
                'S3Object' => ['Bucket' => $this->bucket, 'Name' => $s3Key],
            ],
            'JobTag' => $safeTag,
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
                    $payload = [
                        'JobId' => $jobId,
                        'MaxResults' => 1000,
                    ];

                    if (!empty($nextToken)) {
                        $payload['NextToken'] = $nextToken;
                    }

                    $pageResp = $this->client->getDocumentTextDetection($payload);

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

    // --- New: Full document analysis (layout, forms, tables, signatures) ---

    /**
     * Start async document analysis with feature types like LAYOUT, FORMS, TABLES, SIGNATURES.
     *
     * @param string $s3Key
     * @param string|null $jobTag
     * @param array<int, string> $featureTypes
     */
    public function startDocumentAnalysis(string $s3Key, ?string $jobTag = null, array $featureTypes = ['LAYOUT', 'FORMS', 'TABLES', 'SIGNATURES']): string
    {
        $originalTag = $jobTag ?? basename($s3Key);
        $safeTag = $this->sanitizeJobTag($originalTag);

        // Filter feature types to a known-allowed subset for StartDocumentAnalysis
        $allowed = ['LAYOUT', 'FORMS', 'TABLES', 'SIGNATURES'];
        $featureTypes = array_values(array_intersect($allowed, $featureTypes));
        if (empty($featureTypes)) {
            $featureTypes = ['LAYOUT', 'FORMS', 'TABLES'];
        }

        Log::info('Textract: starting document analysis', [
            's3Key' => $s3Key,
            'featureTypes' => $featureTypes,
            'bucket' => $this->bucket,
            'jobTagOriginal' => $originalTag,
            'jobTag' => $safeTag,
        ]);

        $result = $this->client->startDocumentAnalysis([
            'DocumentLocation' => [
                'S3Object' => ['Bucket' => $this->bucket, 'Name' => $s3Key],
            ],
            'FeatureTypes' => $featureTypes,
            'JobTag' => $safeTag,
        ]);

        $jobId = (string) $result->get('JobId');
        Log::info('Textract: document analysis started', ['jobId' => $jobId]);
        return $jobId;
    }

    /**
     * Wait for document analysis to complete and return all blocks (paginated).
     * @return array<int, array>
     */
    public function waitAndFetchDocumentAnalysis(string $jobId, int $sleepSeconds = 5, int $maxWaitSeconds = 1800): array
    {
        $elapsed = 0;
        do {
            $statusResp = $this->client->getDocumentAnalysis([
                'JobId' => $jobId,
                'MaxResults' => 1000,
            ]);

            $status = (string) $statusResp->get('JobStatus');
            Log::debug('Textract: analysis status', ['jobId' => $jobId, 'status' => $status]);

            if ($status === 'SUCCEEDED') {
                $blocks = [];
                $nextToken = null;
                do {
                    $payload = [
                        'JobId' => $jobId,
                        'MaxResults' => 1000,
                    ];

                    if (!empty($nextToken)) {
                        $payload['NextToken'] = $nextToken;
                    }

                    $pageResp = $this->client->getDocumentAnalysis($payload);
                    $blocks = array_merge($blocks, $pageResp->get('Blocks') ?? []);
                    $nextToken = $pageResp->get('NextToken');
                } while ($nextToken);

                Log::info('Textract: analysis completed', [
                    'jobId' => $jobId,
                    'blocksCount' => count($blocks),
                ]);
                return $blocks;
            }

            if ($status === 'FAILED' || $status === 'PARTIAL_SUCCESS') {
                throw new \RuntimeException("Textract analysis job {$jobId} status: {$status}");
            }

            sleep($sleepSeconds);
            $elapsed += $sleepSeconds;
        } while ($elapsed < $maxWaitSeconds);

        throw new \RuntimeException("Textract analysis job {$jobId} timed out after {$maxWaitSeconds}s.");
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

    /**
     * Save analysis blocks JSON to S3 and a local dedicated copy.
     * @return array{s3JsonKey:string, localJsonRel:string, localJsonAbs:string}
     */
    public function saveResultsToS3AndLocal(string $driveFileId, array $blocks): array
    {
        $s3JsonKey = $this->jsonPrefix . '/' . $driveFileId . '.json';
        Storage::disk('s3')->put($s3JsonKey, json_encode($blocks, JSON_PRETTY_PRINT));

        // Save under the configured 'local' disk (root is storage/app/private)
        $localJsonRel = 'textract/json/' . $driveFileId . '.json';
        Storage::disk('local')->put($localJsonRel, json_encode($blocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        // Compute absolute path based on the disk's root to avoid mismatches
        $localJsonAbs = Storage::disk('local')->path($localJsonRel);

        Log::info('Textract: results saved', [
            's3JsonKey' => $s3JsonKey,
            'localJson' => $localJsonAbs,
        ]);

        return compact('s3JsonKey', 'localJsonRel', 'localJsonAbs');
    }
}
