<?php

namespace App\Actions\Textract;

use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Action: UploadOutputToS3
 * Purpose: Upload the reconstructed searchable PDF to S3 output prefix.
 */
class UploadOutputToS3
{
    use AsAction;

    /**
     * @return string S3 key of the uploaded file
     */
    public function handle(string $driveFileId, string $targetLocalPath): string
    {
        $outputPrefix = trim((string) env('S3_OUTPUT_PREFIX', 'textract/output'), '/');
        $outKey = $outputPrefix . '/' . $driveFileId . '-searchable.pdf';
        Storage::disk('s3')->put($outKey, fopen($targetLocalPath, 'r'), ['visibility' => 'private']);
        return $outKey;
    }
}

