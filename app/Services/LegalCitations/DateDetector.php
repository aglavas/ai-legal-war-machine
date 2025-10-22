<?php

namespace App\Services\LegalCitations;

class DateDetector extends BaseCitationDetector
{
    public function detect(string $text): array
    {
        $pattern = '/\b(?P<d>[0-3]?\d)\.\s*(?P<m>[01]?\d)\.\s*(?P<y>(?:19|20)\d{2})\.?\b/u';
        $results = [];

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                // $match parts with PREG_OFFSET_CAPTURE are arrays: [value, byteOffset]
                $raw = $match[0][0];
                $offset = $match[0][1];
                $rawLen = strlen($raw); // bytes

                $day = str_pad($match['d'][0], 2, '0', STR_PAD_LEFT);
                $month = str_pad($match['m'][0], 2, '0', STR_PAD_LEFT);
                $year = $match['y'][0];

                // Build a small context snippet around the date (multibyte-safe, by bytes)
                $ctxBytes = 40; // bytes on each side
                $startLeft = max(0, $offset - $ctxBytes);
                $leftLen = $offset - $startLeft; // bytes
                $left = $leftLen > 0 ? mb_strcut($text, $startLeft, $leftLen, 'UTF-8') : '';
                $right = mb_strcut($text, $offset + $rawLen, $ctxBytes, 'UTF-8');

                $hasMoreLeft = $startLeft > 0;
                $hasMoreRight = ($offset + $rawLen) < strlen($text);

                $context = ($hasMoreLeft ? '…' : '')
                    . $left
                    . $raw
                    . $right
                    . ($hasMoreRight ? '…' : '');

                $results[] = [
                    'raw' => $raw,
                    'iso' => "{$year}-{$month}-{$day}",
                    'context' => $context,
                ];
            }
        }

        return $results;
    }
}
