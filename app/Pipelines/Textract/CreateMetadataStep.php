<?php

namespace App\Pipelines\Textract;

use App\Services\Ocr\LegalMetadataExtractor;
use Closure;

/**
 * Step: CreateMetadataStep
 * Extract and store comprehensive legal metadata from the OCR document.
 * This step is detachable and can be run independently.
 *
 * Extracts:
 * - Legal citations (statutes, case numbers, ECLI, Narodne Novine)
 * - Legal entities (courts, parties, judges)
 * - Document classification (type, jurisdiction)
 * - Dates and key legal phrases
 *
 * Input payload keys: ocrDocument, driveFileId, driveFileName, job (optional)
 * Output payload adds: legalMetadata
 */
class CreateMetadataStep
{
    public function __construct(
        private LegalMetadataExtractor $extractor
    ) {}

    public function handle(array $payload, Closure $next): mixed
    {
        // Ensure we have the required data
        if (!isset($payload['ocrDocument'])) {
            throw new \RuntimeException('OcrDocument not found in payload. CreateMetadataStep requires CollectLinesStep to run first.');
        }

        // Extract legal metadata
        $metadata = $this->extractor->extract(
            document: $payload['ocrDocument'],
            driveFileId: $payload['driveFileId'] ?? null,
            driveFileName: $payload['driveFileName'] ?? null
        );

        // Add to payload
        $payload['legalMetadata'] = $metadata;

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
