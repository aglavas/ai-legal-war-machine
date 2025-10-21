<?php

namespace App\Services\Ocr;

use App\Services\HrLegalCitationsDetector;
use App\Services\LegalMetadata\CourtDetector;
use App\Services\LegalMetadata\PartyDetector;
use App\Services\LegalMetadata\DocumentTypeClassifier;
use Illuminate\Support\Facades\Log;

/**
 * Service for extracting comprehensive legal metadata from OCR documents.
 * Analyzes legal content including citations, parties, courts, and document classification.
 */
class LegalMetadataExtractor
{
    private const LOW_CONFIDENCE_THRESHOLD = 0.8;

    public function __construct(
        private HrLegalCitationsDetector $citationsDetector,
        private CourtDetector $courtDetector,
        private PartyDetector $partyDetector,
        private DocumentTypeClassifier $documentClassifier,
    ) {}

    /**
     * Extract legal metadata from an OcrDocument.
     *
     * @param OcrDocument $document The OCR document to analyze
     * @param string|null $driveFileId Optional Drive file ID for processing metadata
     * @param string|null $driveFileName Optional Drive file name for processing metadata
     * @return LegalDocumentMetadata
     */
    public function extract(
        OcrDocument $document,
        ?string $driveFileId = null,
        ?string $driveFileName = null
    ): LegalDocumentMetadata {
        Log::info('LegalMetadataExtractor: starting extraction', [
            'pageCount' => count($document->pages),
            'driveFileId' => $driveFileId,
        ]);

        $metadata = new LegalDocumentMetadata();

        // Set processing metadata
        $metadata->driveFileId = $driveFileId;
        $metadata->driveFileName = $driveFileName;
        $metadata->processingTimestamp = now()->toIso8601String();

        // Extract full text from document
        $fullText = $this->extractFullText($document);
        $wordCount = str_word_count($fullText);

        // Extract legal citations using HrLegalCitationsDetector
        $citations = $this->citationsDetector->detectAll($fullText);

        $metadata->statuteCitations = $citations['statutes'] ?? [];
        $metadata->caseNumberCitations = $citations['cases'] ?? [];
        $metadata->ecliCitations = $citations['ecli'] ?? [];
        $metadata->narodneNovineCitations = $citations['nn'] ?? [];
        $metadata->dates = $citations['dates'] ?? [];

        // Calculate total citations
        $metadata->totalCitations =
            count($metadata->statuteCitations) +
            count($metadata->caseNumberCitations) +
            count($metadata->ecliCitations) +
            count($metadata->narodneNovineCitations);

        // Extract unique referenced laws
        $metadata->referencedLaws = $this->extractUniqueLaws($metadata->statuteCitations);

        // Detect courts
        $metadata->courts = $this->courtDetector->detect($fullText);

        // Detect parties
        $metadata->parties = $this->partyDetector->detect($fullText);

        // Classify document type
        $classification = $this->documentClassifier->classify($fullText);
        $metadata->documentType = $classification['document_type'];
        $metadata->jurisdiction = $classification['jurisdiction'];
        $metadata->confidence = $classification['confidence'];

        // Extract key legal phrases
        $metadata->keyPhrases = $this->extractKeyPhrases($fullText);

        // Calculate document statistics
        $metadata->pageCount = count($document->pages);
        $metadata->wordCount = $wordCount;
        $metadata->paragraphCount = $this->countParagraphs($document);

        // Calculate OCR quality metrics (lightweight)
        $qualityMetrics = $this->calculateOcrQuality($document);
        $metadata->averageConfidence = $qualityMetrics['average'];
        $metadata->lowConfidencePageCount = $qualityMetrics['low_confidence_pages'];

        Log::info('LegalMetadataExtractor: extraction complete', [
            'driveFileId' => $driveFileId,
            'documentType' => $metadata->documentType,
            'totalCitations' => $metadata->totalCitations,
            'courts' => count($metadata->courts),
            'parties' => count($metadata->parties),
        ]);

        return $metadata;
    }

    /**
     * Extract metadata from a JSON file containing Textract results.
     *
     * @param string $jsonPath Path to the Textract JSON results file
     * @param string|null $driveFileId Optional Drive file ID
     * @param string|null $driveFileName Optional Drive file name
     * @return LegalDocumentMetadata
     */
    public function extractFromJson(
        string $jsonPath,
        ?string $driveFileId = null,
        ?string $driveFileName = null
    ): LegalDocumentMetadata {
        if (!file_exists($jsonPath)) {
            throw new \RuntimeException("JSON file not found: {$jsonPath}");
        }

        // Re-analyze the JSON to get OcrDocument
        $ocrDocument = app(\App\Actions\Textract\AnalyzeTextractLayout::class)->handle($jsonPath);

        return $this->extract($ocrDocument, $driveFileId, $driveFileName);
    }

    /**
     * Extract full text from OcrDocument.
     */
    private function extractFullText(OcrDocument $document): string
    {
        $text = [];

        foreach ($document->pages as $page) {
            foreach ($page->lines as $line) {
                $text[] = $line->text;
            }
        }

        return implode("\n", $text);
    }

    /**
     * Extract unique laws from statute citations.
     */
    private function extractUniqueLaws(array $statuteCitations): array
    {
        $laws = [];

        foreach ($statuteCitations as $citation) {
            $law = $citation['law'] ?? null;
            if ($law && !in_array($law, $laws)) {
                $laws[] = $law;
            }
        }

        sort($laws);
        return $laws;
    }

    /**
     * Extract key legal phrases from text.
     * These are important terms that appear in legal documents.
     */
    private function extractKeyPhrases(string $text): array
    {
        $keyPhrases = [
            'in the name of the republic' => 'u ime republike hrvatske',
            'in the name of the people' => 'u ime naroda',
            'hereby decides' => 'odlučuje se',
            'hereby orders' => 'nalaže se',
            'legal remedy' => 'pravni lijek',
            'final decision' => 'pravomoćna odluka',
            'enforcement' => 'izvršenje',
            'appeal' => 'žalba',
            'revision' => 'revizija',
            'cassation' => 'kasacija',
        ];

        $found = [];
        $textLower = mb_strtolower($text, 'UTF-8');

        foreach ($keyPhrases as $key => $phrase) {
            if (str_contains($textLower, mb_strtolower($phrase, 'UTF-8'))) {
                $found[] = [
                    'key' => $key,
                    'phrase' => $phrase,
                ];
            }
        }

        return $found;
    }

    /**
     * Count paragraphs (rough estimate based on page structure).
     */
    private function countParagraphs(OcrDocument $document): int
    {
        $paragraphCount = 0;

        foreach ($document->pages as $page) {
            // Estimate paragraphs as groups of lines with similar indentation
            // For simplicity, count lines as potential paragraph markers
            $paragraphCount += max(1, intval(count($page->lines) / 3));
        }

        return $paragraphCount;
    }

    /**
     * Calculate OCR quality metrics.
     */
    private function calculateOcrQuality(OcrDocument $document): array
    {
        $allConfidences = [];
        $lowConfidencePages = 0;

        foreach ($document->pages as $page) {
            $pageConfidences = [];

            foreach ($page->lines as $line) {
                if ($line->confidence > 0) {
                    $allConfidences[] = $line->confidence;
                    $pageConfidences[] = $line->confidence;
                }
            }

            // Check if page has low average confidence
            if (!empty($pageConfidences)) {
                $pageAvg = array_sum($pageConfidences) / count($pageConfidences);
                if ($pageAvg < self::LOW_CONFIDENCE_THRESHOLD) {
                    $lowConfidencePages++;
                }
            }
        }

        $average = !empty($allConfidences)
            ? array_sum($allConfidences) / count($allConfidences)
            : 0.0;

        return [
            'average' => $average,
            'low_confidence_pages' => $lowConfidencePages,
        ];
    }
}
