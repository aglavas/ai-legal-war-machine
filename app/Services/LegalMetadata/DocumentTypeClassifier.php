<?php

namespace App\Services\LegalMetadata;

/**
 * Classifies Croatian legal document types based on content patterns.
 */
class DocumentTypeClassifier
{
    /**
     * Document type patterns with keywords and weights.
     */
    private const DOCUMENT_TYPES = [
        'judgment' => [
            'keywords' => ['presuda', 'presuđuje', 'presuđeno', 'odlučuje', 'rasprava'],
            'phrases' => ['u ime republike hrvatske', 'u ime naroda'],
            'weight' => 10,
        ],
        'decision' => [
            'keywords' => ['rješenje', 'odluka', 'zaključak', 'odlučuje se'],
            'phrases' => ['rješava se', 'odlučuje se'],
            'weight' => 8,
        ],
        'ruling' => [
            'keywords' => ['nalog', 'naredba', 'nalaže se'],
            'phrases' => ['nalaže se'],
            'weight' => 7,
        ],
        'motion' => [
            'keywords' => ['zahtjev', 'prijedlog', 'molba', 'predlaže se', 'traži se'],
            'phrases' => ['podnosi zahtjev', 'podnosi prijedlog'],
            'weight' => 6,
        ],
        'complaint' => [
            'keywords' => ['žalba', 'žalbeni', 'uložena žalba'],
            'phrases' => ['podnosi žalbu', 'ulaže žalbu'],
            'weight' => 7,
        ],
        'appeal' => [
            'keywords' => ['revizija', 'ustavna tužba'],
            'phrases' => ['podnosi reviziju', 'ustavna tužba'],
            'weight' => 7,
        ],
        'indictment' => [
            'keywords' => ['optužnica', 'optužni prijedlog', 'kazneni progon'],
            'phrases' => ['optužuje se', 'kazneno djelo'],
            'weight' => 9,
        ],
        'contract' => [
            'keywords' => ['ugovor', 'ugovorne strane', 'ugovarač', 'sporazum'],
            'phrases' => ['ugovorne strane', 'sklapaju ugovor', 'međusobno se dogovaraju'],
            'weight' => 10,
        ],
        'power_of_attorney' => [
            'keywords' => ['punomoć', 'opunomoćuje', 'punomočnik', 'punomoćnik'],
            'phrases' => ['daje punomoć', 'ovlašćuje'],
            'weight' => 10,
        ],
        'statement' => [
            'keywords' => ['izjava', 'očitovanje', 'izjavljuje'],
            'phrases' => ['daje izjavu', 'očituje se'],
            'weight' => 5,
        ],
        'certificate' => [
            'keywords' => ['potvrda', 'uvjerenje', 'potvrduje se'],
            'phrases' => ['izdaje se potvrda', 'izdaje se uvjerenje'],
            'weight' => 6,
        ],
        'statute' => [
            'keywords' => ['zakon', 'narodne novine', 'članak', 'primjenjuje se od'],
            'phrases' => ['hrvatski sabor', 'donosi zakon', 'stupanje na snagu'],
            'weight' => 9,
        ],
        'ordinance' => [
            'keywords' => ['pravilnik', 'uredba', 'propis'],
            'phrases' => ['donosi se pravilnik', 'donosi se uredba'],
            'weight' => 8,
        ],
    ];

    /**
     * Jurisdiction patterns
     */
    private const JURISDICTIONS = [
        'civil' => ['građanski', 'parnica', 'izvršenje', 'ovrha', 'obligacijski'],
        'criminal' => ['kazneni', 'kazneno djelo', 'optuženi', 'optužnica', 'zatvor', 'kazna'],
        'administrative' => ['upravni', 'upravno', 'ministarstvo', 'tijelo uprave'],
        'commercial' => ['trgovački', 'trgovina', 'poslovanje', 'trgovačko društvo'],
        'labor' => ['radni', 'radni odnos', 'zaposlenik', 'poslodavac', 'otkazivanje'],
        'family' => ['obiteljski', 'brak', 'razvod', 'roditeljska skrb', 'djeca'],
        'constitutional' => ['ustavni', 'ustavna tužba', 'temeljno pravo'],
    ];

    /**
     * Classify document type based on text content.
     */
    public function classify(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        $scores = [];

        // Score each document type
        foreach (self::DOCUMENT_TYPES as $type => $config) {
            $score = 0;

            // Count keyword matches
            foreach ($config['keywords'] as $keyword) {
                $count = mb_substr_count($text, mb_strtolower($keyword, 'UTF-8'));
                $score += $count * $config['weight'];
            }

            // Bonus for phrase matches
            foreach ($config['phrases'] as $phrase) {
                if (str_contains($text, mb_strtolower($phrase, 'UTF-8'))) {
                    $score += $config['weight'] * 3;
                }
            }

            $scores[$type] = $score;
        }

        // Find best match
        arsort($scores);
        $topType = array_key_first($scores);
        $topScore = $scores[$topType] ?? 0;

        // Calculate confidence based on score difference
        $secondScore = array_values($scores)[1] ?? 0;
        $confidence = $topScore > 0
            ? min(1.0, ($topScore / max(1, $topScore + $secondScore)))
            : 0.0;

        // Classify jurisdiction
        $jurisdiction = $this->classifyJurisdiction($text);

        return [
            'document_type' => $topScore > 0 ? $topType : null,
            'confidence' => $confidence,
            'jurisdiction' => $jurisdiction['type'],
            'jurisdiction_confidence' => $jurisdiction['confidence'],
            'all_scores' => $scores,
        ];
    }

    /**
     * Classify jurisdiction based on text.
     */
    private function classifyJurisdiction(string $text): array
    {
        $scores = [];

        foreach (self::JURISDICTIONS as $jurisdiction => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                $count = mb_substr_count($text, mb_strtolower($keyword, 'UTF-8'));
                $score += $count * 10;
            }
            $scores[$jurisdiction] = $score;
        }

        arsort($scores);
        $topJurisdiction = array_key_first($scores);
        $topScore = $scores[$topJurisdiction] ?? 0;

        $secondScore = array_values($scores)[1] ?? 0;
        $confidence = $topScore > 0
            ? min(1.0, ($topScore / max(1, $topScore + $secondScore)))
            : 0.0;

        return [
            'type' => $topScore > 0 ? $topJurisdiction : null,
            'confidence' => $confidence,
        ];
    }
}
