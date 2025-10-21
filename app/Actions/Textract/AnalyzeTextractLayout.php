<?php

namespace App\Actions\Textract;

use App\Services\Ocr\TextractLayoutAnalyzer;
use App\Services\Ocr\OcrDocument;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Action: AnalyzeTextractLayout
 * Purpose: Parse Textract JSON blocks into an OcrDocument structure using the new analyzer.
 */
class AnalyzeTextractLayout
{
    use AsAction;

    /**
     * @param string $jsonPath Path to the Textract JSON file
     * @return OcrDocument
     */
    public function handle(string $jsonPath): OcrDocument
    {
        $payload = file_get_contents($jsonPath);

        $analyzer = new TextractLayoutAnalyzer();
        return $analyzer->analyze($payload);
    }
}

