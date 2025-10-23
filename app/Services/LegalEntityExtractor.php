<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Extracts legal entities (laws, articles, cases) from user queries
 * Uses pattern matching and NLP to identify Croatian legal references
 */
class LegalEntityExtractor
{
    /**
     * Extract all legal entities from a query
     */
    public function extract(string $query): array
    {
        return [
            'laws' => $this->extractLaws($query),
            'articles' => $this->extractArticles($query),
            'case_numbers' => $this->extractCaseNumbers($query),
            'court_types' => $this->extractCourtTypes($query),
            'legal_terms' => $this->extractLegalTerms($query),
        ];
    }

    /**
     * Check if query contains specific legal references
     */
    public function hasSpecificReferences(string $query): bool
    {
        $entities = $this->extract($query);
        return !empty($entities['laws'])
            || !empty($entities['articles'])
            || !empty($entities['case_numbers']);
    }

    /**
     * Extract Croatian law references
     * Patterns: "Zakon o ...","NN 123/45", "ZOR", "OZ", etc.
     */
    protected function extractLaws(string $query): array
    {
        $laws = [];

        // Pattern 1: "Zakon o ..."
        if (preg_match_all('/Zakon\s+o\s+([^\.,;]+)/iu', $query, $matches)) {
            foreach ($matches[1] as $lawName) {
                $laws[] = [
                    'type' => 'law_name',
                    'value' => 'Zakon o ' . trim($lawName),
                    'name' => trim($lawName),
                ];
            }
        }

        // Pattern 2: "NN 123/45" (Narodne Novine - Official Gazette)
        if (preg_match_all('/NN\s*(\d+)\/(\d+)/i', $query, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $laws[] = [
                    'type' => 'nn_reference',
                    'value' => $match[0],
                    'number' => $match[1],
                    'year' => $match[2],
                ];
            }
        }

        // Pattern 3: Common law abbreviations
        $abbreviations = [
            'ZOR' => 'Zakon o obveznim odnosima',
            'OZ' => 'Obvezno pravo / Opći zakon',
            'ZKP' => 'Zakon o kaznenom postupku',
            'KZ' => 'Kazneni zakon',
            'OPZ' => 'Opći porezni zakon',
            'ZOR' => 'Zakon o radu',
            'ZTR' => 'Zakon o trgovačkim društvima',
            'ZZP' => 'Zakon o zaštiti potrošača',
        ];

        foreach ($abbreviations as $abbr => $full) {
            if (preg_match('/\b' . preg_quote($abbr, '/') . '\b/i', $query)) {
                $laws[] = [
                    'type' => 'abbreviation',
                    'value' => $abbr,
                    'full_name' => $full,
                ];
            }
        }

        return $laws;
    }

    /**
     * Extract article references
     * Patterns: "članak 123", "čl. 45", "stavak 2", "st. 3"
     */
    protected function extractArticles(string $query): array
    {
        $articles = [];

        // Pattern 1: "članak 123" or "čl. 123"
        if (preg_match_all('/(?:članak|čl\.?)\s*(\d+)/iu', $query, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $articles[] = [
                    'type' => 'article',
                    'number' => (int)$match[1],
                    'value' => $match[0],
                ];
            }
        }

        // Pattern 2: "stavak 2" or "st. 2"
        if (preg_match_all('/(?:stavak|st\.?)\s*(\d+)/iu', $query, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $articles[] = [
                    'type' => 'paragraph',
                    'number' => (int)$match[1],
                    'value' => $match[0],
                ];
            }
        }

        // Pattern 3: "točka 3" or "t. 3"
        if (preg_match_all('/(?:točka|t\.?)\s*(\d+)/iu', $query, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $articles[] = [
                    'type' => 'point',
                    'number' => (int)$match[1],
                    'value' => $match[0],
                ];
            }
        }

        return $articles;
    }

    /**
     * Extract case numbers
     * Patterns: "P-123/2023", "K-456/22", etc.
     */
    protected function extractCaseNumbers(string $query): array
    {
        $cases = [];

        // Pattern: "P-123/2023" or similar
        if (preg_match_all('/([A-Z]{1,3})-?(\d+)\/(\d{2,4})/i', $query, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $cases[] = [
                    'full' => $match[0],
                    'prefix' => strtoupper($match[1]),
                    'number' => $match[2],
                    'year' => $match[3],
                ];
            }
        }

        return $cases;
    }

    /**
     * Extract court type mentions
     */
    protected function extractCourtTypes(string $query): array
    {
        $courts = [];

        $courtTypes = [
            'Vrhovni sud' => 'supreme_court',
            'Visoki trgovački sud' => 'high_commercial_court',
            'Upravni sud' => 'administrative_court',
            'Županijski sud' => 'county_court',
            'Općinski sud' => 'municipal_court',
            'Trgovački sud' => 'commercial_court',
            'Prekršajni sud' => 'misdemeanor_court',
        ];

        foreach ($courtTypes as $name => $type) {
            if (preg_match('/\b' . preg_quote($name, '/') . '\b/iu', $query)) {
                $courts[] = [
                    'name' => $name,
                    'type' => $type,
                ];
            }
        }

        return $courts;
    }

    /**
     * Extract legal terminology
     */
    protected function extractLegalTerms(string $query): array
    {
        $terms = [];

        $legalTerms = [
            // Contract law
            'ugovor' => 'contract',
            'obveza' => 'obligation',
            'naknada štete' => 'damages',
            'raskid' => 'termination',

            // Criminal law
            'kazna' => 'punishment',
            'krivnja' => 'guilt',
            'optužba' => 'indictment',

            // Procedure
            'tužba' => 'lawsuit',
            'žalba' => 'appeal',
            'presuda' => 'judgment',
            'rješenje' => 'decision',

            // Labor law
            'radni odnos' => 'employment',
            'otkaz' => 'dismissal',
            'plaća' => 'salary',

            // Property law
            'vlasništvo' => 'ownership',
            'posjed' => 'possession',
            'uknjižba' => 'land_registration',
        ];

        $lowerQuery = mb_strtolower($query);

        foreach ($legalTerms as $croatian => $english) {
            if (mb_strpos($lowerQuery, $croatian) !== false) {
                $terms[] = [
                    'croatian' => $croatian,
                    'english' => $english,
                ];
            }
        }

        return $terms;
    }

    /**
     * Extract keywords for search optimization
     */
    public function extractKeywords(string $query): array
    {
        // Remove common Croatian stop words
        $stopWords = [
            'i', 'u', 'o', 'na', 'za', 'je', 'da', 'kako', 'koji', 'koja',
            'koje', 'sam', 'što', 'mi', 'se', 'ali', 'ili', 'to', 'od', 'do',
            'ima', 'biti', 'su', 'bio', 'bila', 'bilo', 'više', 'može', 'mogu',
        ];

        // Tokenize and clean
        $words = preg_split('/\s+/u', mb_strtolower($query));
        $keywords = [];

        foreach ($words as $word) {
            // Remove punctuation
            $word = trim($word, '.,!?;:()[]{}"\'-');

            // Skip if too short or is stop word
            if (mb_strlen($word) < 3 || in_array($word, $stopWords)) {
                continue;
            }

            $keywords[] = $word;
        }

        return array_unique($keywords);
    }
}
