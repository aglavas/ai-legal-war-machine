<?php

namespace App\Actions\Textract;

use App\Services\GoogleDriveService;
use App\Services\TextractService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Action: UploadInputToS3
 * Purpose: Upload the original PDF to S3 at the expected Textract input prefix.
 */
class UploadInputToS3
{
    use AsAction;

    /**
     * @var TextractService
     */
    public TextractService $textract;

    /**
     * @param TextractService $textract
     */
    public function __construct(TextractService $textract)
    {
        $this->textract = $textract;
    }

    /**
     * @return string S3 key (path) where the file was uploaded
     */
    public function handle(string $localPath, string $driveFileId): string
    {
        $inputPrefix = trim((string) env('S3_INPUT_PREFIX', 'textract/input'), '/');
        $s3Key = $inputPrefix . '/' . $driveFileId . '.pdf';
        $this->textract->uploadToS3($localPath, $s3Key);
        return $s3Key;
    }
}

