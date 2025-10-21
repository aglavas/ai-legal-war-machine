<?php

namespace App\Console\Commands;

use App\Models\TextractJob;
use App\Services\Ocr\DocumentMetadataExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Standalone command for extracting metadata from Textract results.
 * Can be run independently of the main pipeline.
 *
 * Usage:
 *   php artisan textract:extract-metadata {driveFileId}
 *   php artisan textract:extract-metadata {driveFileId} --output=metadata.json
 *   php artisan textract:extract-metadata {driveFileId} --save-to-job
 */
class TextractExtractMetadata extends Command
{
    protected $signature = 'textract:extract-metadata
                            {driveFileId : The Google Drive file ID}
                            {--output= : Path to save metadata JSON file}
                            {--save-to-job : Save metadata to TextractJob record}
                            {--pretty : Pretty print JSON output}';

    protected $description = 'Extract metadata from Textract OCR results (detachable pipeline step)';

    public function __construct(
        private DocumentMetadataExtractor $extractor
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $driveFileId = $this->argument('driveFileId');
        $outputPath = $this->option('output');
        $saveToJob = $this->option('save-to-job');
        $pretty = $this->option('pretty');

        $this->info("Extracting metadata for Drive file: {$driveFileId}");

        // Find the TextractJob
        $job = TextractJob::where('drive_file_id', $driveFileId)->first();

        if (!$job) {
            $this->error("No TextractJob found for Drive file ID: {$driveFileId}");
            return self::FAILURE;
        }

        // Determine path to JSON results
        $jsonPath = $this->findJsonPath($driveFileId);

        if (!$jsonPath) {
            $this->error("Could not find Textract JSON results for Drive file ID: {$driveFileId}");
            $this->info("Expected locations checked:");
            $this->info("  - storage/app/textract/json/{$driveFileId}.json");
            $this->info("  - S3: textract/json/{$driveFileId}.json");
            return self::FAILURE;
        }

        $this->info("Found JSON results at: {$jsonPath}");

        try {
            // Extract metadata
            $metadata = $this->extractor->extractFromJson(
                $jsonPath,
                $driveFileId,
                $job->drive_file_name
            );

            // Display metadata summary
            $this->displayMetadataSummary($metadata);

            // Save to job if requested
            if ($saveToJob) {
                $job->update(['metadata' => $metadata->toArray()]);
                $this->info("✓ Metadata saved to TextractJob record");
            }

            // Save to file if output path specified
            if ($outputPath) {
                $json = $pretty
                    ? json_encode($metadata->toArray(), JSON_PRETTY_PRINT)
                    : json_encode($metadata->toArray());

                file_put_contents($outputPath, $json);
                $this->info("✓ Metadata saved to: {$outputPath}");
            }

            // Display JSON if no output options specified
            if (!$saveToJob && !$outputPath) {
                $this->line('');
                $this->line('Metadata JSON:');
                $this->line($metadata->toJson());
            }

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("Failed to extract metadata: {$e->getMessage()}");
            return self::FAILURE;
        }
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
            $tempPath = storage_path("app/temp/{$driveFileId}.json");
            $content = Storage::disk('s3')->get($s3Key);
            file_put_contents($tempPath, $content);
            return $tempPath;
        }

        return null;
    }

    /**
     * Display a summary of the extracted metadata.
     */
    private function displayMetadataSummary($metadata): void
    {
        $this->line('');
        $this->info('=== Document Metadata Summary ===');
        $this->line('');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Pages', $metadata->pageCount],
                ['Total Lines', $metadata->totalLines],
                ['Total Words', $metadata->totalWords],
                ['Total Characters', $metadata->totalCharacters],
                ['Signatures', $metadata->signatureCount],
                ['Headers', $metadata->headerCount],
                ['Bold Lines', "{$metadata->boldLineCount} ({$metadata->boldTextPercentage}%)"],
            ]
        );

        $this->line('');
        $this->info('OCR Quality:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Average Confidence', round($metadata->averageConfidence * 100, 2) . '%'],
                ['Min Confidence', round($metadata->minimumConfidence * 100, 2) . '%'],
                ['Max Confidence', round($metadata->maximumConfidence * 100, 2) . '%'],
                ['Low Confidence Lines', $metadata->lowConfidenceLineCount],
                ['Low Confidence Pages', implode(', ', $metadata->lowConfidencePages) ?: 'None'],
            ]
        );

        if (!empty($metadata->emptyPages)) {
            $this->warn("Empty pages detected: " . implode(', ', $metadata->emptyPages));
        }

        $this->line('');
    }
}
