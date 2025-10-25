<?php

namespace App\Services;

use App\Services\Ocr\HrLanguageNormalizer;
use App\Services\Ocr\OcrQualityAnalyzer;
use Illuminate\Support\Facades\Log;

/**
 * CaseIngestPipeline
 *
 * Orchestrates the full case document ingestion flow:
 * - OCR quality validation
 * - Language normalization (Croatian)
 * - Text chunking
 * - Embedding generation with quality gates
 */
class CaseIngestPipeline
{
    public function __construct(
        protected OcrQualityAnalyzer $qualityAnalyzer,
        protected HrLanguageNormalizer $normalizer,
        protected CaseVectorStoreService $vectorStore
    ) {}

    /**
     * Process a case document through the full ingestion pipeline.
     *
     * @param string $caseId ULID of the legal case
     * @param string $docId Document identifier
     * @param string $rawText OCR-extracted text
     * @param array $ocrBlocks Optional Textract blocks for quality analysis
     * @param array $options Configuration options
     * @return array Pipeline result
     */
    public function ingest(
        string $caseId,
        string $docId,
        string $rawText,
        array $ocrBlocks = [],
        array $options = []
    ): array {
        $result = [
            'status' => 'pending',
            'quality_check' => null,
            'normalized' => false,
            'chunked' => false,
            'embedded' => false,
            'needs_review' => false,
            'error' => null,
        ];

        try {
            // Step 1: OCR Quality Analysis
            $qualityResult = $this->analyzeQuality($ocrBlocks, $rawText, $options);
            $result['quality_check'] = $qualityResult;

            $minConfidence = (float) ($options['min_confidence'] ?? 0.82);
            $minCoverage = (float) ($options['min_coverage'] ?? 0.75);

            if ($qualityResult['confidence'] < $minConfidence || $qualityResult['coverage'] < $minCoverage) {
                Log::warning('OCR quality below threshold', [
                    'case_id' => $caseId,
                    'doc_id' => $docId,
                    'confidence' => $qualityResult['confidence'],
                    'coverage' => $qualityResult['coverage'],
                    'thresholds' => compact('minConfidence', 'minCoverage'),
                ]);

                $result['status'] = 'quality_check_failed';
                $result['needs_review'] = true;

                // Block embedding if skip_embedding_on_low_quality is true
                if ($options['skip_embedding_on_low_quality'] ?? true) {
                    Log::info('Skipping embedding due to low OCR quality', [
                        'case_id' => $caseId,
                        'doc_id' => $docId,
                    ]);
                    return $result;
                }
            }

            // Step 2: Language Normalization
            $normalizedText = $this->normalizeText($rawText, $options);
            $result['normalized'] = true;

            // Step 3: Chunking
            $chunkSize = (int) ($options['chunk_size'] ?? 1200);
            $chunkOverlap = (int) ($options['overlap'] ?? 150);
            $chunks = $this->chunkText($normalizedText, $chunkSize, $chunkOverlap);
            $result['chunked'] = true;
            $result['chunk_count'] = count($chunks);

            if (empty($chunks)) {
                Log::warning('No chunks generated from text', [
                    'case_id' => $caseId,
                    'doc_id' => $docId,
                    'text_length' => strlen($rawText),
                ]);
                $result['status'] = 'no_content';
                return $result;
            }

            // Step 4: Prepare documents for embedding
            $docs = [];
            foreach ($chunks as $i => $chunkText) {
                $docs[] = [
                    'content' => $chunkText,
                    'chunk_index' => $i,
                    'metadata' => [
                        'quality_check' => $qualityResult,
                        'normalized' => true,
                        'chunk_size' => $chunkSize,
                        'overlap' => $chunkOverlap,
                    ],
                    'actual' => $options['metadata'] ?? null,
                ];
            }

            // Step 5: Ingest into vector store
            $ingestResult = $this->vectorStore->ingest($caseId, $docId, $docs, $options);
            $result['embedded'] = true;
            $result['ingest_result'] = $ingestResult;
            $result['status'] = 'completed';

            Log::info('Case document ingestion completed', [
                'case_id' => $caseId,
                'doc_id' => $docId,
                'chunks' => count($chunks),
                'inserted' => $ingestResult['inserted'],
                'quality' => $qualityResult['confidence'],
            ]);

        } catch (\Throwable $e) {
            Log::error('Case ingestion pipeline failed', [
                'case_id' => $caseId,
                'doc_id' => $docId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $result['status'] = 'error';
            $result['error'] = $e->getMessage();
            throw $e;
        }

        return $result;
    }

    /**
     * Analyze OCR quality from Textract blocks or raw text.
     */
    protected function analyzeQuality(array $blocks, string $text, array $options): array
    {
        if (!empty($blocks)) {
            return $this->qualityAnalyzer->analyzeFromBlocks($blocks);
        }

        // Fallback: estimate quality from text characteristics
        return $this->qualityAnalyzer->estimateFromText($text);
    }

    /**
     * Normalize text for Croatian language.
     */
    protected function normalizeText(string $text, array $options): string
    {
        $language = $options['language'] ?? 'hr';

        if ($language === 'hr' || $language === 'hr_HR') {
            return $this->normalizer->normalize($text);
        }

        // For other languages, just basic cleanup
        return $this->basicCleanup($text);
    }

    /**
     * Basic text cleanup for non-Croatian languages.
     */
    protected function basicCleanup(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        // Normalize line breaks
        $text = preg_replace('/\r\n|\r/', "\n", $text);
        // Remove excessive blank lines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Chunk text using sliding window with overlap.
     * Smart splitting tries to break at sentence boundaries.
     */
    protected function chunkText(string $text, int $size, int $overlap): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        if ($size <= 0) {
            return [$text];
        }

        $chunks = [];
        $start = 0;
        $len = mb_strlen($text, 'UTF-8');

        while ($start < $len) {
            $end = min($len, $start + $size);
            $chunk = mb_substr($text, $start, $end - $start, 'UTF-8');

            // Try to break at sentence boundary if not at end
            if ($end < $len && $end - $start > 100) {
                $chunk = $this->breakAtSentence($chunk);
            }

            $chunks[] = trim($chunk);

            if ($end >= $len) {
                break;
            }

            // Advance by (size - overlap), ensuring we don't go backwards
            $start = max($start + 1, $end - $overlap);
        }

        return array_filter($chunks, fn($c) => trim($c) !== '');
    }

    /**
     * Try to break chunk at the last sentence boundary.
     */
    protected function breakAtSentence(string $chunk): string
    {
        // Look for sentence endings in the last 20% of the chunk
        $minKeep = (int) (mb_strlen($chunk, 'UTF-8') * 0.8);

        // Croatian and common sentence endings
        $patterns = [
            '/([.!?])\s+(?=[A-ZČĆŽŠĐ])/u',  // Period/exclamation/question followed by capital
            '/([.!?])\n/u',                   // Sentence end at line break
            '/\n\n/u',                        // Paragraph break
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $chunk, $matches, PREG_OFFSET_CAPTURE)) {
                // Find the last match that's after minKeep
                $matches = $matches[0];
                for ($i = count($matches) - 1; $i >= 0; $i--) {
                    if ($matches[$i][1] >= $minKeep) {
                        return mb_substr($chunk, 0, $matches[$i][1] + mb_strlen($matches[$i][0], 'UTF-8'), 'UTF-8');
                    }
                }
            }
        }

        // No good break point found, try word boundary
        if (preg_match('/\s+(?=\S)/u', mb_substr($chunk, $minKeep, null, 'UTF-8'), $matches, PREG_OFFSET_CAPTURE)) {
            return mb_substr($chunk, 0, $minKeep + $matches[0][1], 'UTF-8');
        }

        // Give up, return as-is
        return $chunk;
    }

    /**
     * Check if document needs re-OCR based on quality metrics.
     */
    public function needsReOcr(array $qualityMetrics, array $options = []): bool
    {
        $minConfidence = (float) ($options['min_confidence'] ?? 0.82);
        $minCoverage = (float) ($options['min_coverage'] ?? 0.75);
        $maxLowConfPages = (int) ($options['max_low_confidence_pages'] ?? 3);

        if ($qualityMetrics['confidence'] < $minConfidence) {
            return true;
        }

        if ($qualityMetrics['coverage'] < $minCoverage) {
            return true;
        }

        if (($qualityMetrics['low_confidence_pages'] ?? 0) > $maxLowConfPages) {
            return true;
        }

        return false;
    }
}
