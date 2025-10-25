<?php

namespace App\Services\Ocr;

/**
 * HrLanguageNormalizer
 *
 * Normalizes Croatian text from OCR output.
 * Handles common OCR artifacts specific to Croatian language:
 * - Ligature fixes (fi, fl, etc.)
 * - Hyphenation repair
 * - Croatian diacritics (č, ć, ž, š, đ)
 * - Quote normalization
 * - Whitespace cleanup
 */
class HrLanguageNormalizer
{
    /**
     * Common OCR ligature substitutions.
     */
    protected array $ligatures = [
        'ﬁ' => 'fi',
        'ﬂ' => 'fl',
        'ﬀ' => 'ff',
        'ﬃ' => 'ffi',
        'ﬄ' => 'ffl',
        'ﬅ' => 'ft',
        'ﬆ' => 'st',
    ];

    /**
     * Common OCR character confusion in Croatian.
     * Maps commonly confused characters to their correct form.
     */
    protected array $characterFixes = [
        // Croatian diacritics often misread
        'ĉ' => 'č',  // Wrong circumflex
        'ċ' => 'č',  // Dot above instead of caron
        'ć' => 'ć',  // Ensure proper NFC form
        'č' => 'č',  // Ensure proper NFC form
        'ž' => 'ž',  // Ensure proper NFC form
        'š' => 'š',  // Ensure proper NFC form
        'đ' => 'đ',  // Ensure proper NFC form

        // Common confusions
        'l' => 'I',  // Context-dependent, handled separately
        '1' => 'l',  // Digit 1 vs lowercase L
        '0' => 'O',  // Digit 0 vs uppercase O

        // Quote normalization
        '„' => '"',  // German-style quotes
        '"' => '"',
        ''' => "'",
        ''' => "'",
        '`' => "'",
        '´' => "'",
    ];

    /**
     * Normalize Croatian text.
     */
    public function normalize(string $text): string
    {
        // Step 1: Unicode normalization (NFC)
        $text = \Normalizer::normalize($text, \Normalizer::FORM_C);

        // Step 2: Fix ligatures
        $text = $this->fixLigatures($text);

        // Step 3: Repair hyphenation (words split across lines)
        $text = $this->repairHyphenation($text);

        // Step 4: Fix common character confusions
        $text = $this->fixCharacterConfusions($text);

        // Step 5: Normalize whitespace
        $text = $this->normalizeWhitespace($text);

        // Step 6: Fix quote marks
        $text = $this->normalizeQuotes($text);

        // Step 7: Remove soft hyphens and zero-width characters
        $text = $this->removeInvisibleCharacters($text);

        return $text;
    }

    /**
     * Fix common ligatures that OCR often preserves.
     */
    protected function fixLigatures(string $text): string
    {
        return str_replace(array_keys($this->ligatures), array_values($this->ligatures), $text);
    }

    /**
     * Repair hyphenated words split across line breaks.
     *
     * Pattern: "word-\n word" -> "wordword"
     * But preserve intentional hyphens in compounds.
     */
    protected function repairHyphenation(string $text): string
    {
        // Match: word character, hyphen, optional whitespace, newline, optional whitespace, word character
        // This pattern is conservative to avoid breaking intentional line-ending hyphens
        $pattern = '/(\p{L}+)-\s*\n\s*(\p{L}+)/u';

        return preg_replace_callback($pattern, function ($matches) {
            $before = $matches[1];
            $after = $matches[2];

            // If the next word starts with lowercase, likely a split word
            if (mb_strtolower($after[0], 'UTF-8') === $after[0]) {
                return $before . $after;
            }

            // Otherwise, preserve the hyphen with a space
            return $before . '- ' . $after;
        }, $text);
    }

    /**
     * Fix common character confusions.
     */
    protected function fixCharacterConfusions(string $text): string
    {
        // Apply simple character replacements
        $text = str_replace(array_keys($this->characterFixes), array_values($this->characterFixes), $text);

        // Context-aware fixes
        $text = $this->fixDigitLetterConfusion($text);

        return $text;
    }

    /**
     * Fix digit/letter confusion based on context.
     */
    protected function fixDigitLetterConfusion(string $text): string
    {
        // Fix '1' to 'l' when surrounded by letters (e.g., "principa1" -> "principal")
        $text = preg_replace('/(?<=\p{L})1(?=\p{L})/u', 'l', $text);

        // Fix 'l' to '1' when in numeric context (e.g., "l23" -> "123")
        $text = preg_replace('/(?<=\d)l(?=\d)/u', '1', $text);

        // Fix '0' to 'O' in specific contexts (Croatian words)
        // Example: "zak0n" -> "zakon"
        $text = preg_replace('/(?<=\p{L})0(?=n)/u', 'o', $text);

        return $text;
    }

    /**
     * Normalize whitespace.
     */
    protected function normalizeWhitespace(string $text): string
    {
        // Replace non-breaking spaces with regular spaces
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = str_replace("\u{00A0}", ' ', $text);

        // Replace tabs with spaces
        $text = str_replace("\t", ' ', $text);

        // Collapse multiple spaces
        $text = preg_replace('/ {2,}/', ' ', $text);

        // Normalize line endings
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        // Collapse excessive blank lines (keep max 2)
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Remove trailing spaces from lines
        $text = preg_replace('/ +$/m', '', $text);

        return $text;
    }

    /**
     * Normalize quote characters.
     */
    protected function normalizeQuotes(string $text): string
    {
        // Smart quotes to straight quotes (for consistency)
        $text = str_replace(['"', '"'], '"', $text);
        $text = str_replace([''', '''], "'", $text);

        // Remove backticks used as quotes
        $text = str_replace('`', "'", $text);

        return $text;
    }

    /**
     * Remove invisible and zero-width characters.
     */
    protected function removeInvisibleCharacters(string $text): string
    {
        // Soft hyphens (U+00AD)
        $text = str_replace("\xc2\xad", '', $text);
        $text = str_replace("\u{00AD}", '', $text);

        // Zero-width space (U+200B)
        $text = str_replace("\u{200B}", '', $text);

        // Zero-width non-joiner (U+200C)
        $text = str_replace("\u{200C}", '', $text);

        // Zero-width joiner (U+200D)
        $text = str_replace("\u{200D}", '', $text);

        // Word joiner (U+2060)
        $text = str_replace("\u{2060}", '', $text);

        return $text;
    }

    /**
     * Validate Croatian diacritics are properly formed.
     */
    public function validateDiacritics(string $text): array
    {
        $issues = [];

        // Check for decomposed diacritics (NFD form)
        if (preg_match('/\p{L}\p{M}/u', $text)) {
            $issues[] = 'Contains decomposed diacritics (NFD form)';
        }

        // Check for common OCR errors in Croatian letters
        $croatianChars = ['č', 'ć', 'ž', 'š', 'đ', 'Č', 'Ć', 'Ž', 'Š', 'Đ'];
        foreach ($croatianChars as $char) {
            if (strpos($text, $char) !== false) {
                // Check if properly normalized
                $normalized = \Normalizer::normalize($char, \Normalizer::FORM_C);
                if ($char !== $normalized) {
                    $issues[] = "Character '$char' not in NFC form";
                }
            }
        }

        return $issues;
    }
}
