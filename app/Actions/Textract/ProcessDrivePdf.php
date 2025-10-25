<?php

namespace App\Actions\Textract;

use App\Models\TextractJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Pipeline\Pipeline;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsJob;
use App\Pipelines\Textract\EnsureJobStep;
use App\Pipelines\Textract\DownloadDriveFileStep;
use App\Pipelines\Textract\UploadInputToS3Step;
use App\Pipelines\Textract\StartAnalysisStep;
use App\Pipelines\Textract\WaitAndFetchStep;
use App\Pipelines\Textract\SaveResultsStep;
use App\Pipelines\Textract\CollectLinesStep;
use App\Pipelines\Textract\CheckOcrQualityStep;
use App\Pipelines\Textract\CreateMetadataStep;
use App\Pipelines\Textract\ReconstructPdfStep;
use App\Pipelines\Textract\UploadOutputStep;
use App\Pipelines\Textract\PersistReconstructedStep;

/**
 * Action: ProcessDrivePdf
 * Purpose: Orchestrate the end-to-end pipeline for a single Google Drive PDF using a Pipeline.
 */
class ProcessDrivePdf
{
    use AsAction, AsJob;

    /**
     * Main orchestrator for a single file.
     */
    public function handle(string $driveFileId, string $driveFileName): void
    {
        Log::info('ProcessDrivePdf (Action): start', compact('driveFileId', 'driveFileName'));

        $payload = [
            'driveFileId' => $driveFileId,
            'driveFileName' => $driveFileName,
        ];

        try {
            /** @var array $payload */
            $payload = app(Pipeline::class)
                ->send($payload)
                ->through([
                    EnsureJobStep::class,
                    DownloadDriveFileStep::class,
                    UploadInputToS3Step::class,
                    StartAnalysisStep::class,
                    WaitAndFetchStep::class,
                    SaveResultsStep::class,
                    CollectLinesStep::class,
                    CheckOcrQualityStep::class,     // OCR quality analysis
                    CreateMetadataStep::class,      // Legal metadata extraction
                    ReconstructPdfStep::class,
                    UploadOutputStep::class,
                    PersistReconstructedStep::class,
                ])
                ->thenReturn();

            if (isset($payload['job']) && $payload['job'] instanceof TextractJob) {
                $payload['job']->update(['status' => 'succeeded']);
            }

            Log::info('ProcessDrivePdf: succeeded', [
                'driveFileId' => $driveFileId,
                'outKey' => $payload['outKey'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcessDrivePdf: failed', [
                'driveFileId' => $driveFileId,
                'error' => $e->getMessage(),
            ]);
            TextractJob::where('drive_file_id', $driveFileId)->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
            throw $e; // Let job runner record failure if queued
        }
    }
}
