<?php

namespace App\Services\LegalCitations;

class CroatianLawRegistry
{
    public const LAW_ABBREVIATIONS = [
        'ZPP', 'ZKP', 'ZOO', 'OZ', 'ZTD', 'ZUP', 'ZUS', 
        'KZ', 'ZK', 'ZJN', 'ZZK', 'ZSPC', 'ZIS', 'ZDO', 
        'ZDR', 'ZOR', 'ZPD',
    ];

    public const LAW_NAME_TO_ABBREV = [
        'zakon o parničnom postupku' => 'ZPP',
        'zakon o kaznenom postupku' => 'ZKP',
        'kazneni zakon' => 'KZ',
        'zakon o obveznim odnosima' => 'ZOO',
        'ovršni zakon' => 'OZ',
        'zakon o upravnom postupku' => 'ZUP',
        'zakon o upravnim sporovima' => 'ZUS',
        'zakon o trgovačkim društvima' => 'ZTD',
        'zakon o zemljišnim knjigama' => 'ZK',
    ];

    public static function getAbbreviationRegex(): string
    {
        $escaped = array_map(
            fn($s) => preg_quote($s, '/'), 
            self::LAW_ABBREVIATIONS
        );
        
        return '(?:' . implode('|', $escaped) . ')';
    }

    public static function normalizeLaw(?string $law): ?string
    {
        if (!$law) {
            return null;
        }

        $lower = mb_strtolower(trim($law), 'UTF-8');
        $upper = mb_strtoupper($law, 'UTF-8');

        // Check if it's already a valid abbreviation
        if (in_array($upper, self::LAW_ABBREVIATIONS, true)) {
            return $upper;
        }

        // Try to find in long names map
        foreach (self::LAW_NAME_TO_ABBREV as $name => $abbr) {
            if (strpos($lower, $name) !== false) {
                return $abbr;
            }
        }

        // Fallback to uppercase
        return $upper;
    }

    public static function getLongNameRegex(): string
    {
        $names = array_map(
            fn($n) => preg_quote($n, '/'), 
            array_keys(self::LAW_NAME_TO_ABBREV)
        );
        
        return implode('|', $names);
    }
}