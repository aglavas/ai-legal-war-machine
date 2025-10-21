<?php

namespace App\Services\LegalCitations;

class EcliDetector extends BaseCitationDetector
{
    public function detect(string $text): array
    {
        $pattern = '/\bECLI:HR:[A-ZČĆĐŠŽ]{2,12}:[0-9]{4}:[A-Z0-9.]{3,}\b/u';
        $results = [];

        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[0] as $ecli) {
                $results[] = [
                    'raw' => $ecli,
                    'canonical' => $ecli,
                ];
            }
        }

        return $results;
    }
}