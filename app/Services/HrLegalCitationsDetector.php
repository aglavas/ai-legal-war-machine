<?php

namespace App\Services;

use App\Services\LegalCitations\CitationDetectorInterface;
use App\Services\LegalCitations\StatuteCitationDetector;
use App\Services\LegalCitations\NarodneNovineDetector;
use App\Services\LegalCitations\CaseNumberDetector;
use App\Services\LegalCitations\EcliDetector;
use App\Services\LegalCitations\DateDetector;

class HrLegalCitationsDetector
{
    private array $detectors;

    public function __construct()
    {
        $this->detectors = [
            'statutes' => new StatuteCitationDetector(),
            'nn' => new NarodneNovineDetector(),
            'cases' => new CaseNumberDetector(),
            'ecli' => new EcliDetector(),
            'dates' => new DateDetector(),
        ];
    }

    public function detectAll(string $text): array
    {
        $results = [];
        
        foreach ($this->detectors as $key => $detector) {
            $results[$key] = $detector->detect($text);
        }
        
        return $results;
    }

    public function detect(string $type, string $text): array
    {
        if (!isset($this->detectors[$type])) {
            throw new \InvalidArgumentException("Unknown citation type: {$type}");
        }

        return $this->detectors[$type]->detect($text);
    }
}