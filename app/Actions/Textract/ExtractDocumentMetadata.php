<?php

namespace App\Actions\Textract;

use App\Models\TextractJob;
use App\Services\Ocr\DocumentMetadata;
use App\Services\Ocr\DocumentMetadataExtractor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Action: ExtractDocumentMetadata
 * Standalone action for extracting metadata from Textract OCR results.
 * Can be used independently of the main pipeline or integrated into other workflows.
 *
 * Usage:
 *   $metadata = ExtractDocumentMetadata::run($driveFileId);
 *   ExtractDocumentMetadata::dispatch($driveFileId); // As a job
 */
class ExtractDocumentMetadata
{
    use AsAction;

    public function __construct(
        private DocumentMetadataExtractor $extractor
    ) {}

    /**
     * Extract metadata for a given Drive file.
     *
     * @param string $driveFileId The Google Drive file ID
     * @param bool $saveToJob Whether to save metadata to the TextractJob record
     * @return DocumentMetadata
     * @throws \RuntimeException
     */
    public function handle(string $driveFileId, bool $saveToJob = false): DocumentMetadata
    {
        Log::info('ExtractDocumentMetadata: starting', compact('driveFileId', 'saveToJob'));

        // Find the TextractJob
        $job = TextractJob::where('drive_file_id', $driveFileId)->first();

        if (!$job) {
            throw new \RuntimeException("No TextractJob found for Drive file ID: {$driveFileId}");
        }

        // Find JSON results file
        $jsonPath = $this->findJsonPath($driveFileId);

        if (!$jsonPath) {
            throw new \RuntimeException(
                "Could not find Textract JSON results for Drive file ID: {$driveFileId}. " .
                "Ensure the Textract analysis has been completed."
            );
        }

        // Extract metadata
        $metadata = $this->extractor->extractFromJson(
            $jsonPath,
            $driveFileId,
            $job->drive_file_name
        );

        // Save to job if requested
        if ($saveToJob) {
            $job->update(['metadata' => $metadata->toArray()]);
            Log::info('ExtractDocumentMetadata: saved to job', ['driveFileId' => $driveFileId]);
        }

        Log::info('ExtractDocumentMetadata: completed', [
            'driveFileId' => $driveFileId,
            'pageCount' => $metadata->pageCount,
            'totalLines' => $metadata->totalLines,
        ]);

        return $metadata;
    }

    /**
     * Find the path to the Textract JSON results file.
     */
    private function findJsonPath(string $driveFileId): ?string
    {
        // Try local storage first
        $localPath = storage_path("app/textract/json/{$driveFileId}.json");
        if (file_exists($localPath)) {
            return $localPath;
        }

        // Try to download from S3
        $s3Key = "textract/json/{$driveFileId}.json";
        if (Storage::disk('s3')->exists($s3Key)) {
            // Download to temporary location
            $tempDir = storage_path('app/temp');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $tempPath = "{$tempDir}/{$driveFileId}.json";
            $content = Storage::disk('s3')->get($s3Key);
            file_put_contents($tempPath, $content);
            return $tempPath;
        }

        return null;
    }
}
