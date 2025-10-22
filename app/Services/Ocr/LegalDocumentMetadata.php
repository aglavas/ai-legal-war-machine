<?php

namespace App\Services\Ocr;

/**
 * DTO for legal document metadata extracted from OCR text content.
 * Contains legal citations, entities, dates, and document classification.
 */
final class LegalDocumentMetadata
{
    public function __construct(
        // Citations
        public array $statuteCitations = [],      // Statute/law citations
        public array $caseNumberCitations = [],   // Case number references
        public array $ecliCitations = [],         // ECLI identifiers
        public array $narodneNovineCitations = [], // Narodne Novine references

        // Dates
        public array $dates = [],                 // All detected dates

        // Legal Entities
        public array $courts = [],                // Mentioned courts
        public array $parties = [],               // Parties (plaintiffs, defendants)
        public array $judges = [],                // Mentioned judges

        // Document Classification
        public ?string $documentType = null,      // e.g., 'judgment', 'motion', 'contract'
        public ?string $jurisdiction = null,      // e.g., 'civil', 'criminal', 'administrative'
        public float $confidence = 0.0,           // Classification confidence

        // Content Analysis
        public int $totalCitations = 0,
        public array $referencedLaws = [],        // Unique laws referenced
        public array $keyPhrases = [],            // Important legal phrases/terms

        // Document Statistics
        public int $pageCount = 0,
        public int $wordCount = 0,
        public int $paragraphCount = 0,

        // Processing Metadata
        public ?string $driveFileId = null,
        public ?string $driveFileName = null,
        public ?string $processingTimestamp = null,

        // OCR Quality (lightweight subset)
        public float $averageConfidence = 0.0,
        public int $lowConfidencePageCount = 0,
    ) {}

    /**
     * Convert metadata to array for JSON serialization.
     */
    public function toArray(): array
    {
        return [
            'citations' => [
                'statutes' => $this->statuteCitations,
                'case_numbers' => $this->caseNumberCitations,
                'ecli' => $this->ecliCitations,
                'narodne_novine' => $this->narodneNovineCitations,
                'total_count' => $this->totalCitations,
                'referenced_laws' => $this->referencedLaws,
            ],
            'dates' => $this->dates,
            'legal_entities' => [
                'courts' => $this->courts,
                'parties' => $this->parties,
                'judges' => $this->judges,
            ],
            'classification' => [
                'document_type' => $this->documentType,
                'jurisdiction' => $this->jurisdiction,
                'confidence' => round($this->confidence, 4),
            ],
            'content_analysis' => [
                'key_phrases' => $this->keyPhrases,
            ],
            'document_statistics' => [
                'page_count' => $this->pageCount,
                'word_count' => $this->wordCount,
                'paragraph_count' => $this->paragraphCount,
            ],
            'ocr_quality' => [
                'average_confidence' => round($this->averageConfidence, 4),
                'low_confidence_page_count' => $this->lowConfidencePageCount,
            ],
            'processing' => [
                'drive_file_id' => $this->driveFileId,
                'drive_file_name' => $this->driveFileName,
                'timestamp' => $this->processingTimestamp,
            ],
        ];
    }

    /**
     * Convert metadata to JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get summary statistics.
     */
    public function getSummary(): array
    {
        return [
            'document_type' => $this->documentType ?? 'unknown',
            'total_citations' => $this->totalCitations,
            'unique_laws' => count($this->referencedLaws),
            'courts_mentioned' => count($this->courts),
            'parties_mentioned' => count($this->parties),
            'dates_found' => count($this->dates),
            'word_count' => $this->wordCount,
            'ocr_confidence' => round($this->averageConfidence * 100, 2) . '%',
        ];
    }
}
