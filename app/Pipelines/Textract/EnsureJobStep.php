<?php

namespace App\Pipelines\Textract;

use App\Models\TextractJob;
use Closure;

/**
 * Step: EnsureJobStep
 * Ensure a TextractJob exists for the given Drive file and attach it to the payload.
 * Input payload keys: driveFileId, driveFileName
 * Output payload adds: job (TextractJob)
 */
class EnsureJobStep
{
    public function handle(array $payload, Closure $next): mixed
    {
        $driveFileId = (string) $payload['driveFileId'];
        $driveFileName = (string) $payload['driveFileName'];

        $job = TextractJob::firstOrCreate(
            ['drive_file_id' => $driveFileId],
            ['drive_file_name' => $driveFileName, 'status' => 'queued']
        );

        // Enforce that a case is associated with the job before proceeding
        if (!$job->case_id) {
            throw new \RuntimeException('No case selected. Please select a case before processing this job.');
        }

        $payload['job'] = $job;
        $payload['caseId'] = (string) $job->case_id;
        return $next($payload);
    }
}
