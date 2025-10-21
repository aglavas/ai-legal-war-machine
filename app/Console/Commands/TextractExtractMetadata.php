<?php

namespace App\Console\Commands;

use App\Models\TextractJob;
use App\Services\Ocr\LegalMetadataExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Standalone command for extracting legal metadata from Textract results.
 * Can be run independently of the main pipeline.
 *
 * Extracts comprehensive legal metadata including:
 * - Legal citations (statutes, case numbers, ECLI, Narodne Novine)
 * - Legal entities (courts, parties, judges)
 * - Document classification (type, jurisdiction)
 * - Dates and key legal phrases
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

    protected $description = 'Extract legal metadata from Textract OCR results (detachable pipeline step)';

    public function __construct(
        private LegalMetadataExtractor $extractor
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $driveFileId = $this->argument('driveFileId');
        $outputPath = $this->option('output');
        $saveToJob = $this->option('save-to-job');
        $pretty = $this->option('pretty');

        $this->info("Extracting legal metadata for Drive file: {$driveFileId}");

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
            // Extract legal metadata
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
                    ? json_encode($metadata->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    : json_encode($metadata->toArray(), JSON_UNESCAPED_UNICODE);

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

    /**
     * Display a summary of the extracted legal metadata.
     */
    private function displayMetadataSummary($metadata): void
    {
        $this->line('');
        $this->info('=== Legal Document Metadata Summary ===');
        $this->line('');

        // Document Classification
        $this->table(
            ['Property', 'Value'],
            [
                ['Document Type', $metadata->documentType ?? 'unknown'],
                ['Jurisdiction', $metadata->jurisdiction ?? 'unknown'],
                ['Classification Confidence', round($metadata->confidence * 100, 2) . '%'],
            ]
        );

        // Citations
        $this->line('');
        $this->info('Legal Citations:');
        $this->table(
            ['Citation Type', 'Count'],
            [
                ['Statute Citations', count($metadata->statuteCitations)],
                ['Case Number Citations', count($metadata->caseNumberCitations)],
                ['ECLI Citations', count($metadata->ecliCitations)],
                ['Narodne Novine Citations', count($metadata->narodneNovineCitations)],
                ['Total Citations', $metadata->totalCitations],
                ['Unique Laws Referenced', count($metadata->referencedLaws)],
            ]
        );

        // Legal Entities
        $this->line('');
        $this->info('Legal Entities:');
        $this->table(
            ['Entity Type', 'Count'],
            [
                ['Courts', count($metadata->courts)],
                ['Parties', count($metadata->parties)],
                ['Dates', count($metadata->dates)],
            ]
        );

        // Display sample citations
        if (!empty($metadata->statuteCitations)) {
            $this->line('');
            $this->info('Sample Statute Citations:');
            foreach (array_slice($metadata->statuteCitations, 0, 5) as $citation) {
                $this->line('  • ' . ($citation['canonical'] ?? $citation['raw'] ?? 'N/A'));
            }
            if (count($metadata->statuteCitations) > 5) {
                $this->line('  ... and ' . (count($metadata->statuteCitations) - 5) . ' more');
            }
        }

        // Display courts
        if (!empty($metadata->courts)) {
            $this->line('');
            $this->info('Courts Mentioned:');
            foreach (array_slice($metadata->courts, 0, 5) as $court) {
                $this->line('  • ' . ($court['normalized'] ?? $court['raw'] ?? 'N/A'));
            }
            if (count($metadata->courts) > 5) {
                $this->line('  ... and ' . (count($metadata->courts) - 5) . ' more');
            }
        }

        // Display parties
        if (!empty($metadata->parties)) {
            $this->line('');
            $this->info('Parties Mentioned:');
            foreach (array_slice($metadata->parties, 0, 5) as $party) {
                $role = $party['role'] ?? 'party';
                $this->line('  • ' . ($party['name'] ?? 'N/A') . " ({$role})");
            }
            if (count($metadata->parties) > 5) {
                $this->line('  ... and ' . (count($metadata->parties) - 5) . ' more');
            }
        }

        // Document Statistics
        $this->line('');
        $this->info('Document Statistics:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Pages', $metadata->pageCount],
                ['Words', number_format($metadata->wordCount)],
                ['Paragraphs (estimated)', $metadata->paragraphCount],
                ['OCR Quality', round($metadata->averageConfidence * 100, 2) . '%'],
            ]
        );

        $this->line('');
    }
}
