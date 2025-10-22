<?php

namespace Tests\Unit;

use App\Services\LegalCitations\StatuteCitationDetector;
use Tests\TestCase;

class StatuteCitationDetectorTest extends TestCase
{
    public function test_detects_kaznenog_zakona_with_listed_paragraphs_variants(): void
    {
        $detector = new StatuteCitationDetector();

        $text1 = '... opisano u čl. 331.st.1. i st.3. Kaznenog zakona.';
        $text2 = '... opisano u čl. 331 st.1. i st.3. Kaznenog zakona';

        foreach ([$text1, $text2] as $text) {
            $results = $detector->detect($text);
            $canon = array_values(array_filter(array_map(fn($r) => $r['canonical'] ?? null, $results)));

            $this->assertContains('KZ:čl.331 st.1', $canon, 'Should detect KZ čl.331 st.1');
            $this->assertContains('KZ:čl.331 st.3', $canon, 'Should detect KZ čl.331 st.3');
        }
    }

    public function test_detects_zakon_o_kaznenom_postupku_article_before_law(): void
    {
        $detector = new StatuteCitationDetector();

        $text = 'Temeljem članka 272. st. 1. Zakona o kaznenom postupku';
        $results = $detector->detect($text);
        $canon = array_values(array_filter(array_map(fn($r) => $r['canonical'] ?? null, $results)));

        $this->assertContains('ZKP:čl.272 st.1', $canon, 'Should detect ZKP čl.272 st.1');
    }

    public function test_detects_first_example_with_mixed_text_and_no_space_dots(): void
    {
        $detector = new StatuteCitationDetector();
        $text = 'iz čl.190. st.2. kaznenog djela Nedozvoljeno posjedovanje, izrada i nabavljanje oružja i eksplozivnih tvari opisano u čl. 331.st.1. i st.3. Kaznenog zakona.';
        $results = $detector->detect($text);
        $canon = array_values(array_filter(array_map(fn($r) => $r['canonical'] ?? null, $results)));

        $this->assertContains('KZ:čl.331 st.1', $canon);
        $this->assertContains('KZ:čl.331 st.3', $canon);
    }

    public function test_detects_third_example_with_quotes_and_commas(): void
    {
        $detector = new StatuteCitationDetector();
        $text = 'djelo "Nedozvoljeno posjedovanje, izrada i nabavljanje oružja i eksplozivnih tvari" opisano u čl. 331 st.1. i st.3. Kaznenog zakona';
        $results = $detector->detect($text);
        $canon = array_values(array_filter(array_map(fn($r) => $r['canonical'] ?? null, $results)));

        $this->assertContains('KZ:čl.331 st.1', $canon);
        $this->assertContains('KZ:čl.331 st.3', $canon);
    }

    public function test_detects_lawless_article_and_paragraph(): void
    {
        $detector = new StatuteCitationDetector();
        $text = 'opisano u čl. 331. st.1. što';
        $results = $detector->detect($text);
        $canon = array_values(array_filter(array_map(fn($r) => $r['canonical'] ?? null, $results)));

        $this->assertContains('čl.331 st.1', $canon);
    }
}
