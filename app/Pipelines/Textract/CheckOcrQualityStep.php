<?php

namespace App\Pipelines\Textract;

use App\Models\TextractJob;
use App\Services\Ocr\OcrQualityAnalyzer;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * CheckOcrQualityStep
 *
 * Analyzes OCR quality from Textract blocks and determines if document needs review or re-OCR.
 * Adds quality metrics to the payload for downstream steps.
 */
class CheckOcrQualityStep
{
    public function __construct(
        private OcrQualityAnalyzer $qualityAnalyzer
    ) {}

    public function handle(array $payload, Closure $next): mixed
    {
        $blocks = $payload['blocks'] ?? [];
        $driveFileId = (string) $payload['driveFileId'];
        $driveFileName = (string) $payload['driveFileName'];

        Log::info('CheckOcrQuality: analyzing quality', [
            'driveFileId' => $driveFileId,
            'blocksCount' => count($blocks),
        ]);

        // Analyze quality from blocks
        $qualityMetrics = $this->qualityAnalyzer->analyzeFromBlocks($blocks);

        Log::info('CheckOcrQuality: analysis complete', [
            'driveFileId' => $driveFileId,
            'confidence' => $qualityMetrics['confidence'],
            'coverage' => $qualityMetrics['coverage'],
            'lowConfidencePages' => $qualityMetrics['low_confidence_pages'],
        ]);

        // Add to payload
        $payload['qualityMetrics'] = $qualityMetrics;

        // Determine if needs review
        $minConfidence = (float) config('vizra-adk.ocr.min_confidence', 0.82);
        $minCoverage = (float) config('vizra-adk.ocr.min_coverage', 0.75);
        $maxLowConfPages = (int) config('vizra-adk.ocr.max_low_confidence_pages', 3);

        $needsReview = false;
        $reviewReasons = [];

        if ($qualityMetrics['confidence'] < $minConfidence) {
            $needsReview = true;
            $reviewReasons[] = sprintf(
                'Low overall confidence: %.2f%% (threshold: %.2f%%)',
                $qualityMetrics['confidence'] * 100,
                $minConfidence * 100
            );
        }

        if ($qualityMetrics['coverage'] < $minCoverage) {
            $needsReview = true;
            $reviewReasons[] = sprintf(
                'Low coverage: %.2f%% (threshold: %.2f%%)',
                $qualityMetrics['coverage'] * 100,
                $minCoverage * 100
            );
        }

        if ($qualityMetrics['low_confidence_pages'] > $maxLowConfPages) {
            $needsReview = true;
            $reviewReasons[] = sprintf(
                'Too many low-confidence pages: %d (threshold: %d)',
                $qualityMetrics['low_confidence_pages'],
                $maxLowConfPages
            );
        }

        $payload['needsReview'] = $needsReview;
        $payload['reviewReasons'] = $reviewReasons;

        // Update TextractJob with quality metadata
        if (isset($payload['job']) && $payload['job'] instanceof TextractJob) {
            $currentMetadata = $payload['job']->metadata ?? [];
            $currentMetadata['ocrQuality'] = $qualityMetrics;
            $currentMetadata['needsReview'] = $needsReview;
            $currentMetadata['reviewReasons'] = $reviewReasons;

            $payload['job']->update(['metadata' => $currentMetadata]);
        }

        if ($needsReview) {
            Log::warning('CheckOcrQuality: document needs review', [
                'driveFileId' => $driveFileId,
                'reasons' => $reviewReasons,
            ]);
        }

        return $next($payload);
    }
}
