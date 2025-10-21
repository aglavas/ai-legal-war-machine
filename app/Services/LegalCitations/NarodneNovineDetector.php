<?php

namespace App\Services\LegalCitations;

class NarodneNovineDetector extends BaseCitationDetector
{
    public function detect(string $text): array
    {
        $pattern = '/\b(?:Narodne\s+novine|NN)\s*' .
                   '(?:,?\s*(?:br\.?|broj))?\s*' .
                   '(?P<issues>(?:\d{1,3}\/\d{2,4}(?:\s*[-–]\s*\d+)?)' .
                   '(?:\s*,\s*\d{1,3}\/\d{2,4}(?:\s*[-–]\s*\d+)?)*)/iu';

        $results = [];

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $raw = $match[0];
                $issues = $this->parseIssues($match['issues']);

                $results[] = [
                    'raw' => $raw,
                    'issues' => $issues,
                ];
            }
        }

        return $results;
    }

    private function parseIssues(string $issuesString): array
    {
        $parts = preg_split('/\s*,\s*/u', $issuesString);
        
        return array_map(
            fn($s) => preg_replace('/\s+/u', '', $s), 
            $parts
        );
    }
}