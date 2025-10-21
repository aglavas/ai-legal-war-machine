<?php

namespace App\Actions\Textract;

use App\Services\TextractService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Action: SaveAnalysisResults
 * Purpose: Persist Textract blocks to S3 and local storage for audit/debug.
 */
class SaveAnalysisResults
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
     * @param array<int, array> $blocks
     * @return array{s3JsonKey:string, localJsonRel:string, localJsonAbs:string}
     */
    public function handle(string $driveFileId, array $blocks): array
    {
        return $this->textract->saveResultsToS3AndLocal($driveFileId, $blocks);
    }
}

