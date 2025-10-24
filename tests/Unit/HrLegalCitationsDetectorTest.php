<?php

namespace Tests\Unit;

use App\Services\HrLegalCitationsDetector;
use Tests\TestCase;

class HrLegalCitationsDetectorTest extends TestCase
{
    private HrLegalCitationsDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new HrLegalCitationsDetector();
    }

    /** @test */
    public function it_detects_narodne_novine_references()
    {
        $text = 'Prema NN 123/05 i Narodne novine 67/89 propisano je';

        $result = $this->detector->detect('nn', $text);

        $this->assertCount(2, $result);
        $this->assertNotEmpty($result[0]['issues']);
        $this->assertContains('123/05', $result[0]['issues']);
    }

    /** @test */
    public function it_detects_statute_citations_with_articles()
    {
        $text = 'ZPP čl. 110 st. 2 toč. 3';

        $result = $this->detector->detect('statutes', $text);

        $this->assertNotEmpty($result);
        $this->assertEquals('ZPP', $result[0]['law']);
        $this->assertEquals('110', $result[0]['article']);
        $this->assertEquals('2', $result[0]['paragraph']);
        $this->assertEquals('3', $result[0]['item']);
    }

    /** @test */
    public function it_detects_case_numbers()
    {
        $text = 'U predmetu Rev-123/2023 i Gž-456/22 odlučeno je';

        $result = $this->detector->detect('cases', $text);

        $this->assertCount(2, $result);
        $this->assertEquals('Rev', $result[0]['prefix']);
        $this->assertEquals('123', $result[0]['number']);
        $this->assertEquals('2023', $result[0]['year']);
    }

    /** @test */
    public function it_detects_court_types()
    {
        $text = 'Vrhovni sud Republike Hrvatske i Županijski sud u Zagrebu';

        $result = $this->detector->detect('courts', $text);

        $this->assertGreaterThanOrEqual(2, count($result));

        $types = array_column($result, 'type');
        $this->assertContains('supreme', $types);
        $this->assertContains('county', $types);
    }

    /** @test */
    public function it_detects_legal_terms()
    {
        $text = 'Tužitelj je podnio tužbu, a tuženik je dao odgovor na tužbu';

        $result = $this->detector->detect('legal_terms', $text);

        $this->assertGreaterThan(0, count($result));

        $terms = array_column($result, 'term');
        $this->assertContains('tužitelj', $terms);
        $this->assertContains('tužba', $terms);
    }

    /** @test */
    public function it_extracts_all_entities_in_normalized_format()
    {
        $text = 'Prema NN 35/05 ZOO čl. 1045 u predmetu Rev-789/2023 Vrhovni sud';

        $result = $this->detector->extract($text);

        $this->assertArrayHasKey('laws', $result);
        $this->assertArrayHasKey('articles', $result);
        $this->assertArrayHasKey('case_numbers', $result);
        $this->assertArrayHasKey('court_types', $result);
        $this->assertArrayHasKey('legal_terms', $result);
        $this->assertArrayHasKey('has_specific_refs', $result);

        $this->assertTrue($result['has_specific_refs']);
        $this->assertNotEmpty($result['laws']);
        $this->assertNotEmpty($result['articles']);
        $this->assertNotEmpty($result['case_numbers']);
    }

    /** @test */
    public function it_sets_has_specific_refs_flag_correctly()
    {
        $textWithRefs = 'Prema NN 123/45 i Rev-456/2022';
        $result = $this->detector->extract($textWithRefs);
        $this->assertTrue($result['has_specific_refs']);

        $textWithoutRefs = 'neka opća pitanja o pravu';
        $result = $this->detector->extract($textWithoutRefs);
        $this->assertFalse($result['has_specific_refs']);
    }

    /** @test */
    public function it_handles_empty_text()
    {
        $result = $this->detector->extract('');

        $this->assertIsArray($result);
        $this->assertEmpty($result['laws']);
        $this->assertEmpty($result['articles']);
        $this->assertEmpty($result['case_numbers']);
        $this->assertFalse($result['has_specific_refs']);
    }

    /** @test */
    public function it_detects_constitutional_court_case_numbers()
    {
        $text = 'U predmetu U-III-1234/2019 Ustavni sud odlučio je';

        $result = $this->detector->detect('cases', $text);

        $this->assertNotEmpty($result);
        $this->assertEquals('U-III', $result[0]['prefix']);
        $this->assertEquals('1234', $result[0]['number']);
        $this->assertEquals('2019', $result[0]['year']);
    }

    /** @test */
    public function it_detects_long_form_law_names()
    {
        $text = 'Kaznenog zakona, čl. 331 st. 1';

        $result = $this->detector->detect('statutes', $text);

        $this->assertNotEmpty($result);
        $this->assertEquals('KZ', $result[0]['law']);
        $this->assertEquals('331', $result[0]['article']);
        $this->assertEquals('1', $result[0]['paragraph']);
    }

    /** @test */
    public function it_handles_multiple_nn_issues()
    {
        $text = 'Narodne novine, broj 123/05, 45/07, 89/09';

        $result = $this->detector->detect('nn', $text);

        $this->assertNotEmpty($result);
        $this->assertCount(3, $result[0]['issues']);
    }

    /** @test */
    public function it_extracts_keywords_from_query()
    {
        $text = 'Što kaže zakon o obveznim odnosima u vezi s ugovorima?';

        $keywords = $this->detector->extractKeywords($text);

        $this->assertIsArray($keywords);
        $this->assertContains('zakon', $keywords);
        $this->assertContains('obveznim', $keywords);
        $this->assertContains('odnosima', $keywords);
        $this->assertContains('ugovorima', $keywords);

        // Stop words should be removed
        $this->assertNotContains('što', $keywords);
        $this->assertNotContains('o', $keywords);
        $this->assertNotContains('s', $keywords);
    }

    /** @test */
    public function it_handles_article_ranges()
    {
        $text = 'ZPP čl. 10-15';

        $result = $this->detector->detect('statutes', $text);

        $this->assertNotEmpty($result);
        // Should expand range to individual articles
        $this->assertCount(6, $result); // Articles 10, 11, 12, 13, 14, 15
    }

    /** @test */
    public function it_handles_paragraph_lists()
    {
        $text = 'ZPP čl. 110 st. 1, 2 i 3';

        $result = $this->detector->detect('statutes', $text);

        $this->assertNotEmpty($result);
        // Should detect multiple paragraphs
        $this->assertGreaterThan(1, count($result));
    }

    /** @test */
    public function it_deduplicates_law_references()
    {
        $text = 'ZPP čl. 110 i ZPP čl. 115';

        $result = $this->detector->extract($text);

        // Should have only one ZPP law reference (deduplicated)
        $laws = $result['laws'];
        $zppLaws = array_filter($laws, fn($l) => $l['value'] === 'ZPP' || $l['abbreviation'] === 'ZPP');

        $this->assertEquals(1, count($zppLaws));
    }

    /** @test */
    public function it_detects_court_type_with_city_name()
    {
        $text = 'Županijski sud u Zagrebu';

        $result = $this->detector->detect('courts', $text);

        $this->assertNotEmpty($result);
        $this->assertEquals('county', $result[0]['type']);
    }

    /** @test */
    public function it_categorizes_legal_terms()
    {
        $text = 'ugovor obveza presuda tužba radni odnos vlasništvo';

        $result = $this->detector->detect('legal_terms', $text);

        $this->assertNotEmpty($result);

        $categories = array_column($result, 'category');
        $this->assertContains('contract_law', $categories);
        $this->assertContains('procedure', $categories);
        $this->assertContains('labor_law', $categories);
        $this->assertContains('property_law', $categories);
    }

    /** @test */
    public function it_provides_english_translations_for_legal_terms()
    {
        $text = 'tužba ugovor presuda';

        $result = $this->detector->detect('legal_terms', $text);

        $this->assertNotEmpty($result);

        $englishTerms = array_column($result, 'english');
        $this->assertContains('lawsuit', $englishTerms);
        $this->assertContains('contract', $englishTerms);
        $this->assertContains('judgment', $englishTerms);
    }

    /** @test */
    public function it_handles_all_known_law_abbreviations()
    {
        $text = 'ZPP ZKP KZ ZOO OZ ZUP ZTD';

        $result = $this->detector->detect('statutes', $text);

        $this->assertGreaterThanOrEqual(7, count($result));
    }

    /** @test */
    public function it_normalizes_laws_from_both_statutes_and_nn()
    {
        $text = 'ZOO čl. 1045 prema NN 35/05';

        $result = $this->detector->extract($text);

        $this->assertNotEmpty($result['laws']);
        $this->assertGreaterThanOrEqual(2, count($result['laws']));

        $types = array_column($result['laws'], 'type');
        $this->assertContains('abbreviation', $types);
        $this->assertContains('nn_reference', $types);
    }

    /** @test */
    public function it_normalizes_article_details()
    {
        $text = 'ZOO čl. 1045 st. 1 toč. 3 al. 2';

        $result = $this->detector->extract($text);

        $this->assertNotEmpty($result['articles']);

        $article = $result['articles'][0];
        $this->assertEquals('1045', $article['number']);
        $this->assertEquals('1', $article['paragraph']);
        $this->assertEquals('3', $article['item']);
        $this->assertEquals('2', $article['alineja']);
    }
}
