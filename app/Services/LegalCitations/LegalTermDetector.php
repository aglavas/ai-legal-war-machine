<?php

namespace App\Services\LegalCitations;

class LegalTermDetector extends BaseCitationDetector
{
    private const LEGAL_TERMS = [
        // Contract law
        'ugovor' => ['category' => 'contract_law', 'english' => 'contract'],
        'obveza' => ['category' => 'contract_law', 'english' => 'obligation'],
        'naknada štete' => ['category' => 'contract_law', 'english' => 'damages'],
        'odšteta' => ['category' => 'contract_law', 'english' => 'compensation'],
        'raskid ugovora' => ['category' => 'contract_law', 'english' => 'contract_termination'],
        'ugovorna strana' => ['category' => 'contract_law', 'english' => 'contracting_party'],

        // Criminal law
        'kazna' => ['category' => 'criminal_law', 'english' => 'punishment'],
        'krivnja' => ['category' => 'criminal_law', 'english' => 'guilt'],
        'optužba' => ['category' => 'criminal_law', 'english' => 'indictment'],
        'kazneno djelo' => ['category' => 'criminal_law', 'english' => 'criminal_offense'],
        'okrivljenik' => ['category' => 'criminal_law', 'english' => 'defendant'],

        // Civil procedure
        'tužba' => ['category' => 'procedure', 'english' => 'lawsuit'],
        'tužitelj' => ['category' => 'procedure', 'english' => 'plaintiff'],
        'tuženik' => ['category' => 'procedure', 'english' => 'defendant'],
        'žalba' => ['category' => 'procedure', 'english' => 'appeal'],
        'presuda' => ['category' => 'procedure', 'english' => 'judgment'],
        'rješenje' => ['category' => 'procedure', 'english' => 'decision'],
        'pravna moć' => ['category' => 'procedure', 'english' => 'legal_force'],
        'izvršenje' => ['category' => 'procedure', 'english' => 'enforcement'],
        'ovršenik' => ['category' => 'procedure', 'english' => 'debtor'],
        'ovrhovoditelj' => ['category' => 'procedure', 'english' => 'creditor'],

        // Labor law
        'radni odnos' => ['category' => 'labor_law', 'english' => 'employment'],
        'otkaz' => ['category' => 'labor_law', 'english' => 'dismissal'],
        'otpremnina' => ['category' => 'labor_law', 'english' => 'severance_pay'],
        'plaća' => ['category' => 'labor_law', 'english' => 'salary'],
        'poslodavac' => ['category' => 'labor_law', 'english' => 'employer'],
        'zaposlenik' => ['category' => 'labor_law', 'english' => 'employee'],

        // Property law
        'vlasništvo' => ['category' => 'property_law', 'english' => 'ownership'],
        'posjed' => ['category' => 'property_law', 'english' => 'possession'],
        'uknjižba' => ['category' => 'property_law', 'english' => 'land_registration'],
        'nekretnina' => ['category' => 'property_law', 'english' => 'real_estate'],
        'stvarno pravo' => ['category' => 'property_law', 'english' => 'real_right'],

        // Family law
        'razvod braka' => ['category' => 'family_law', 'english' => 'divorce'],
        'uzdržavanje' => ['category' => 'family_law', 'english' => 'maintenance'],
        'roditeljska skrb' => ['category' => 'family_law', 'english' => 'parental_care'],

        // Commercial law
        'trgovačko društvo' => ['category' => 'commercial_law', 'english' => 'company'],
        'dioničar' => ['category' => 'commercial_law', 'english' => 'shareholder'],
        'stečaj' => ['category' => 'commercial_law', 'english' => 'bankruptcy'],
        'likvidacija' => ['category' => 'commercial_law', 'english' => 'liquidation'],
    ];

    public function detect(string $text): array
    {
        $results = [];
        $seen = [];
        $lowerText = mb_strtolower($text, 'UTF-8');

        foreach (self::LEGAL_TERMS as $term => $info) {
            $lowerTerm = mb_strtolower($term, 'UTF-8');

            // Use word boundaries for single words, flexible matching for phrases
            if (mb_strpos($term, ' ') !== false) {
                // Multi-word term - use flexible matching
                $pattern = '/\b' . preg_quote($lowerTerm, '/') . '\b/u';
            } else {
                // Single word - strict word boundary
                $pattern = '/\b' . preg_quote($lowerTerm, '/') . '\b/u';
            }

            if (preg_match_all($pattern, $lowerText, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $canonical = $info['category'] . ':' . $info['english'];

                    // Avoid duplicate entries for the same term
                    if (isset($seen[$canonical])) {
                        continue;
                    }

                    $seen[$canonical] = true;
                    $results[] = [
                        'raw' => $match[0],
                        'term' => $term,
                        'category' => $info['category'],
                        'english' => $info['english'],
                        'canonical' => $canonical,
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Get all terms in a specific category
     */
    public static function getTermsByCategory(string $category): array
    {
        return array_filter(
            self::LEGAL_TERMS,
            fn($info) => $info['category'] === $category
        );
    }

    /**
     * Get all available categories
     */
    public static function getCategories(): array
    {
        return array_unique(array_column(self::LEGAL_TERMS, 'category'));
    }
}
