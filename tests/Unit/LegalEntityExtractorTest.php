<?php

namespace Tests\Unit;

use App\Services\LegalEntityExtractor;
use Tests\TestCase;

class LegalEntityExtractorTest extends TestCase
{
    private LegalEntityExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new LegalEntityExtractor();
    }

    /** @test */
    public function it_extracts_nn_references()
    {
        $text = 'Prema NN 123/45 i NN 67/89 propisano je';

        $result = $this->extractor->extract($text);

        $this->assertCount(2, $result['laws']);
        $this->assertEquals('nn_reference', $result['laws'][0]['type']);
        $this->assertEquals('123', $result['laws'][0]['number']);
        $this->assertEquals('45', $result['laws'][0]['year']);
        $this->assertEquals('NN 123/45', $result['laws'][0]['value']);
    }

    /** @test */
    public function it_extracts_law_names()
    {
        $text = 'Zakon o obveznim odnosima i Zakon o kaznenom postupku';

        $result = $this->extractor->extract($text);

        $this->assertGreaterThanOrEqual(2, count($result['laws']));
        $lawNames = array_column($result['laws'], 'value');
        $this->assertContains('Zakon o obveznim odnosima', $lawNames);
        $this->assertContains('Zakon o kaznenom postupku', $lawNames);
    }

    /** @test */
    public function it_extracts_law_abbreviations()
    {
        $text = 'Prema ZOR i OZ trebalo bi';

        $result = $this->extractor->extract($text);

        $this->assertCount(2, $result['laws']);

        $abbreviations = array_column($result['laws'], 'abbreviation');
        $this->assertContains('ZOR', $abbreviations);
        $this->assertContains('OZ', $abbreviations);

        $fullNames = array_column($result['laws'], 'full_name');
        $this->assertContains('Zakon o obveznim odnosima', $fullNames);
    }

    /** @test */
    public function it_extracts_article_references()
    {
        $text = 'Prema članku 123 i čl. 45 stavak 2 točka 3';

        $result = $this->extractor->extract($text);

        $this->assertGreaterThanOrEqual(2, count($result['articles']));
        $numbers = array_column($result['articles'], 'number');
        $this->assertContains('123', $numbers);
        $this->assertContains('45', $numbers);
    }

    /** @test */
    public function it_extracts_paragraph_references()
    {
        $text = 'stavak 2 i st. 5';

        $result = $this->extractor->extract($text);

        $this->assertGreaterThanOrEqual(2, count($result['articles']));

        $paragraphs = array_filter($result['articles'], fn($a) => $a['type'] === 'paragraph');
        $this->assertCount(2, $paragraphs);
    }

    /** @test */
    public function it_extracts_point_references()
    {
        $text = 'točka 3 i t. 7';

        $result = $this->extractor->extract($text);

        $points = array_filter($result['articles'], fn($a) => $a['type'] === 'point');
        $this->assertCount(2, $points);
    }

    /** @test */
    public function it_extracts_case_numbers()
    {
        $text = 'U predmetu P-123/2023 i K-456/22 odlučeno je';

        $result = $this->extractor->extract($text);

        $this->assertCount(2, $result['case_numbers']);
        $this->assertEquals('P-123/2023', $result['case_numbers'][0]['full']);
        $this->assertEquals('K-456/22', $result['case_numbers'][1]['full']);
    }

    /** @test */
    public function it_extracts_court_types()
    {
        $text = 'Vrhovni sud Republike Hrvatske i Županijski sud u Zagrebu';

        $result = $this->extractor->extract($text);

        $this->assertGreaterThanOrEqual(2, count($result['court_types']));

        $types = array_column($result['court_types'], 'type');
        $this->assertContains('supreme', $types);
        $this->assertContains('county', $types);
    }

    /** @test */
    public function it_extracts_legal_terms()
    {
        $text = 'Tužitelj je podnio tužbu, a tuženik je dao odgovor na tužbu';

        $result = $this->extractor->extract($text);

        $this->assertGreaterThan(0, count($result['legal_terms']));

        $terms = array_column($result['legal_terms'], 'term');
        $this->assertContains('tužitelj', $terms);
        $this->assertContains('tužba', $terms);
    }

    /** @test */
    public function it_sets_has_specific_refs_flag_when_laws_or_cases_found()
    {
        $textWithRefs = 'Prema NN 123/45 i P-456/2022';
        $result = $this->extractor->extract($textWithRefs);
        $this->assertTrue($result['has_specific_refs']);

        $textWithoutRefs = 'neka opća pitanja o pravu';
        $result = $this->extractor->extract($textWithoutRefs);
        $this->assertFalse($result['has_specific_refs']);
    }

    /** @test */
    public function it_handles_empty_text()
    {
        $result = $this->extractor->extract('');

        $this->assertIsArray($result);
        $this->assertEmpty($result['laws']);
        $this->assertEmpty($result['articles']);
        $this->assertEmpty($result['case_numbers']);
        $this->assertEmpty($result['court_types']);
        $this->assertEmpty($result['legal_terms']);
        $this->assertFalse($result['has_specific_refs']);
    }

    /** @test */
    public function it_handles_mixed_content()
    {
        $text = 'U predmetu K-789/2023 Sud primjenjuje Zakon o obveznim odnosima (NN 35/05) članak 1045 stavak 1';

        $result = $this->extractor->extract($text);

        $this->assertGreaterThan(0, count($result['case_numbers']));
        $this->assertGreaterThan(0, count($result['laws']));
        $this->assertGreaterThan(0, count($result['articles']));
        $this->assertTrue($result['has_specific_refs']);
    }

    /** @test */
    public function it_extracts_all_common_law_abbreviations()
    {
        $text = 'ZOR, OZ, ZKP, KZ, OPZ, ZTR, ZZP, ZPP, ZIDTP';

        $result = $this->extractor->extract($text);

        $this->assertGreaterThanOrEqual(9, count($result['laws']));

        $abbreviations = array_column($result['laws'], 'abbreviation');
        $this->assertContains('ZOR', $abbreviations);
        $this->assertContains('ZKP', $abbreviations);
        $this->assertContains('KZ', $abbreviations);
    }

    /** @test */
    public function it_handles_case_insensitive_patterns()
    {
        $text = 'nn 123/45 i NN 67/89';

        $result = $this->extractor->extract($text);

        $this->assertCount(2, $result['laws']);
    }

    /** @test */
    public function it_extracts_complex_article_references()
    {
        $text = 'članka 1045. stavka 1. točke 3.';

        $result = $this->extractor->extract($text);

        $this->assertGreaterThan(0, count($result['articles']));

        $articles = array_filter($result['articles'], fn($a) => $a['type'] === 'article');
        $this->assertGreaterThan(0, count($articles));
    }

    /** @test */
    public function it_identifies_constitutional_court()
    {
        $text = 'Ustavni sud Republike Hrvatske';

        $result = $this->extractor->extract($text);

        $types = array_column($result['court_types'], 'type');
        $this->assertContains('constitutional', $types);
    }

    /** @test */
    public function it_identifies_high_commercial_court()
    {
        $text = 'Visoki trgovački sud Republike Hrvatske';

        $result = $this->extractor->extract($text);

        $types = array_column($result['court_types'], 'type');
        $this->assertContains('high_commercial', $types);
    }
}
