<?php

namespace App\Services\LegalCitations;

class CourtTypeDetector extends BaseCitationDetector
{
    private const COURT_TYPES = [
        'Ustavni sud Republike Hrvatske' => ['type' => 'constitutional', 'abbr' => 'USRH'],
        'Ustavni sud' => ['type' => 'constitutional', 'abbr' => 'USRH'],
        'Vrhovni sud Republike Hrvatske' => ['type' => 'supreme', 'abbr' => 'VSRH'],
        'Vrhovni sud' => ['type' => 'supreme', 'abbr' => 'VSRH'],
        'Visoki trgovački sud Republike Hrvatske' => ['type' => 'high_commercial', 'abbr' => 'VTSRH'],
        'Visoki trgovački sud' => ['type' => 'high_commercial', 'abbr' => 'VTS'],
        'Visoki upravni sud Republike Hrvatske' => ['type' => 'high_administrative', 'abbr' => 'VUSRH'],
        'Visoki upravni sud' => ['type' => 'high_administrative', 'abbr' => 'VUS'],
        'Visoki prekršajni sud Republike Hrvatske' => ['type' => 'high_misdemeanor', 'abbr' => 'VPSRH'],
        'Visoki prekršajni sud' => ['type' => 'high_misdemeanor', 'abbr' => 'VPS'],
        'Županijski sud' => ['type' => 'county', 'abbr' => null],
        'Općinski sud' => ['type' => 'municipal', 'abbr' => null],
        'Trgovački sud' => ['type' => 'commercial', 'abbr' => null],
        'Upravni sud' => ['type' => 'administrative', 'abbr' => null],
        'Prekršajni sud' => ['type' => 'misdemeanor', 'abbr' => null],
    ];

    public function detect(string $text): array
    {
        $results = [];
        $seen = [];

        foreach (self::COURT_TYPES as $courtName => $info) {
            $pattern = '/\b' . preg_quote($courtName, '/') . '(?:\s+(?:u|Republike\sHrvatske))?\b/iu';

            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $match) {
                    $raw = $match[0][0];
                    $canonical = $info['type'];

                    // Avoid duplicates
                    if (isset($seen[$canonical])) {
                        continue;
                    }

                    $seen[$canonical] = true;
                    $results[] = [
                        'raw' => $raw,
                        'name' => $courtName,
                        'type' => $info['type'],
                        'abbreviation' => $info['abbr'],
                        'canonical' => $canonical,
                    ];
                }
            }
        }

        return $results;
    }
}
