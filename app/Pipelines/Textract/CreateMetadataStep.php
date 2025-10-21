<?php

namespace App\Pipelines\Textract;

use App\Services\Ocr\DocumentMetadataExtractor;
use Closure;

/**
 * Step: CreateMetadataStep
 * Extract and store comprehensive metadata from the OCR document.
 * This step is detachable and can be run independently.
 *
 * Input payload keys: ocrDocument, driveFileId, driveFileName, job (optional)
 * Output payload adds: documentMetadata
 */
class CreateMetadataStep
{
    public function __construct(
        private DocumentMetadataExtractor $extractor
    ) {}

    public function handle(array $payload, Closure $next): mixed
    {
        // Ensure we have the required data
        if (!isset($payload['ocrDocument'])) {
            throw new \RuntimeException('OcrDocument not found in payload. CreateMetadataStep requires CollectLinesStep to run first.');
        }

        // Extract metadata
        $metadata = $this->extractor->extract(
            document: $payload['ocrDocument'],
            driveFileId: $payload['driveFileId'] ?? null,
            driveFileName: $payload['driveFileName'] ?? null
        );

        // Add to payload
        $payload['documentMetadata'] = $metadata;

        // Optionally update job status
        if (isset($payload['job'])) {
            $payload['job']->update([
                'status' => 'metadata_extracted',
                'metadata' => $metadata->toArray(),
            ]);
        }

        return $next($payload);
    }
}
