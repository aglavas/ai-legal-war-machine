<?php

namespace App\Services\LegalCitations;

class StatuteCitationDetector extends BaseCitationDetector
{
    private const MAX_RANGE_SIZE = 50;

    public function detect(string $text): array
    {
        $patterns = $this->getPatterns();
        $matches = [];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                $matches = array_merge($matches, $m);
            }
        }

        $results = $this->processMatches($matches, $text);

        return $this->deduplicateByKey($results, 'canonical');
    }

    private function getPatterns(): array
    {
        $abbr = CroatianLawRegistry::getAbbreviationRegex();
        $longNames = CroatianLawRegistry::getLongNameRegex();

        // Pattern 1: Law before article (e.g., "ZPP čl. 110 st. 2 toč. 3")
        $p1 = '/\b(?P<law>' . $abbr . ')\s*,?\s*'
            . '(?:čl(?:\.|anak|anka)?|cl(?:\.|anak|anka)?)\s*'
            . '(?P<article>\d+[a-z]?)\s*'
            . '(?:[-–]\s*(?P<article_end>\d+[a-z]?))?\s*\.?\s*'
            . $this->getSubdivisionPattern() . '/iu';

        // Pattern 2: Article before law abbreviation (e.g., "članak 110. st. 2 ZPP")
        $p2 = '/\b(?:čl(?:\.|anak|anka)?|cl(?:\.|anak|anka)?)\s*'
            . '(?P<article>\d+[a-z]?)\s*'
            . '(?:[-–]\s*(?P<article_end>\d+[a-z]?))?\s*\.?\s*'
            . $this->getSubdivisionPattern() . '\s*'
            . '(?P<law>' . $abbr . ')\b/iu';

        // Pattern 3: Long-name law before article (e.g., "Kaznenog zakona, čl. 331 st. 1")
        $p3 = '/\b(?P<law_long>' . $longNames . ')\s*,?\s*'
            . '(?:čl(?:\.|anak|anka)?|cl(?:\.|anak|anka)?)\s*'
            . '(?P<article>\d+[a-z]?)\s*'
            . '(?:[-–]\s*(?P<article_end>\d+[a-z]?))?\s*\.?\s*'
            . $this->getSubdivisionPattern() . '/iu';

        // Pattern 4: Article before long-name law (e.g., "članak 272. st. 1. Zakona o kaznenom postupku")
        $p4 = '/\b(?:čl(?:\.|anak|anka)?|cl(?:\.|anak|anka)?)\s*'
            . '(?P<article>\d+[a-z]?)\s*'
            . '(?:[-–]\s*(?P<article_end>\d+[a-z]?))?\s*\.?\s*'
            . $this->getSubdivisionPattern() . '\s*'
            . '(?P<law_long>' . $longNames . ')\b/iu';

        // Pattern 5: Article and subdivisions without law (e.g., "čl. 331. st.1.")
        $p5 = '/\b(?:čl(?:\.|anak|anka)?|cl(?:\.|anak|anka)?)\s*'
            . '(?P<article>\d+[a-z]?)\s*\.?\s*'
            . $this->getSubdivisionPattern() . '/iu';

        return [$p1, $p2, $p3, $p4, $p5];
    }

    private function getSubdivisionPattern(): string
    {
        // Support single or list forms like: "st. 1.", "st.1. i st.3.", "st. 1, 2 i 3"; similarly for toč./t.
        $sep = '(?:\s*,\s*|\s*i\s*)';

        $paragraphs = '(?:(?:st(?:\.|avak|av)?)\s*(?P<paragraphs>\d+\.?'
                    . '(?:' . $sep . '(?:st(?:\.|avak|av)?)?\s*\d+\.?)*)\s*)?';

        $items = '(?:(?:toč(?:\.|ka)?|t(?:\.|oč\.?)?)\s*(?P<items>\d+\.?'
               . '(?:' . $sep . '(?:toč(?:\.|ka)?|t(?:\.|oč\.?)?)?\s*\d+\.?)*)\s*)?';

        $alineja = '(?:(?:al(?:\.|ineja)?)\s*(?P<alineja>\d+)\.?\s*)?';

        return $paragraphs . '\s*' . $items . '\s*' . $alineja;
    }

    private function processMatches(array $matches, string $text): array
    {
        $results = [];

        foreach ($matches as $match) {
            $lawRaw = $match['law'][0] ?? ($match['law_long'][0] ?? null);
            $law = CroatianLawRegistry::normalizeLaw($lawRaw);

            $article = isset($match['article'][0])
                ? mb_strtolower(trim($match['article'][0]), 'UTF-8')
                : null;

            $articleEnd = isset($match['article_end'][0])
                ? mb_strtolower(trim($match['article_end'][0]), 'UTF-8')
                : null;

            // Parse potential lists of paragraphs/items
            $paragraphsList = $this->parseNumberList($match['paragraphs'][0] ?? null);
            $itemsList = $this->parseNumberList($match['items'][0] ?? null);

            $alineja = $this->normInt($match['alineja'][0] ?? null);

            $articles = $this->expandArticleRange($article, $articleEnd);

            // If no lists provided, keep null to avoid expanding
            $paragraphsList = !empty($paragraphsList) ? $paragraphsList : [null];
            $itemsList = !empty($itemsList) ? $itemsList : [null];

            foreach ($articles as $a) {
                foreach ($paragraphsList as $paragraph) {
                    foreach ($itemsList as $item) {
                        $results[] = [
                            'raw' => $match[0][0] ?? '',
                            'law' => $law,
                            'article' => $a,
                            'paragraph' => $paragraph,
                            'item' => $item,
                            'alineja' => $alineja,
                            'canonical' => $this->buildCanonical($law, $a, $paragraph, $item, $alineja),
                        ];
                    }
                }
            }
        }

        return $results;
    }

    private function expandArticleRange(?string $article, ?string $articleEnd): array
    {
        if (!$article) {
            return [];
        }

        if (!$articleEnd) {
            return [preg_replace('/\.$/', '', $article)];
        }

        $start = intval($this->normInt($article));
        $end = intval($this->normInt($articleEnd));

        if (!$start || !$end || $end < $start || ($end - $start) > self::MAX_RANGE_SIZE) {
            return [preg_replace('/\.$/', '', $article)];
        }

        $articles = [];
        for ($i = $start; $i <= $end; $i++) {
            $articles[] = (string)$i;
        }

        return $articles;
    }

    private function buildCanonical(?string $law, ?string $article, ?string $paragraph, ?string $item, ?string $alineja): ?string
    {
        if (!$article) {
            return null;
        }

        $parts = [];
        if ($law) {
            $parts[] = "{$law}:čl.{$article}";
        } else {
            $parts[] = "čl.{$article}";
        }

        if ($paragraph) {
            $parts[] = "st.{$paragraph}";
        }
        if ($item) {
            $parts[] = "t.{$item}";
        }
        if ($alineja) {
            $parts[] = "al.{$alineja}";
        }

        return implode(' ', $parts);
    }

    private function parseNumberList(?string $text): array
    {
        if (!$text) {
            return [];
        }
        // Extract all integers, e.g., from "1., 2. i 3" or "st.1. i st.3."
        if (preg_match_all('/\d+/', $text, $m)) {
            // Preserve order and uniqueness
            $seen = [];
            $out = [];
            foreach ($m[0] as $num) {
                $n = ltrim($num, '0');
                $n = $n === '' ? '0' : $n; // keep single zero if any
                if (!isset($seen[$n])) {
                    $seen[$n] = true;
                    $out[] = $n;
                }
            }
            return $out;
        }
        return [];
    }
}
