<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Log;

/**
 * Service for extracting comprehensive metadata from OCR documents.
 * Can be used independently or as part of the Textract pipeline.
 */
class DocumentMetadataExtractor
{
    private const LOW_CONFIDENCE_THRESHOLD = 0.8;

    /**
     * Extract metadata from an OcrDocument.
     *
     * @param OcrDocument $document The OCR document to analyze
     * @param string|null $driveFileId Optional Drive file ID for processing metadata
     * @param string|null $driveFileName Optional Drive file name for processing metadata
     * @return DocumentMetadata
     */
    public function extract(
        OcrDocument $document,
        ?string $driveFileId = null,
        ?string $driveFileName = null
    ): DocumentMetadata {
        Log::info('DocumentMetadataExtractor: starting extraction', [
            'pageCount' => count($document->pages),
            'driveFileId' => $driveFileId,
        ]);

        $metadata = new DocumentMetadata();

        // Set processing metadata
        $metadata->driveFileId = $driveFileId;
        $metadata->driveFileName = $driveFileName;
        $metadata->processingTimestamp = now()->toIso8601String();

        // Initialize tracking variables
        $allConfidences = [];
        $totalWords = 0;
        $totalCharacters = 0;
        $totalLines = 0;
        $lowConfidenceLineCount = 0;
        $lowConfidencePages = [];
        $signatureCount = 0;
        $headerCount = 0;
        $boldLineCount = 0;
        $emptyPages = [];
        $pageStats = [];

        // Process each page
        foreach ($document->pages as $page) {
            $pageNumber = $page->number;
            $pageLineCount = count($page->lines);
            $pageWordCount = 0;
            $pageCharCount = 0;
            $pageConfidences = [];
            $pageBoldLines = 0;
            $pageHeaderCount = 0;
            $pageLowConfidenceLines = 0;

            // Track empty pages
            if ($pageLineCount === 0) {
                $emptyPages[] = $pageNumber;
            }

            // Process lines
            foreach ($page->lines as $line) {
                $totalLines++;
                $lineConfidence = $line->confidence;
                $lineText = $line->text;
                $lineWordCount = str_word_count($lineText);
                $lineCharCount = mb_strlen($lineText);

                // Accumulate statistics
                $totalWords += $lineWordCount;
                $totalCharacters += $lineCharCount;
                $pageWordCount += $lineWordCount;
                $pageCharCount += $lineCharCount;

                // Track confidence
                if ($lineConfidence > 0) {
                    $allConfidences[] = $lineConfidence;
                    $pageConfidences[] = $lineConfidence;
                }

                // Track low confidence
                if ($lineConfidence > 0 && $lineConfidence < self::LOW_CONFIDENCE_THRESHOLD) {
                    $lowConfidenceLineCount++;
                    $pageLowConfidenceLines++;
                }

                // Track headers
                if ($line->isHeader) {
                    $headerCount++;
                    $pageHeaderCount++;
                }

                // Track bold text
                if (str_contains($line->style, 'B')) {
                    $boldLineCount++;
                    $pageBoldLines++;
                }
            }

            // Process signatures
            $pageSignatureCount = count($page->signatures);
            $signatureCount += $pageSignatureCount;

            // Calculate page-level confidence
            $pageAvgConfidence = !empty($pageConfidences)
                ? array_sum($pageConfidences) / count($pageConfidences)
                : 0.0;

            // Track pages with low average confidence
            if ($pageAvgConfidence > 0 && $pageAvgConfidence < self::LOW_CONFIDENCE_THRESHOLD) {
                $lowConfidencePages[] = $pageNumber;
            }

            // Store per-page statistics
            $pageStats[] = [
                'page_number' => $pageNumber,
                'line_count' => $pageLineCount,
                'word_count' => $pageWordCount,
                'character_count' => $pageCharCount,
                'signature_count' => $pageSignatureCount,
                'header_count' => $pageHeaderCount,
                'bold_line_count' => $pageBoldLines,
                'low_confidence_line_count' => $pageLowConfidenceLines,
                'average_confidence' => round($pageAvgConfidence, 4),
            ];
        }

        // Calculate overall confidence metrics
        $avgConfidence = !empty($allConfidences)
            ? array_sum($allConfidences) / count($allConfidences)
            : 0.0;
        $minConfidence = !empty($allConfidences) ? min($allConfidences) : 0.0;
        $maxConfidence = !empty($allConfidences) ? max($allConfidences) : 0.0;

        // Calculate bold text percentage
        $boldTextPercentage = $totalLines > 0
            ? ($boldLineCount / $totalLines) * 100
            : 0.0;

        // Populate metadata object
        $metadata->pageCount = count($document->pages);
        $metadata->totalLines = $totalLines;
        $metadata->totalWords = $totalWords;
        $metadata->totalCharacters = $totalCharacters;
        $metadata->averageConfidence = $avgConfidence;
        $metadata->minimumConfidence = $minConfidence;
        $metadata->maximumConfidence = $maxConfidence;
        $metadata->lowConfidenceLineCount = $lowConfidenceLineCount;
        $metadata->lowConfidencePages = $lowConfidencePages;
        $metadata->signatureCount = $signatureCount;
        $metadata->headerCount = $headerCount;
        $metadata->boldLineCount = $boldLineCount;
        $metadata->boldTextPercentage = $boldTextPercentage;
        $metadata->emptyPages = $emptyPages;
        $metadata->pageStats = $pageStats;

        Log::info('DocumentMetadataExtractor: extraction complete', [
            'pageCount' => $metadata->pageCount,
            'totalLines' => $metadata->totalLines,
            'averageConfidence' => round($metadata->averageConfidence, 4),
        ]);

        return $metadata;
    }

    /**
     * Extract metadata from a JSON file containing Textract results.
     * This allows standalone processing of previously saved results.
     *
     * @param string $jsonPath Path to the Textract JSON results file
     * @param string|null $driveFileId Optional Drive file ID
     * @param string|null $driveFileName Optional Drive file name
     * @return DocumentMetadata
     */
    public function extractFromJson(
        string $jsonPath,
        ?string $driveFileId = null,
        ?string $driveFileName = null
    ): DocumentMetadata {
        if (!file_exists($jsonPath)) {
            throw new \RuntimeException("JSON file not found: {$jsonPath}");
        }

        // Re-analyze the JSON to get OcrDocument
        $ocrDocument = app(\App\Actions\Textract\AnalyzeTextractLayout::class)->handle($jsonPath);

        return $this->extract($ocrDocument, $driveFileId, $driveFileName);
    }
}
