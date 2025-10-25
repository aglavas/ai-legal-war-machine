<?php

namespace App\Console\Commands;

use App\Models\LegalCase;
use App\Models\CaseDocumentUpload;
use App\Services\CaseIngestPipeline;
use App\Services\OcrService;
use App\Services\TextractService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * CasesIngest Command
 *
 * Batch ingest case documents from a directory.
 * Supports both OCR (via Textract) and direct text extraction.
 *
 * Usage:
 *   php artisan cases:ingest --path=/path/to/pdfs --case=<case-id>
 *   php artisan cases:ingest --path=/path/to/pdfs --case=<case-id> --chunk=1200 --overlap=150
 */
class CasesIngest extends Command
{
    protected $signature = 'cases:ingest
                            {--path= : Path to directory containing PDF files}
                            {--case= : ULID of the legal case to associate documents with}
                            {--chunk=1200 : Chunk size for text splitting}
                            {--overlap=150 : Overlap between chunks}
                            {--ocr : Force OCR via AWS Textract (default: try text extraction first)}
                            {--local-ocr : Use local OCR (tesseract) instead of Textract}
                            {--skip-existing : Skip files that already exist in case}
                            {--dry-run : Show what would be processed without actually processing}';

    protected $description = 'Batch ingest case documents from a directory';

    protected CaseIngestPipeline $pipeline;
    protected OcrService $ocrService;
    protected TextractService $textractService;

    protected array $stats = [
        'total' => 0,
        'processed' => 0,
        'skipped' => 0,
        'failed' => 0,
        'needs_review' => 0,
    ];

    public function handle(
        CaseIngestPipeline $pipeline,
        OcrService $ocrService,
        TextractService $textractService
    ): int {
        $this->pipeline = $pipeline;
        $this->ocrService = $ocrService;
        $this->textractService = $textractService;

        // Validate inputs
        $path = $this->option('path');
        $caseId = $this->option('case');

        if (!$path) {
            $this->error('--path is required');
            return 1;
        }

        if (!$caseId) {
            $this->error('--case is required');
            return 1;
        }

        if (!is_dir($path)) {
            $this->error("Directory not found: {$path}");
            return 1;
        }

        // Verify case exists
        $case = LegalCase::find($caseId);
        if (!$case) {
            $this->error("Case not found: {$caseId}");
            return 1;
        }

        $this->info("Ingesting documents for case: {$case->case_number} ({$case->title})");
        $this->info("Source directory: {$path}");
        $this->newLine();

        // Find all PDF files
        $files = $this->findPdfFiles($path);
        $this->stats['total'] = count($files);

        if (empty($files)) {
            $this->warn('No PDF files found in directory');
            return 0;
        }

        $this->info("Found {$this->stats['total']} PDF file(s)");
        $this->newLine();

        // Process each file
        $progressBar = $this->output->createProgressBar($this->stats['total']);
        $progressBar->start();

        foreach ($files as $filePath) {
            $this->processFile($filePath, $case);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Print summary
        $this->printSummary();

        return 0;
    }

    protected function findPdfFiles(string $path): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'pdf') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    protected function processFile(string $filePath, LegalCase $case): void
    {
        $fileName = basename($filePath);
        $sha256 = hash_file('sha256', $filePath);

        // Check if already exists
        if ($this->option('skip-existing')) {
            $exists = CaseDocumentUpload::where('case_id', $case->id)
                ->where('sha256', $sha256)
                ->exists();

            if ($exists) {
                $this->stats['skipped']++;
                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->warn("Skipped (already exists): {$fileName}");
                }
                return;
            }
        }

        if ($this->option('dry-run')) {
            $this->stats['processed']++;
            if ($this->option('verbose')) {
                $this->newLine();
                $this->info("Would process: {$fileName}");
            }
            return;
        }

        try {
            // Step 1: Store the file
            $docId = 'doc-' . Str::ulid();
            $localRel = "cases/{$case->id}/{$docId}/" . $fileName;
            Storage::disk('local')->put($localRel, file_get_contents($filePath));
            $localAbs = Storage::disk('local')->path($localRel);

            // Step 2: Create upload record
            $upload = CaseDocumentUpload::create([
                'id' => (string) Str::ulid(),
                'case_id' => $case->id,
                'doc_id' => $docId,
                'disk' => 'local',
                'local_path' => $localRel,
                'original_filename' => $fileName,
                'mime_type' => 'application/pdf',
                'file_size' => filesize($filePath),
                'sha256' => $sha256,
                'status' => 'stored',
                'uploaded_at' => now(),
            ]);

            // Step 3: Extract text
            $text = '';
            $blocks = [];

            if ($this->option('ocr')) {
                // Force Textract OCR
                $this->warn('Textract OCR not implemented in CLI yet. Use local OCR or text extraction.');
                $text = $this->extractTextLocal($localAbs);
            } elseif ($this->option('local-ocr')) {
                // Local OCR
                $text = $this->extractTextLocal($localAbs);
            } else {
                // Try text extraction first
                $text = $this->extractTextLocal($localAbs);
            }

            if (trim($text) === '') {
                $this->stats['failed']++;
                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->error("Failed to extract text: {$fileName}");
                }
                $upload->update(['status' => 'failed', 'error' => 'No text extracted']);
                return;
            }

            // Step 4: Ingest via pipeline
            $result = $this->pipeline->ingest(
                caseId: $case->id,
                docId: $docId,
                rawText: $text,
                ocrBlocks: $blocks,
                options: [
                    'chunk_size' => (int) $this->option('chunk'),
                    'overlap' => (int) $this->option('overlap'),
                    'upload_id' => $upload->id,
                    'language' => 'hr',
                    'metadata' => [
                        'original_path' => $filePath,
                        'cli_ingested' => true,
                        'ingested_at' => now()->toIso8601String(),
                    ],
                ]
            );

            if ($result['status'] === 'completed') {
                $this->stats['processed']++;
                if ($result['needs_review']) {
                    $this->stats['needs_review']++;
                }
                $upload->update(['status' => 'completed']);

                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->info("✓ Processed: {$fileName} ({$result['chunk_count']} chunks)");
                    if ($result['needs_review']) {
                        $this->warn("  ⚠ Needs review (low OCR quality)");
                    }
                }
            } else {
                $this->stats['failed']++;
                $upload->update(['status' => 'failed', 'error' => $result['error'] ?? 'Unknown error']);

                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->error("✗ Failed: {$fileName}");
                }
            }

        } catch (\Throwable $e) {
            $this->stats['failed']++;
            if ($this->option('verbose')) {
                $this->newLine();
                $this->error("Exception processing {$fileName}: " . $e->getMessage());
            }
        }
    }

    protected function extractTextLocal(string $pdfPath): string
    {
        try {
            return $this->ocrService->extractTextFromPdf($pdfPath);
        } catch (\Throwable $e) {
            if ($this->option('verbose')) {
                $this->warn("OCR extraction failed: " . $e->getMessage());
            }
            return '';
        }
    }

    protected function printSummary(): void
    {
        $this->info('═══════════════════════════════════════');
        $this->info('           INGESTION SUMMARY           ');
        $this->info('═══════════════════════════════════════');
        $this->line("Total files:      {$this->stats['total']}");
        $this->line("Processed:        " . $this->formatStat($this->stats['processed'], 'info'));
        $this->line("Skipped:          " . $this->formatStat($this->stats['skipped'], 'comment'));
        $this->line("Failed:           " . $this->formatStat($this->stats['failed'], 'error'));
        $this->line("Needs review:     " . $this->formatStat($this->stats['needs_review'], 'warn'));
        $this->info('═══════════════════════════════════════');
    }

    protected function formatStat(int $count, string $type): string
    {
        $color = match($type) {
            'info' => 'green',
            'comment' => 'yellow',
            'error' => 'red',
            'warn' => 'yellow',
            default => 'white',
        };

        return "<fg={$color}>{$count}</>";
    }
}
