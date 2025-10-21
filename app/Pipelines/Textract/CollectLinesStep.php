<?php

namespace App\Pipelines\Textract;

use App\Actions\Textract\AnalyzeTextractLayout;
use Closure;

/**
 * Step: CollectLinesStep
 * Convert blocks into OcrDocument structure using new TextractLayoutAnalyzer.
 * Adds: ocrDocument
 */
class CollectLinesStep
{
    public function handle(array $payload, Closure $next): mixed
    {
        // Use the saved JSON path to analyze the layout
        $jsonPath = $payload['resultsMeta']['localJsonAbs'] ?? null;

        if (!$jsonPath || !file_exists($jsonPath)) {
            throw new \RuntimeException('Textract JSON file not found for analysis');
        }

        $payload['ocrDocument'] = AnalyzeTextractLayout::run($jsonPath);
        $payload['job']->update(['status' => 'reconstructing']);
        return $next($payload);
    }
}
