<?php

namespace App\Services;

use App\Services\LegalCitations\CitationDetectorInterface;
use App\Services\LegalCitations\StatuteCitationDetector;
use App\Services\LegalCitations\NarodneNovineDetector;
use App\Services\LegalCitations\CaseNumberDetector;
use App\Services\LegalCitations\EcliDetector;
use App\Services\LegalCitations\DateDetector;
use App\Services\LegalCitations\CourtTypeDetector;
use App\Services\LegalCitations\LegalTermDetector;

/**
 * Croatian Legal Citations Detector
 * Comprehensive detection of Croatian legal references including:
 * - Statutes (laws with articles, paragraphs, items)
 * - Narodne Novine (Official Gazette) references
 * - Case numbers
 * - ECLI identifiers
 * - Dates
 * - Court types
 * - Legal terminology
 */
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
            'courts' => new CourtTypeDetector(),
            'legal_terms' => new LegalTermDetector(),
        ];
    }

    /**
     * Detect all citation types in text
     */
    public function detectAll(string $text): array
    {
        $results = [];

        foreach ($this->detectors as $key => $detector) {
            $results[$key] = $detector->detect($text);
        }

        return $results;
    }

    /**
     * Detect specific citation type
     */
    public function detect(string $type, string $text): array
    {
        if (!isset($this->detectors[$type])) {
            throw new \InvalidArgumentException("Unknown citation type: {$type}");
        }

        return $this->detectors[$type]->detect($text);
    }

    /**
     * Extract all legal entities in a normalized format
     * Compatible with QueryProcessingService expectations
     */
    public function extract(string $query): array
    {
        $detected = $this->detectAll($query);

        return [
            'laws' => $this->normalizeLaws($detected),
            'articles' => $this->normalizeArticles($detected),
            'case_numbers' => $this->normalizeCaseNumbers($detected),
            'court_types' => $detected['courts'] ?? [],
            'legal_terms' => $detected['legal_terms'] ?? [],
            'nn_references' => $detected['nn'] ?? [],
            'dates' => $detected['dates'] ?? [],
            'has_specific_refs' => $this->hasSpecificReferences($detected),
        ];
    }

    /**
     * Check if detected citations contain specific legal references
     */
    public function hasSpecificReferences(?array $detected = null): bool
    {
        if ($detected === null) {
            return false;
        }

        return !empty($detected['statutes'])
            || !empty($detected['nn'])
            || !empty($detected['cases']);
    }

    /**
     * Normalize statute detections to laws format
     */
    protected function normalizeLaws(array $detected): array
    {
        $laws = [];

        // From statute detections
        if (!empty($detected['statutes'])) {
            foreach ($detected['statutes'] as $statute) {
                if (!empty($statute['law'])) {
                    $laws[] = [
                        'type' => 'abbreviation',
                        'value' => $statute['law'],
                        'full_name' => $statute['law'],
                        'abbreviation' => $statute['law'],
                        'canonical' => $statute['canonical'] ?? null,
                    ];
                }
            }
        }

        // From NN references
        if (!empty($detected['nn'])) {
            foreach ($detected['nn'] as $nn) {
                if (!empty($nn['issues'])) {
                    foreach ($nn['issues'] as $issue) {
                        $parts = explode('/', $issue);
                        if (count($parts) === 2) {
                            $laws[] = [
                                'type' => 'nn_reference',
                                'value' => "NN {$issue}",
                                'number' => $parts[0],
                                'year' => $parts[1],
                                'canonical' => "NN:{$issue}",
                            ];
                        }
                    }
                }
            }
        }

        // Deduplicate by canonical
        $seen = [];
        $unique = [];
        foreach ($laws as $law) {
            $key = $law['canonical'] ?? $law['value'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $law;
            }
        }

        return $unique;
    }

    /**
     * Normalize statute detections to articles format
     */
    protected function normalizeArticles(array $detected): array
    {
        $articles = [];

        if (!empty($detected['statutes'])) {
            foreach ($detected['statutes'] as $statute) {
                if (!empty($statute['article'])) {
                    $articles[] = [
                        'type' => 'article',
                        'number' => $statute['article'],
                        'paragraph' => $statute['paragraph'] ?? null,
                        'item' => $statute['item'] ?? null,
                        'alineja' => $statute['alineja'] ?? null,
                        'value' => $statute['canonical'] ?? "čl.{$statute['article']}",
                        'canonical' => $statute['canonical'] ?? null,
                    ];
                }
            }
        }

        return $articles;
    }

    /**
     * Normalize case number detections
     */
    protected function normalizeCaseNumbers(array $detected): array
    {
        if (empty($detected['cases'])) {
            return [];
        }

        return array_map(function ($case) {
            return [
                'full' => $case['raw'] ?? $case['canonical'],
                'prefix' => $case['prefix'] ?? null,
                'number' => $case['number'] ?? null,
                'year' => $case['year'] ?? null,
                'canonical' => $case['canonical'] ?? null,
            ];
        }, $detected['cases']);
    }

    /**
     * Extract keywords for search optimization
     * Remove common Croatian stop words
     */
    public function extractKeywords(string $query): array
    {
        $stopWords = [
            'i', 'u', 'o', 'na', 'za', 'je', 'da', 'kako', 'koji', 'koja',
            'koje', 'sam', 'što', 'mi', 'se', 'ali', 'ili', 'to', 'od', 'do',
            'ima', 'biti', 'su', 'bio', 'bila', 'bilo', 'više', 'može', 'mogu',
            'te', 'su', 'a', 's', 'iz', 'po', 'pri', 'bez', 'kroz', 'prema',
        ];

        // Tokenize and clean
        $words = preg_split('/\s+/u', mb_strtolower($query, 'UTF-8'));
        $keywords = [];

        foreach ($words as $word) {
            // Remove punctuation
            $word = trim($word, '.,!?;:()[]{}"\'-');

            // Skip if too short or is stop word
            if (mb_strlen($word, 'UTF-8') < 3 || in_array($word, $stopWords)) {
                continue;
            }

            $keywords[] = $word;
        }

        return array_unique($keywords);
    }
}