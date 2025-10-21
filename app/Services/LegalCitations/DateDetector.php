<?php

namespace App\Services\LegalCitations;

class DateDetector extends BaseCitationDetector
{
    public function detect(string $text): array
    {
        $pattern = '/\b(?P<d>[0-3]?\d)\.\s*(?P<m>[01]?\d)\.\s*(?P<y>(?:19|20)\d{2})\.?\b/u';
        $results = [];

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $day = str_pad($match['d'], 2, '0', STR_PAD_LEFT);
                $month = str_pad($match['m'], 2, '0', STR_PAD_LEFT);
                $year = $match['y'];

                $results[] = [
                    'raw' => $match[0],
                    'iso' => "{$year}-{$month}-{$day}",
                ];
            }
        }

        return $results;
    }
}