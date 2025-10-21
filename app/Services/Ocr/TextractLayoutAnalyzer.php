<?php

declare(strict_types=1);

namespace App\Services\Ocr;

use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * Parses Textract-like blocks (array or { Blocks: [] }) into a layout model.
 * Focuses on LINE blocks for reliable full-text reconstruction.
 */
class TextractLayoutAnalyzer
{
    public function analyze(array|string $textract): OcrDocument
    {
        if (is_string($textract)) {
            $textract = json_decode($textract, true);
        }
        if (!is_array($textract)) {
            throw new InvalidArgumentException('Textract payload must be array or JSON string.');
        }

        $blocks = $textract['Blocks'] ?? $textract;
        if (!is_array($blocks) || empty($blocks)) {
            throw new InvalidArgumentException('Textract payload has no blocks.');
        }

        // Group by page
        $byPage = [];
        foreach ($blocks as $b) {
            $page = (int)($b['Page'] ?? 1);
            $type = (string)($b['BlockType'] ?? '');
            $byPage[$page]['ALL'][] = $b;
            $byPage[$page][$type][] = $b;
        }

        $doc = new OcrDocument();

        foreach ($byPage as $pageNum => $groups) {
            $page = new OcrPage(number: $pageNum);

            $linesRaw = $groups['LINE'] ?? [];
            foreach ($linesRaw as $ln) {
                $text = (string)($ln['Text'] ?? '');
                if ($text === '') {
                    continue;
                }
                $bb = Arr::get($ln, 'Geometry.BoundingBox');
                if (!is_array($bb)) {
                    continue;
                }
                $left   = (float)($bb['Left']   ?? 0);
                $top    = (float)($bb['Top']    ?? 0);
                $width  = (float)($bb['Width']  ?? 0);
                $height = (float)($bb['Height'] ?? 0);

                // Clamp normalized
                $left   = max(0.0, min(1.0, $left));
                $top    = max(0.0, min(1.0, $top));
                $width  = max(0.0, min(1.0, $width));
                $height = max(0.0, min(1.0, $height));

                $conf = (float)($ln['Confidence'] ?? 0.0);

                $page->lines[] = new OcrLine(
                    text: $text,
                    left: $left,
                    top: $top,
                    width: $width,
                    height: $height,
                    confidence: $conf,
                    style: $this->inferStyle($text, $height),
                    isHeader: false,
                    id: $ln['Id'] ?? null
                );
            }

            // Sort lines by reading order
            usort($page->lines, function (OcrLine $a, OcrLine $b) {
                $dy = $a->top <=> $b->top;
                return $dy !== 0 ? $dy : ($a->left <=> $b->left);
            });

            // Signatures (optional)
            foreach (($groups['SIGNATURE'] ?? []) as $sig) {
                $bb = Arr::get($sig, 'Geometry.BoundingBox');
                if (is_array($bb)) {
                    $page->signatures[] = new OcrBox(
                        left: (float)max(0, min(1, $bb['Left']   ?? 0)),
                        top: (float)max(0, min(1, $bb['Top']    ?? 0)),
                        width: (float)max(0, min(1, $bb['Width']  ?? 0)),
                        height:(float)max(0, min(1, $bb['Height'] ?? 0)),
                    );
                }
            }

            $doc->pages[] = $page;
        }

        usort($doc->pages, fn (OcrPage $a, OcrPage $b) => $a->number <=> $b->number);

        if (count($doc->pages) === 0) {
            throw new InvalidArgumentException('No pages found in Textract blocks.');
        }

        return $doc;
    }

    private function inferStyle(string $text, float $normHeight): string
    {
        // Minimal heuristic: all-caps and taller boxes → bold
        $upper = mb_strtoupper($text, 'UTF-8');
        if ($upper === $text && $normHeight >= 0.015) {
            return 'B';
        }
        return '';
    }
}
