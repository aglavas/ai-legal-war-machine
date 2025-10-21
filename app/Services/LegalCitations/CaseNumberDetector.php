<?php

namespace App\Services\LegalCitations;

class CaseNumberDetector extends BaseCitationDetector
{
    private const KNOWN_PREFIXES = [
        'Rev', 'Revr', 'Revt', 'Revd', 'Gž', 'Gžp', 'Gžn', 'Gžr', 
        'Pž', 'Kž', 'Kžm', 'Kžg', 'U-III', 'U-II', 'U-I', 
        'Usž', 'Us', 'UP', 'Pp', 'Pn', 'Pr', 'Gpp', 'Gzp', 
        'Gžzp', 'Psp',
    ];

    public function detect(string $text): array
    {
        $patterns = $this->getPatterns();
        $results = [];
        $seen = [];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $canonical = $this->buildCanonical($match);

                    if (isset($seen[$canonical])) {
                        continue;
                    }

                    $seen[$canonical] = true;
                    $results[] = [
                        'raw' => $match[0],
                        'prefix' => trim($match['prefix']),
                        'number' => ltrim($match['number'], '0'),
                        'year' => $match['year'],
                        'canonical' => $canonical,
                    ];
                }
            }
        }

        return $results;
    }

    private function getPatterns(): array
    {
        $prefixAlt = implode('|', array_map(
            fn($s) => preg_quote($s, '/'), 
            self::KNOWN_PREFIXES
        ));

        // Pattern A: Constitutional court formats (U-III-1234/2019)
        $patternA = '/\b(?P<prefix>U-[IVX]{1,3})-?\s*(?P<number>\d{1,6})\/(?P<year>\d{2,4})\b/u';

        // Pattern B: Known prefixes
        $patternB = '/\b(?P<prefix>' . $prefixAlt . ')\s*-?\s*(?P<number>\d{1,6})\/(?P<year>\d{2,4})\b/u';

        // Pattern C: Fallback generic pattern (higher risk of false positives)
        $patternC = '/\b(?P<prefix>[A-ZČĆĐŠŽ]{1,3})-?\s*(?P<number>\d{1,6})\/(?P<year>\d{2,4})\b/u';

        return [$patternA, $patternB, $patternC];
    }

    private function buildCanonical(array $match): string
    {
        $prefix = trim($match['prefix']);
        $number = ltrim($match['number'], '0');
        $year = $match['year'];

        return "{$prefix} {$number}/{$year}";
    }
}