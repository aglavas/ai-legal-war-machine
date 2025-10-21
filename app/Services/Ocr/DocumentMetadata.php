<?php

namespace App\Services\Ocr;

/**
 * DTO for document metadata extracted from OCR analysis.
 * Contains statistics, quality metrics, and content features.
 */
final class DocumentMetadata
{
    public function __construct(
        // Document Statistics
        public int $pageCount = 0,
        public int $totalLines = 0,
        public int $totalWords = 0,
        public int $totalCharacters = 0,

        // OCR Quality Metrics
        public float $averageConfidence = 0.0,
        public float $minimumConfidence = 0.0,
        public float $maximumConfidence = 0.0,
        public int $lowConfidenceLineCount = 0, // Lines with confidence < 0.8
        public array $lowConfidencePages = [], // Page numbers with avg confidence < 0.8

        // Content Features
        public int $signatureCount = 0,
        public int $headerCount = 0,
        public int $boldLineCount = 0,
        public float $boldTextPercentage = 0.0,
        public array $emptyPages = [], // Page numbers with no lines

        // Per-page Statistics
        public array $pageStats = [], // Array of per-page metadata

        // Processing Metadata
        public ?string $driveFileId = null,
        public ?string $driveFileName = null,
        public ?string $processingTimestamp = null,
    ) {}

    /**
     * Convert metadata to array for JSON serialization.
     */
    public function toArray(): array
    {
        return [
            'document_statistics' => [
                'page_count' => $this->pageCount,
                'total_lines' => $this->totalLines,
                'total_words' => $this->totalWords,
                'total_characters' => $this->totalCharacters,
            ],
            'ocr_quality' => [
                'average_confidence' => round($this->averageConfidence, 4),
                'minimum_confidence' => round($this->minimumConfidence, 4),
                'maximum_confidence' => round($this->maximumConfidence, 4),
                'low_confidence_line_count' => $this->lowConfidenceLineCount,
                'low_confidence_pages' => $this->lowConfidencePages,
            ],
            'content_features' => [
                'signature_count' => $this->signatureCount,
                'header_count' => $this->headerCount,
                'bold_line_count' => $this->boldLineCount,
                'bold_text_percentage' => round($this->boldTextPercentage, 2),
                'empty_pages' => $this->emptyPages,
            ],
            'page_statistics' => $this->pageStats,
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
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}
