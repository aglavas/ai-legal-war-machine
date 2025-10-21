<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Actions\Textract\ProcessDrivePdf as ProcessDrivePdfAction;
use App\Models\TextractJob;

class ProcessDrivePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $driveFileId, public string $driveFileName) {}

    public function handle(): void
    {
        // Delegate to the Action orchestrator
        ProcessDrivePdfAction::run($this->driveFileId, $this->driveFileName);
    }

    public function failed(\Throwable $e): void
    {
        TextractJob::where('drive_file_id', $this->driveFileId)->update([
            'status' => 'failed',
            'error' => $e->getMessage(),
        ]);
    }
}
