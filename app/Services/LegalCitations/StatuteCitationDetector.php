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
        $p1 = '/\b(?P<law>' . $abbr . ')\s*,?\s*' . 
              '(?:čl(?:\.|anak)?|cl(?:\.|anak)?)\s*' .
              '(?P<article>\d+[a-z]?)\s*' .
              '(?:[-–]\s*(?P<article_end>\d+[a-z]?))?\s*\.?\s*' .
              $this->getSubdivisionPattern() . '/iu';

        // Pattern 2: Article before law (e.g., "članak 110. st. 2 ZPP")
        $p2 = '/\b(?:čl(?:\.|anak)?|cl(?:\.|anak)?)\s*' .
              '(?P<article>\d+[a-z]?)\s*' .
              '(?:[-–]\s*(?P<article_end>\d+[a-z]?))?\s*\.?\s*' .
              $this->getSubdivisionPattern() . '\s*' .
              '(?P<law>' . $abbr . ')\b/iu';

        // Pattern 3: Long-name law + article
        $p3 = '/\b(?P<law_long>' . $longNames . ')\s*,?\s*' .
              '(?:čl(?:\.|anak)?|cl(?:\.|anak)?)\s*' .
              '(?P<article>\d+[a-z]?)\s*' .
              '(?:[-–]\s*(?P<article_end>\d+[a-z]?))?\s*\.?\s*' .
              $this->getSubdivisionPattern() . '/iu';

        return [$p1, $p2, $p3];
    }

    private function getSubdivisionPattern(): string
    {
        return '(?:(?:st(?:\.|avak|av)?)\s*(?P<paragraph>\d+)\.?)?\s*' .
               '(?:(?:toč(?:\.|ka)?|t(?:\.|oč\.?)?)\s*(?P<item>\d+)\.?)?\s*' .
               '(?:(?:al(?:\.|ineja)?)\s*(?P<alineja>\d+)\.?)?';
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

            $paragraph = $this->normInt($match['paragraph'][0] ?? null);
            $item = $this->normInt($match['item'][0] ?? null);
            $alineja = $this->normInt($match['alineja'][0] ?? null);

            $articles = $this->expandArticleRange($article, $articleEnd);

            foreach ($articles as $a) {
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
        if (!$law || !$article) {
            return null;
        }

        $parts = ["{$law}:čl.{$article}"];

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
}