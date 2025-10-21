<?php

namespace App\Services\LegalCitations;

abstract class BaseCitationDetector implements CitationDetectorInterface
{
    protected function normInt(?string $s): ?string
    {
        if ($s === null || $s === '') {
            return null;
        }
        return preg_replace('/\D+/', '', $s);
    }

    protected function normLower(?string $s): ?string
    {
        return $s === null ? null : mb_strtolower($s, 'UTF-8');
    }

    protected function clean(?string $s): ?string
    {
        return $s === null ? null : trim(preg_replace('/\s+/u', ' ', $s));
    }

    protected function deduplicateByKey(array $results, string $key): array
    {
        $seen = [];
        $output = [];

        foreach ($results as $result) {
            $canonical = $result[$key] ?? null;
            
            if (!$canonical || isset($seen[$canonical])) {
                continue;
            }

            $seen[$canonical] = true;
            $output[] = $result;
        }

        return $output;
    }
}