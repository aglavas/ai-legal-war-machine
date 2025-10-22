<?php

namespace App\Services\LegalMetadata;

class KeyPhraseExtractor
{
    /**
     * Extract common Croatian legal key phrases from text.
     * Returns list of ['key' => slug, 'phrase' => canonical, 'count' => int, 'context' => string]
     */
    public function extract(string $text): array
    {
        if ($text === '') {
            return [];
        }

        [$norm, $map] = $this->normalizeWithMap($text);

        $dictionary = $this->dictionary();
        $found = [];

        foreach ($dictionary as $slug => $canonical) {
            $needle = $this->simplify($canonical);
            $regex = '/\b' . preg_quote($needle, '/') . '\b/u';

            if (!preg_match_all($regex, $norm, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $count = count($matches[0]);
            if ($count === 0) {
                continue;
            }

            // Build context around the first occurrence using original text offsets
            $first = $matches[0][0];
            $normOffset = $first[1];
            $origOffset = $map[$normOffset] ?? 0;
            $context = $this->contextSnippet($text, $origOffset, mb_strlen($canonical, 'UTF-8'));

            $found[] = [
                'key' => $slug,
                'phrase' => $canonical,
                'count' => $count,
                'context' => $context,
            ];
        }

        return $found;
    }

    private function dictionary(): array
    {
        // Canonical phrases (Croatian), keys are slugs
        return [
            'u-ime-republike-hrvatske' => 'u ime republike hrvatske',
            'u-ime-naroda' => 'u ime naroda',
            'odlucuje-se' => 'odlučuje se',
            'nalaze-se' => 'nalaže se',
            'pravni-lijek' => 'pravni lijek',
            'pravomocna-odluka' => 'pravomoćna odluka',
            'izvrsenje' => 'izvršenje',
            'zalba' => 'žalba',
            'revizija' => 'revizija',
            'kasacija' => 'kasacija',
            'na-temelju-clanka' => 'na temelju članka',
            'temeljem-clanka' => 'temeljem članka',
            'clanak' => 'članak',
            'stavak' => 'stavak',
            'tocka' => 'točka',
            'alineja' => 'alineja',
            'troskovi-postupka' => 'troškovi postupka',
            'parnicni-troskovi' => 'parnični troškovi',
            'presuda' => 'presuda',
            'rjesenje' => 'rješenje',
            'obrazlozenje' => 'obrazloženje',
            'izreka-presude' => 'izreka presude',
            'kazneni-postupak' => 'kazneni postupak',
            'parnicni-postupak' => 'parnični postupak',
            'narodne-novine' => 'narodne novine',
            'u-roku-od' => 'u roku od',
            'ovaj-sud' => 'ovaj sud',
            'okrivljenik' => 'okrivljenik',
            'tuzitelj' => 'tužitelj',
            'tuzenik' => 'tuženik',
            'odvjetnik' => 'odvjetnik',
            'optuznica' => 'optužnica',
        ];
    }

    private function simplify(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = strtr($s, [
            'č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'đ' => 'd',
        ]);
        // Collapse whitespace
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }

    /**
     * Build a normalized string (lowercased, diacritics simplified, non-alnum to space, collapsed) and a map
     * from each normalized character index to original byte offset in $orig.
     *
     * @return array{0:string,1:array<int,int>}
     */
    private function normalizeWithMap(string $orig): array
    {
        $norm = '';
        $map = [];
        $len = mb_strlen($orig, 'UTF-8');
        $prevWasSpace = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($orig, $i, 1, 'UTF-8');
            $lc = mb_strtolower($ch, 'UTF-8');
            $lc = strtr($lc, [
                'č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'đ' => 'd',
            ]);

            // Alphanumeric?
            if (preg_match('/[a-z0-9]/u', $lc)) {
                $norm .= $lc;
                $map[mb_strlen($norm, 'UTF-8') - 1] = $this->utf8ByteOffset($orig, $i);
                $prevWasSpace = false;
                continue;
            }

            // Treat everything else as space (once)
            if (!$prevWasSpace) {
                $norm .= ' ';
                $map[mb_strlen($norm, 'UTF-8') - 1] = $this->utf8ByteOffset($orig, $i);
                $prevWasSpace = true;
            }
        }

        // Collapse duplicate spaces (already prevented), trim
        $norm = trim($norm);
        return [$norm, $map];
    }

    private function utf8ByteOffset(string $s, int $charIndex): int
    {
        // Compute byte offset for a given character index
        if ($charIndex <= 0) {
            return 0;
        }
        $prefix = mb_substr($s, 0, $charIndex, 'UTF-8');
        return strlen($prefix);
    }

    private function contextSnippet(string $orig, int $byteOffset, int $approxChars): string
    {
        $ctxBytesEachSide = 40;
        $start = max(0, $byteOffset - $ctxBytesEachSide);
        $leftLen = $byteOffset - $start;
        $left = $leftLen > 0 ? mb_strcut($orig, $start, $leftLen, 'UTF-8') : '';

        $rawLen = max(1, $approxChars); // approximate match length in chars; use as hint only
        $center = mb_strcut($orig, $byteOffset, $rawLen, 'UTF-8');
        $right = mb_strcut($orig, $byteOffset + strlen($center), $ctxBytesEachSide, 'UTF-8');

        $hasMoreLeft = $start > 0;
        $hasMoreRight = ($byteOffset + strlen($center)) < strlen($orig);

        return ($hasMoreLeft ? '…' : '') . $left . $center . $right . ($hasMoreRight ? '…' : '');
    }
}

