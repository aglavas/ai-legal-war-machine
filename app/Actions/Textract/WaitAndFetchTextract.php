<?php

namespace App\Actions\Textract;

use App\Services\TextractService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Action: WaitAndFetchTextract
 * Purpose: Poll Textract until the analysis completes and return the aggregated blocks.
 */
class WaitAndFetchTextract
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
     * @return array<int, array>
     */
    public function handle(string $jobId): array
    {
        return $this->textract->waitAndFetchDocumentAnalysis($jobId);
    }
}

