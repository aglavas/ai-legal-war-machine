<?php

namespace App\Actions\Textract;

use App\Models\TextractJob;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Action: EnsureTextractJob
 * Purpose: Centralize the logic for creating/reusing a TextractJob record and deciding whether to skip processing.
 */
class EnsureTextractJob
{
    use AsAction;

    /**
     * @return array{shouldProcess: bool, job: ?TextractJob, reason: ?string}
     */
    public function handle(string $driveFileId, string $driveFileName, bool $force = false): array
    {
        $existing = TextractJob::where('drive_file_id', $driveFileId)->first();

        if ($existing && $existing->status === 'succeeded' && !$force) {
            Log::info('EnsureTextractJob: skip (already succeeded)', [
                'driveFileId' => $driveFileId,
                'driveFileName' => $driveFileName,
            ]);
            return [
                'shouldProcess' => false,
                'job' => $existing,
                'reason' => 'already_succeeded',
            ];
        }

        $job = TextractJob::firstOrCreate(
            ['drive_file_id' => $driveFileId],
            ['drive_file_name' => $driveFileName, 'status' => 'queued']
        );

        if ($job->wasRecentlyCreated) {
            Log::info('EnsureTextractJob: created job', [
                'driveFileId' => $driveFileId,
                'driveFileName' => $driveFileName,
            ]);
        } else {
            Log::info('EnsureTextractJob: job exists, continuing', [
                'driveFileId' => $driveFileId,
                'driveFileName' => $driveFileName,
                'status' => $job->status,
            ]);
        }

        return [
            'shouldProcess' => true,
            'job' => $job,
            'reason' => null,
        ];
    }
}

