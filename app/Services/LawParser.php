<?php

namespace App\Services;

use Symfony\Component\DomCrawler\Crawler;

class LawParser
{
    public function splitIntoArticles(string $html): array
    {
        $crawler = new Crawler($html);
        $bodyHtml = $crawler->filter('body')->count() ? $crawler->filter('body')->html() : $html;

        // Normalize spaces (keep it simple, NN markers stay in body, not in the header)
        $normalized = preg_replace('/\xC2\xA0/u', ' ', $bodyHtml); // no-break space
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        // Ensure a break before any "Članak <n>[.<letter>|<letter>] ..." variant
        $normalized = preg_replace(
            '/(Članak|CLANAK)\s+(\d+)(?:\.\s*[a-z]|[a-z])?\s*(?:\)|\.|\()/u',
            "\n$0",
            $normalized
        );

        // Split before each heading; supports: "24.", "24.a", "24. a)", allows "(" right after header
        $splitRegex = '/(?=\s*(?:<[^>]+>\s*)*(Članak|CLANAK)\s+\d+(?:\.\s*[a-z]|[a-z])?\s*(?:\)|\.|\())/u';
        $chunks = preg_split($splitRegex, $normalized, -1, PREG_SPLIT_NO_EMPTY);

        // Header must be at the start of the chunk (ignoring leading tags/whitespace)
        $headerAtStart = '/^\s*(?:<[^>]+>\s*)*(Članak|CLANAK)\s+(\d+)(?:\.\s*([a-z])|([a-z]))?\s*\)?\s*\.?/u';

        $articles = [];
        foreach ($chunks as $chunk) {
            if (!preg_match($headerAtStart, $chunk, $m)) {
                // Preamble or trailing text: append to previous article if any
                if (!empty($articles)) {
                    $articles[array_key_last($articles)]['html'] .= $chunk; // FIX: use '.='
                }
                continue;
            }

            $base   = $m[2];
            $letter = $m[3] ?? $m[4] ?? null;

            // Normalize header text: "24." or "24.a"
            $numberText = $letter ? ($base . '.' . $letter) : ($base . '.');

            // Remove only the first header occurrence; keep "(NN ...)" in the body
            $body = preg_replace($headerAtStart, '', $chunk, 1);

            $articles[] = [
                'number' => $letter ? ($base . $letter) : (string)$base, // e.g. "24a" or "24"
                'heading_chain' => [],
                'html' => '<h3>Članak ' . $numberText . '</h3>' . $body,
            ];
        }

        if (empty($articles)) {
            return [['number' => '1', 'heading_chain' => [], 'html' => $normalized]];
        }

        // Merge lettered articles (e.g., 24a, 24b) into their base (24), preserving headings
        $merged = [];
        foreach ($articles as $art) {
            if (preg_match('/^(\d+)([a-z])$/u', $art['number'], $nm)) {
                $base = $nm[1];
                if (!empty($merged) && $merged[array_key_last($merged)]['number'] === $base) {
                    // Append lettered article content to the base article
                    $merged[array_key_last($merged)]['html'] .= $art['html'];
                    continue;
                }
                // No preceding base: start a new chunk with base number
                $art['number'] = $base;
                $merged[] = $art;
                continue;
            }

            // Pure numeric article starts a new chunk
            $merged[] = $art;
        }

        return $merged;
    }







}
