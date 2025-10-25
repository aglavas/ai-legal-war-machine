<?php

namespace App\Services\Ocr;

/**
 * OcrQualityAnalyzer
 *
 * Analyzes OCR output quality from AWS Textract blocks or raw text.
 * Computes confidence scores, coverage metrics, and flags problematic pages.
 */
class OcrQualityAnalyzer
{
    /**
     * Analyze quality from Textract blocks.
     *
     * @param array $blocks Textract response blocks
     * @return array Quality metrics
     */
    public function analyzeFromBlocks(array $blocks): array
    {
        $pageConfidence = [];
        $pageWordCount = [];
        $totalWords = 0;
        $totalConfidence = 0.0;
        $wordsWithConfidence = 0;
        $lowConfidencePages = [];

        foreach ($blocks as $block) {
            $type = $block['BlockType'] ?? '';
            $page = (int) ($block['Page'] ?? 1);

            if (!isset($pageWordCount[$page])) {
                $pageWordCount[$page] = 0;
                $pageConfidence[$page] = [];
            }

            if ($type === 'WORD') {
                $pageWordCount[$page]++;
                $totalWords++;

                $confidence = (float) ($block['Confidence'] ?? 0) / 100.0;
                if ($confidence > 0) {
                    $pageConfidence[$page][] = $confidence;
                    $totalConfidence += $confidence;
                    $wordsWithConfidence++;
                }
            }
        }

        // Calculate overall confidence
        $overallConfidence = $wordsWithConfidence > 0
            ? $totalConfidence / $wordsWithConfidence
            : 0.0;

        // Calculate per-page average confidence and identify low-quality pages
        $lowConfThreshold = 0.80;
        foreach ($pageConfidence as $page => $confidences) {
            if (empty($confidences)) {
                $lowConfidencePages[] = $page;
                continue;
            }

            $avgConf = array_sum($confidences) / count($confidences);
            if ($avgConf < $lowConfThreshold) {
                $lowConfidencePages[] = $page;
            }
        }

        // Calculate coverage (ratio of words with confidence data)
        $coverage = $totalWords > 0
            ? $wordsWithConfidence / $totalWords
            : 0.0;

        return [
            'confidence' => round($overallConfidence, 4),
            'coverage' => round($coverage, 4),
            'total_words' => $totalWords,
            'words_with_confidence' => $wordsWithConfidence,
            'total_pages' => count($pageWordCount),
            'low_confidence_pages' => count($lowConfidencePages),
            'low_confidence_page_numbers' => $lowConfidencePages,
            'page_stats' => $this->buildPageStats($pageConfidence, $pageWordCount),
        ];
    }

    /**
     * Estimate quality from raw text (when Textract blocks unavailable).
     *
     * Uses heuristics:
     * - Character diversity
     * - Word/sentence structure
     * - Presence of garbled characters
     */
    public function estimateFromText(string $text): array
    {
        $text = trim($text);
        $len = mb_strlen($text, 'UTF-8');

        if ($len === 0) {
            return [
                'confidence' => 0.0,
                'coverage' => 0.0,
                'total_words' => 0,
                'estimated' => true,
                'method' => 'text_heuristics',
            ];
        }

        // Count words
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = count($words);

        // Heuristic 1: Character diversity (more diverse = better OCR)
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $uniqueChars = count(array_unique($chars));
        $charDiversity = $len > 0 ? min(1.0, $uniqueChars / ($len * 0.1)) : 0.0;

        // Heuristic 2: Ratio of valid words (basic check)
        $validWords = 0;
        foreach ($words as $word) {
            // Valid word has reasonable length and mostly letters
            $wordLen = mb_strlen($word, 'UTF-8');
            $alphaRatio = $this->alphaRatio($word);
            if ($wordLen >= 2 && $wordLen <= 45 && $alphaRatio > 0.7) {
                $validWords++;
            }
        }
        $validWordRatio = $wordCount > 0 ? $validWords / $wordCount : 0.0;

        // Heuristic 3: Line break ratio (too many breaks = fragmented OCR)
        $lineBreaks = substr_count($text, "\n");
        $avgCharsPerLine = $lineBreaks > 0 ? $len / $lineBreaks : $len;
        $lineQuality = min(1.0, $avgCharsPerLine / 50); // Expect ~50+ chars per line

        // Combined confidence estimate
        $confidence = ($charDiversity * 0.3 + $validWordRatio * 0.5 + $lineQuality * 0.2);

        // Coverage estimate (assume full coverage if text exists)
        $coverage = $len > 100 ? 1.0 : $len / 100.0;

        return [
            'confidence' => round($confidence, 4),
            'coverage' => round($coverage, 4),
            'total_words' => $wordCount,
            'valid_words' => $validWords,
            'character_diversity' => round($charDiversity, 4),
            'valid_word_ratio' => round($validWordRatio, 4),
            'line_quality' => round($lineQuality, 4),
            'estimated' => true,
            'method' => 'text_heuristics',
        ];
    }

    /**
     * Calculate ratio of alphabetic characters in a word.
     */
    protected function alphaRatio(string $word): float
    {
        $len = mb_strlen($word, 'UTF-8');
        if ($len === 0) {
            return 0.0;
        }

        $alphaCount = mb_strlen(preg_replace('/[^a-zA-ZčćžšđČĆŽŠĐ]/u', '', $word), 'UTF-8');
        return $alphaCount / $len;
    }

    /**
     * Build per-page statistics.
     */
    protected function buildPageStats(array $pageConfidence, array $pageWordCount): array
    {
        $stats = [];
        foreach ($pageWordCount as $page => $count) {
            $confidences = $pageConfidence[$page] ?? [];
            $avgConf = !empty($confidences) ? array_sum($confidences) / count($confidences) : 0.0;

            $stats[$page] = [
                'words' => $count,
                'avg_confidence' => round($avgConf, 4),
                'words_with_confidence' => count($confidences),
            ];
        }
        return $stats;
    }
}
