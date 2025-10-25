<?php

namespace Tests\Unit;

use App\Services\LawParser;
use PHPUnit\Framework\TestCase;

class LawParserEdgeCasesTest extends TestCase
{
    protected LawParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new LawParser();
    }

    /**
     * Test article with format "Članak 24. a)"
     */
    public function test_article_with_dotted_letter_and_parenthesis(): void
    {
        $html = <<<HTML
<body>
    <p>Članak 24. a) Prvi dio teksta</p>
    <p>Članak 25. Drugi članak</p>
</body>
HTML;

        $articles = $this->parser->splitIntoArticles($html);

        $this->assertCount(2, $articles);
        $this->assertEquals('24a', $articles[0]['number']);
        $this->assertStringContainsString('Prvi dio teksta', $articles[0]['html']);
        $this->assertEquals('25', $articles[1]['number']);
    }

    /**
     * Test uppercase format "CLANAK 5a"
     */
    public function test_uppercase_clanak_with_letter(): void
    {
        $html = <<<HTML
<body>
    <p>CLANAK 5a Tekst članka 5a</p>
    <p>CLANAK 6 Tekst članka 6</p>
</body>
HTML;

        $articles = $this->parser->splitIntoArticles($html);

        $this->assertCount(2, $articles);
        $this->assertEquals('5a', $articles[0]['number']);
        $this->assertStringContainsString('Tekst članka 5a', $articles[0]['html']);
        $this->assertEquals('6', $articles[1]['number']);
    }

    /**
     * Test missing body tag - should handle raw HTML
     */
    public function test_missing_body_tag(): void
    {
        $html = <<<HTML
<p>Članak 1. Prvi članak bez body taga</p>
<p>Članak 2. Drugi članak bez body taga</p>
HTML;

        $articles = $this->parser->splitIntoArticles($html);

        $this->assertCount(2, $articles);
        $this->assertEquals('1', $articles[0]['number']);
        $this->assertStringContainsString('Prvi članak bez body taga', $articles[0]['html']);
        $this->assertEquals('2', $articles[1]['number']);
    }

    /**
     * Test nested wrappers - articles wrapped in divs
     */
    public function test_nested_wrappers(): void
    {
        $html = <<<HTML
<body>
    <div class="wrapper">
        <div class="article">Članak 10. Tekst unutar nested divova</div>
    </div>
    <div class="wrapper">
        <span>Članak 11. Tekst unutar spana</span>
    </div>
</body>
HTML;

        $articles = $this->parser->splitIntoArticles($html);

        $this->assertCount(2, $articles);
        $this->assertEquals('10', $articles[0]['number']);
        $this->assertStringContainsString('Tekst unutar nested divova', $articles[0]['html']);
        $this->assertEquals('11', $articles[1]['number']);
    }

    /**
     * Test non-breaking spaces (Unicode U+00A0)
     */
    public function test_non_breaking_spaces(): void
    {
        // Non-breaking space: \xC2\xA0
        $html = '<body><p>Članak' . "\xC2\xA0" . '15.' . "\xC2\xA0" . 'Tekst sa non-breaking spaces</p></body>';

        $articles = $this->parser->splitIntoArticles($html);

        $this->assertCount(1, $articles);
        $this->assertEquals('15', $articles[0]['number']);
        $this->assertStringContainsString('Tekst sa non-breaking spaces', $articles[0]['html']);
        // Verify non-breaking spaces are normalized to regular spaces
        $this->assertStringNotContainsString("\xC2\xA0", $articles[0]['html']);
    }

    /**
     * Test NN markers remain in body
     */
    public function test_nn_markers_remain_in_body(): void
    {
        $html = <<<HTML
<body>
    <p>Članak 20. (NN 123/20) Tekst članka sa NN markerom</p>
</body>
HTML;

        $articles = $this->parser->splitIntoArticles($html);

        $this->assertCount(1, $articles);
        $this->assertEquals('20', $articles[0]['number']);
        $this->assertStringContainsString('(NN 123/20)', $articles[0]['html']);
        $this->assertStringContainsString('Tekst članka sa NN markerom', $articles[0]['html']);
    }

    /**
     * Test lettered articles are merged with base article
     */
    public function test_lettered_articles_merged_with_base(): void
    {
        $html = <<<HTML
<body>
    <p>Članak 24. Osnovni članak 24</p>
    <p>Članak 24a Dodani članak 24a</p>
    <p>Članak 24b Dodani članak 24b</p>
    <p>Članak 25. Sljedeći članak</p>
</body>
HTML;

        $articles = $this->parser->splitIntoArticles($html);

        $this->assertCount(2, $articles);
        // Base article 24 should include 24a and 24b
        $this->assertEquals('24', $articles[0]['number']);
        $this->assertStringContainsString('Osnovni članak 24', $articles[0]['html']);
        $this->assertStringContainsString('Dodani članak 24a', $articles[0]['html']);
        $this->assertStringContainsString('Dodani članak 24b', $articles[0]['html']);

        $this->assertEquals('25', $articles[1]['number']);
    }

    /**
     * Test lettered article without preceding base article
     */
    public function test_lettered_article_without_base(): void
    {
        $html = <<<HTML
<body>
    <p>Članak 5a Lettered article bez base</p>
    <p>Članak 6. Normalan članak</p>
</body>
HTML;

        $articles = $this->parser->splitIntoArticles($html);

        $this->assertCount(2, $articles);
        // Should create article with base number
        $this->assertEquals('5', $articles[0]['number']);
        $this->assertStringContainsString('Lettered article bez base', $articles[0]['html']);
        $this->assertEquals('6', $articles[1]['number']);
    }

    /**
     * Test article with parenthesis after number
     */
    public function test_article_with_parenthesis_after_number(): void
    {
        $html = <<<HTML
<body>
    <p>Članak 30) Tekst članka sa zagradom</p>
    <p>Članak 31. Normalan članak</p>
</body>
HTML;

        $articles = $this->parser->splitIntoArticles($html);

        $this->assertCount(2, $articles);
        $this->assertEquals('30', $articles[0]['number']);
        $this->assertStringContainsString('Tekst članka sa zagradom', $articles[0]['html']);
    }

    /**
     * Test article with opening parenthesis after number
     */
    public function test_article_with_opening_parenthesis(): void
    {
        $html = <<<HTML
<body>
    <p>Članak 40( (NN 100/21) Tekst sa otvorenom zagradom</p>
</body>
HTML;

        $articles = $this->parser->splitIntoArticles($html);

        $this->assertCount(1, $articles);
        $this->assertEquals('40', $articles[0]['number']);
        $this->assertStringContainsString('(NN 100/21)', $articles[0]['html']);
    }

    /**
     * Test mixed uppercase and lowercase article formats
     */
    public function test_mixed_case_articles(): void
    {
        $html = <<<HTML
<body>
    <p>Članak 1. Prvi članak</p>
    <p>CLANAK 2. Drugi članak uppercase</p>
    <p>Članak 3. Treći članak</p>
</body>
HTML;

        $articles = $this->parser->splitIntoArticles($html);

        $this->assertCount(3, $articles);
        $this->assertEquals('1', $articles[0]['number']);
        $this->assertEquals('2', $articles[1]['number']);
        $this->assertEquals('3', $articles[2]['number']);
    }

    /**
     * Test empty body - should return default article
     */
    public function test_empty_body(): void
    {
        $html = '<body></body>';

        $articles = $this->parser->splitIntoArticles($html);

        $this->assertCount(1, $articles);
        $this->assertEquals('1', $articles[0]['number']);
    }

    /**
     * Test preamble text before first article
     */
    public function test_preamble_text(): void
    {
        $html = <<<HTML
<body>
    <p>Ovo je preamble tekst koji dolazi prije prvog članka.</p>
    <p>Još preamble teksta.</p>
    <p>Članak 1. Prvi članak</p>
    <p>Članak 2. Drugi članak</p>
</body>
HTML;

        $articles = $this->parser->splitIntoArticles($html);

        $this->assertCount(2, $articles);
        $this->assertEquals('1', $articles[0]['number']);
        // Preamble should not be in any article since there's no previous article
        $this->assertStringNotContainsString('preamble', $articles[0]['html']);
    }

    /**
     * Test trailing text after last article
     */
    public function test_trailing_text(): void
    {
        $html = <<<HTML
<body>
    <p>Članak 1. Prvi članak</p>
    <p>Trailing tekst nakon članka</p>
</body>
HTML;

        $articles = $this->parser->splitIntoArticles($html);

        $this->assertCount(1, $articles);
        $this->assertEquals('1', $articles[0]['number']);
        // Trailing text should be appended to the last article
        $this->assertStringContainsString('Trailing tekst nakon članka', $articles[0]['html']);
    }

    /**
     * Test multiple spaces and whitespace normalization
     */
    public function test_whitespace_normalization(): void
    {
        $html = <<<HTML
<body>
    <p>Članak    50.     Tekst   sa   više   razmaka</p>
</body>
HTML;

        $articles = $this->parser->splitIntoArticles($html);

        $this->assertCount(1, $articles);
        $this->assertEquals('50', $articles[0]['number']);
        // Multiple spaces should be normalized to single spaces
        $this->assertStringNotContainsString('    ', $articles[0]['html']);
    }

    /**
     * Test article with dot and letter without space "24.a"
     */
    public function test_article_dot_letter_no_space(): void
    {
        $html = <<<HTML
<body>
    <p>Članak 35.a Tekst članka 35.a</p>
    <p>Članak 36. Sljedeći članak</p>
</body>
HTML;

        $articles = $this->parser->splitIntoArticles($html);

        $this->assertCount(2, $articles);
        $this->assertEquals('35', $articles[0]['number']);
        $this->assertStringContainsString('Tekst članka 35.a', $articles[0]['html']);
    }

    /**
     * Test complex scenario with all edge cases combined
     */
    public function test_complex_combined_scenario(): void
    {
        $html = <<<HTML
<body>
    <div class="wrapper">
        <p>Preamble tekst</p>
        <div>Članak 1. Prvi članak</div>
        <span>CLANAK 1a Dodani članak</span>
        <p>Članak 2. a) (NN 50/22) Drugi članak sa NN markerom</p>
        <div>Članak 3. Treći članak</div>
        <p>Završni tekst</p>
    </div>
</body>
HTML;

        $articles = $this->parser->splitIntoArticles($html);

        $this->assertCount(3, $articles);

        // Article 1 should include 1a
        $this->assertEquals('1', $articles[0]['number']);
        $this->assertStringContainsString('Prvi članak', $articles[0]['html']);
        $this->assertStringContainsString('Dodani članak', $articles[0]['html']);

        // Article 2 with NN marker
        $this->assertEquals('2a', $articles[1]['number']);
        $this->assertStringContainsString('(NN 50/22)', $articles[1]['html']);

        // Article 3 with trailing text
        $this->assertEquals('3', $articles[2]['number']);
        $this->assertStringContainsString('Završni tekst', $articles[2]['html']);
    }
}
