<?php

namespace App\Services\LegalMetadata;

/**
 * Detects legal parties (plaintiffs, defendants, etc.) in Croatian legal documents.
 */
class PartyDetector
{
    /**
     * Croatian party role keywords
     */
    private const PARTY_ROLES = [
        'tužitelj' => 'plaintiff',
        'tužiteljica' => 'plaintiff',
        'tuženi' => 'defendant',
        'tužena' => 'defendant',
        'podnositelj' => 'applicant',
        'podnosateljica' => 'applicant',
        'predlagatelj' => 'proposer',
        'predlagateljica' => 'proposer',
        'optuženi' => 'accused',
        'optužena' => 'accused',
        'optuženik' => 'accused',
        'okrivljenik' => 'accused',
        'okrivljenica' => 'accused',
        'žalitelj' => 'appellant',
        'žaliteljica' => 'appellant',
        'protiv' => 'opponent',
        'branitelj' => 'defense_attorney',
        'branitelj' => 'attorney',
        'odvjetnik' => 'attorney',
        'odvjetnica' => 'attorney',
        'punomoćnik' => 'representative',
        'svjedok' => 'witness',
        'svjedokinja' => 'witness',
        'vještak' => 'expert',
        'vještakinja' => 'expert',
    ];

    /**
     * Patterns for extracting party names
     */
    private const NAME_PATTERNS = [
        // Pattern: "tužitelj: NAZIV"
        '/(?P<role>tužitelj|tužiteljica|tuženi|tužena|podnositelj|podnosateljica|predlagatelj|predlagateljica|optuženi|optužena|okrivljenik|okrivljenica|žalitelj|žaliteljica)[\s:,]+(?P<name>[A-ZČĆĐŠŽ][A-ZČĆĐŠŽa-zčćđšž\s\.,d\.o\.o\.-]{3,100}?)(?=\s*[,;\n]|$)/iu',

        // Pattern: "između ... i ..."
        '/između\s+(?P<party1>[A-ZČĆĐŠŽ][A-ZČĆĐŠŽa-zčćđšž\s\.,d\.o\.o\.-]{3,80}?)\s+i\s+(?P<party2>[A-ZČĆĐŠŽ][A-ZČĆĐŠŽa-zčćđšž\s\.,d\.o\.o\.-]{3,80}?)(?=\s*[,;\n]|$)/iu',

        // Pattern: "protiv"
        '/(?P<plaintiff>[A-ZČĆĐŠŽ][A-ZČĆĐŠŽa-zčćđšž\s\.,d\.o\.o\.-]{3,80}?)\s+protiv\s+(?P<defendant>[A-ZČĆĐŠŽ][A-ZČĆĐŠŽa-zčćđšž\s\.,d\.o\.o\.-]{3,80}?)(?=\s*[,;\n]|$)/iu',
    ];

    /**
     * Company suffixes (to help identify legal entities)
     */
    private const COMPANY_SUFFIXES = [
        'd.o.o.', 'd.d.', 'j.d.o.o.', 'obrt', 'udruga', 'zaklada',
    ];

    /**
     * Detect parties mentioned in the text.
     */
    public function detect(string $text): array
    {
        $results = [];
        $seen = [];

        // Use all patterns to extract parties
        foreach (self::NAME_PATTERNS as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $this->processMatch($match, $results, $seen);
                }
            }
        }

        // Deduplicate and clean
        return array_values($results);
    }

    /**
     * Process a regex match to extract party information.
     */
    private function processMatch(array $match, array &$results, array &$seen): void
    {
        // Handle role-based extraction
        if (isset($match['role']) && isset($match['name'])) {
            $role = mb_strtolower($match['role'], 'UTF-8');
            $name = $this->cleanPartyName($match['name']);

            if ($name && !isset($seen[$name])) {
                $results[] = [
                    'name' => $name,
                    'role' => self::PARTY_ROLES[$role] ?? 'party',
                    'type' => $this->detectEntityType($name),
                ];
                $seen[$name] = true;
            }
        }

        // Handle "između X i Y" pattern
        if (isset($match['party1']) && isset($match['party2'])) {
            foreach (['party1', 'party2'] as $key) {
                $name = $this->cleanPartyName($match[$key]);
                if ($name && !isset($seen[$name])) {
                    $results[] = [
                        'name' => $name,
                        'role' => 'party',
                        'type' => $this->detectEntityType($name),
                    ];
                    $seen[$name] = true;
                }
            }
        }

        // Handle "X protiv Y" pattern
        if (isset($match['plaintiff']) && isset($match['defendant'])) {
            $plaintiff = $this->cleanPartyName($match['plaintiff']);
            $defendant = $this->cleanPartyName($match['defendant']);

            if ($plaintiff && !isset($seen[$plaintiff])) {
                $results[] = [
                    'name' => $plaintiff,
                    'role' => 'plaintiff',
                    'type' => $this->detectEntityType($plaintiff),
                ];
                $seen[$plaintiff] = true;
            }

            if ($defendant && !isset($seen[$defendant])) {
                $results[] = [
                    'name' => $defendant,
                    'role' => 'defendant',
                    'type' => $this->detectEntityType($defendant),
                ];
                $seen[$defendant] = true;
            }
        }
    }

    /**
     * Clean and normalize party name.
     */
    private function cleanPartyName(string $name): ?string
    {
        $name = trim($name);

        // Remove trailing punctuation
        $name = rtrim($name, '.,;:');

        // Remove common noise words at the end
        $name = preg_replace('/\s+(i|te|kao|za|iz|sa|u|na|od|do|po)$/iu', '', $name);

        // Must be at least 3 characters
        if (mb_strlen($name, 'UTF-8') < 3) {
            return null;
        }

        // Remove if it's just common words
        $commonWords = ['Sud', 'Sudac', 'Republika', 'Hrvatska', 'Zagreb', 'Split'];
        if (in_array($name, $commonWords)) {
            return null;
        }

        return $name;
    }

    /**
     * Detect if entity is a person or organization.
     */
    private function detectEntityType(string $name): string
    {
        $nameLower = mb_strtolower($name, 'UTF-8');

        // Check for company suffixes
        foreach (self::COMPANY_SUFFIXES as $suffix) {
            if (str_contains($nameLower, $suffix)) {
                return 'organization';
            }
        }

        // Check for organizational keywords
        $orgKeywords = ['banka', 'trgovina', 'tvrtka', 'društvo', 'agencija', 'zavod', 'ministarstvo'];
        foreach ($orgKeywords as $keyword) {
            if (str_contains($nameLower, $keyword)) {
                return 'organization';
            }
        }

        // If name has multiple capitalized words, likely organization
        $capitalizedWords = preg_match_all('/\b[A-ZČĆĐŠŽ][a-zčćđšž]+/', $name);
        if ($capitalizedWords > 2) {
            return 'organization';
        }

        // Default to person
        return 'person';
    }
}
