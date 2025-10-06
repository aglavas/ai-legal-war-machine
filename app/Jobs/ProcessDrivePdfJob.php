<?php

namespace App\Jobs;

use App\Models\TextractJob;
use App\Services\GoogleDriveService;
use App\Services\TextractService;
use App\Services\PdfReconstructor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessDrivePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $driveFileId, public string $driveFileName) {}

    public function handle(GoogleDriveService $drive, TextractService $textract, PdfReconstructor $recon): void
    {
        $job = TextractJob::firstOrCreate(
            ['drive_file_id' => $this->driveFileId],
            ['drive_file_name' => $this->driveFileName, 'status' => 'queued']
        );

        $job->update(['status' => 'uploading']);

        // 1) Download PDF locally
        $localPath = $drive->downloadFile($this->driveFileId);

        // 2) Upload to S3
        $inputPrefix = trim((string) env('S3_INPUT_PREFIX', 'textract/input'), '/');
        $s3Key = $inputPrefix . '/' . $this->driveFileId . '.pdf';
        $textract->uploadToS3($localPath, $s3Key);

        $job->update(['s3_key' => $s3Key, 'status' => 'started']);

        // 3) Start Textract job
        $jobId = $textract->startDocumentTextDetection($s3Key, $this->driveFileName);
        $job->update(['job_id' => $jobId]);

        // 4) Poll until finished
        $blocks = $textract->waitAndFetchTextDetection($jobId);

        // 5) Save raw JSON (optional)
        $jsonPrefix = trim((string) env('S3_JSON_PREFIX', 'textract/json'), '/');
        Storage::disk('s3')->put($jsonPrefix . '/' . $this->driveFileId . '.json', json_encode($blocks, JSON_PRETTY_PRINT));

        // 6) Group LINEs by page
        $linesByPage = $textract->collectLinesByPage($blocks);

        // 7) Build searchable PDF
        $outputPrefix = trim((string) env('S3_OUTPUT_PREFIX', 'textract/output'), '/');
        $targetLocal = storage_path('app/tmp/' . $this->driveFileId . '-searchable.pdf');
        $recon->buildSearchablePdf($localPath, $linesByPage, $targetLocal);

        // 8) Upload result to S3
        $outKey = $outputPrefix . '/' . $this->driveFileId . '-searchable.pdf';
        Storage::disk('s3')->put($outKey, fopen($targetLocal, 'r'), ['visibility' => 'private']);

        // 9) Cleanup tmp files
        @unlink($localPath);
        @unlink($targetLocal);

        $job->update(['status' => 'succeeded']);
    }

    public function failed(\Throwable $e): void
    {
        TextractJob::where('drive_file_id', $this->driveFileId)->update([
            'status' => 'failed',
            'error' => $e->getMessage(),
        ]);
    }
}

