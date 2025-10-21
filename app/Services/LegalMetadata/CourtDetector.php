<?php

namespace App\Services\LegalMetadata;

/**
 * Detects Croatian court names in legal document text.
 */
class CourtDetector
{
    /**
     * Known Croatian courts with variations.
     */
    private const COURTS = [
        // Supreme Court
        'Vrhovni sud Republike Hrvatske' => ['VSRH', 'Vrhovni sud RH', 'Vrhovni sud'],

        // Constitutional Court
        'Ustavni sud Republike Hrvatske' => ['USRH', 'Ustavni sud RH', 'Ustavni sud'],

        // High Commercial Court
        'Visoki trgovački sud Republike Hrvatske' => ['VTSRH', 'VTS RH', 'Visoki trgovački sud'],

        // High Misdemeanor Court
        'Visoki prekršajni sud Republike Hrvatske' => ['VPSRH', 'VPS RH', 'Visoki prekršajni sud'],

        // High Administrative Court
        'Visoki upravni sud Republike Hrvatske' => ['VUSRH', 'VUS RH', 'Visoki upravni sud'],

        // County Courts
        'Županijski sud' => [],

        // Municipal Courts
        'Općinski sud' => [],

        // Commercial Courts
        'Trgovački sud' => [],

        // Misdemeanor Courts
        'Prekršajni sud' => [],
    ];

    /**
     * Cities with courts
     */
    private const COURT_CITIES = [
        'Zagreb', 'Split', 'Rijeka', 'Osijek', 'Varaždin', 'Zadar',
        'Slavonski Brod', 'Pula', 'Sisak', 'Karlovac', 'Šibenik',
        'Dubrovnik', 'Bjelovar', 'Čakovec', 'Koprivnica', 'Gospić',
        'Pazin', 'Požega', 'Virovitica', 'Vukovar',
    ];

    /**
     * Detect courts mentioned in the text.
     */
    public function detect(string $text): array
    {
        $results = [];
        $seen = [];

        // Detect full court names and abbreviations
        foreach (self::COURTS as $fullName => $variations) {
            // Check full name
            if (mb_stripos($text, $fullName) !== false) {
                $canonical = $this->normalizeCourtName($fullName);
                if (!isset($seen[$canonical])) {
                    $results[] = [
                        'raw' => $fullName,
                        'normalized' => $canonical,
                        'type' => $this->classifyCourtType($fullName),
                    ];
                    $seen[$canonical] = true;
                }
            }

            // Check variations
            foreach ($variations as $variation) {
                if (preg_match('/\b' . preg_quote($variation, '/') . '\b/iu', $text)) {
                    $canonical = $this->normalizeCourtName($fullName);
                    if (!isset($seen[$canonical])) {
                        $results[] = [
                            'raw' => $variation,
                            'normalized' => $canonical,
                            'type' => $this->classifyCourtType($fullName),
                        ];
                        $seen[$canonical] = true;
                    }
                }
            }
        }

        // Detect city-specific courts (e.g., "Županijski sud u Zagrebu")
        $results = array_merge($results, $this->detectCitySpecificCourts($text, $seen));

        return $results;
    }

    /**
     * Detect courts with city names.
     */
    private function detectCitySpecificCourts(string $text, array &$seen): array
    {
        $results = [];

        $courtTypes = [
            'Županijski sud',
            'Općinski sud',
            'Trgovački sud',
            'Prekršajni sud',
        ];

        foreach ($courtTypes as $courtType) {
            foreach (self::COURT_CITIES as $city) {
                $patterns = [
                    "/\b{$courtType}\s+u\s+{$city}[ui]?\b/iu",
                    "/\b{$courtType}\s+{$city}\b/iu",
                ];

                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $text, $matches)) {
                        $raw = $matches[0];
                        $canonical = $this->normalizeCourtName($raw);

                        if (!isset($seen[$canonical])) {
                            $results[] = [
                                'raw' => $raw,
                                'normalized' => $canonical,
                                'type' => $this->classifyCourtType($courtType),
                                'city' => $city,
                            ];
                            $seen[$canonical] = true;
                        }
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Normalize court name for deduplication.
     */
    private function normalizeCourtName(string $name): string
    {
        $normalized = mb_strtolower(trim($name), 'UTF-8');
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = str_replace('republike hrvatske', 'rh', $normalized);
        return $normalized;
    }

    /**
     * Classify court type.
     */
    private function classifyCourtType(string $courtName): string
    {
        $name = mb_strtolower($courtName, 'UTF-8');

        if (str_contains($name, 'vrhovni')) return 'supreme';
        if (str_contains($name, 'ustavni')) return 'constitutional';
        if (str_contains($name, 'visoki')) return 'high';
        if (str_contains($name, 'županijski')) return 'county';
        if (str_contains($name, 'općinski')) return 'municipal';
        if (str_contains($name, 'trgovački')) return 'commercial';
        if (str_contains($name, 'prekršajni')) return 'misdemeanor';
        if (str_contains($name, 'upravni')) return 'administrative';

        return 'other';
    }
}
