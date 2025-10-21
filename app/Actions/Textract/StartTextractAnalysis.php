<?php

namespace App\Actions\Textract;

use App\Services\TextractService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Action: StartTextractAnalysis
 * Purpose: Start an async Textract document analysis job for the PDF stored in S3.
 */
class StartTextractAnalysis
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
     * @param array<int, string> $featureTypes
     * @return string Job ID
     */
    public function handle(string $s3Key, ?string $jobTag, array $featureTypes): string
    {
        return $this->textract->startDocumentAnalysis($s3Key, $jobTag, $featureTypes);
    }
}

