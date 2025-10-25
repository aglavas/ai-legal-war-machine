<?php

namespace App\Services\LegalCitations;

/**
 * Composite citation detector for Croatian legal texts
 * Orchestrates multiple specialized detectors to identify all types of legal citations
 */
class HrLegalCitationsDetector
{
    public function __construct(
        protected StatuteCitationDetector $statuteDetector,
        protected NarodneNovineDetector $nnDetector,
        protected CaseNumberDetector $caseDetector,
        protected EcliDetector $ecliDetector,
        protected DateDetector $dateDetector
    ) {
    }

    /**
     * Detect all types of citations in the given text
     *
     * @param string $text The text to analyze
     * @return array Structured array of all detected citations by type
     */
    public function detectAll(string $text): array
    {
        return [
            'statutes' => $this->statuteDetector->detect($text),
            'narodne_novine' => $this->nnDetector->detect($text),
            'case_numbers' => $this->caseDetector->detect($text),
            'ecli' => $this->ecliDetector->detect($text),
            'dates' => $this->dateDetector->detect($text),
        ];
    }

    /**
     * Extract all canonical citation strings for quick reference
     *
     * @param string $text The text to analyze
     * @return array Array of canonical citation strings
     */
    public function extractCanonicalCitations(string $text): array
    {
        $all = $this->detectAll($text);
        $canonicals = [];

        foreach ($all['statutes'] as $statute) {
            if (isset($statute['canonical'])) {
                $canonicals[] = $statute['canonical'];
            }
        }

        foreach ($all['case_numbers'] as $case) {
            if (isset($case['canonical'])) {
                $canonicals[] = $case['canonical'];
            }
        }

        foreach ($all['ecli'] as $ecli) {
            if (isset($ecli['canonical'])) {
                $canonicals[] = $ecli['canonical'];
            }
        }

        return array_values(array_unique($canonicals));
    }

    /**
     * Get statistics about citations in the text
     *
     * @param string $text The text to analyze
     * @return array Citation statistics
     */
    public function getStatistics(string $text): array
    {
        $all = $this->detectAll($text);

        return [
            'total_citations' => array_sum(array_map('count', $all)),
            'statute_citations' => count($all['statutes']),
            'nn_citations' => count($all['narodne_novine']),
            'case_citations' => count($all['case_numbers']),
            'ecli_citations' => count($all['ecli']),
            'dates_found' => count($all['dates']),
        ];
    }

    /**
     * Extract cited law numbers for database lookups
     *
     * @param string $text The text to analyze
     * @return array Array of law numbers (e.g., ['123/20', '45/2021'])
     */
    public function extractLawNumbers(string $text): array
    {
        $nnResults = $this->nnDetector->detect($text);
        $lawNumbers = [];

        foreach ($nnResults as $result) {
            if (isset($result['issues'])) {
                foreach ($result['issues'] as $issue) {
                    $lawNumbers[] = $issue;
                }
            }
        }

        return array_values(array_unique($lawNumbers));
    }

    /**
     * Extract case identifiers for database lookups
     *
     * @param string $text The text to analyze
     * @return array Array of case identifiers
     */
    public function extractCaseIds(string $text): array
    {
        $caseResults = $this->caseDetector->detect($text);
        $caseIds = [];

        foreach ($caseResults as $result) {
            if (isset($result['canonical'])) {
                $caseIds[] = $result['canonical'];
            }
        }

        return array_values(array_unique($caseIds));
    }

    /**
     * Check if text contains any legal citations
     *
     * @param string $text The text to analyze
     * @return bool True if at least one citation is found
     */
    public function hasCitations(string $text): bool
    {
        $stats = $this->getStatistics($text);
        return $stats['total_citations'] > 0;
    }
}
