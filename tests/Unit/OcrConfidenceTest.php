<?php

namespace Tests\Unit;

use App\Services\Ocr\OcrQualityAnalyzer;
use Tests\TestCase;

/**
 * Unit tests for OCR quality analysis.
 */
class OcrConfidenceTest extends TestCase
{
    protected OcrQualityAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new OcrQualityAnalyzer();
    }

    /** @test */
    public function it_calculates_confidence_from_textract_blocks()
    {
        // Arrange: Create blocks with known confidence values
        $blocks = [
            ['BlockType' => 'WORD', 'Text' => 'Hello', 'Confidence' => 95.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'World', 'Confidence' => 92.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'Test', 'Confidence' => 88.0, 'Page' => 1],
            ['BlockType' => 'LINE', 'Text' => 'Ignored', 'Page' => 1], // Should be ignored
        ];

        // Act
        $result = $this->analyzer->analyzeFromBlocks($blocks);

        // Assert
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('coverage', $result);
        $this->assertArrayHasKey('total_words', $result);

        // Confidence = (95 + 92 + 88) / 3 / 100 = 0.9167
        $expectedConfidence = (95.0 + 92.0 + 88.0) / 3 / 100;
        $this->assertEquals(round($expectedConfidence, 4), $result['confidence']);

        // Coverage = 3/3 = 1.0 (all words have confidence)
        $this->assertEquals(1.0, $result['coverage']);

        // Total words
        $this->assertEquals(3, $result['total_words']);
    }

    /** @test */
    public function it_identifies_low_confidence_pages()
    {
        // Arrange: Page 1 has high confidence, Page 2 has low confidence
        $blocks = [
            // Page 1: High confidence (avg > 0.80)
            ['BlockType' => 'WORD', 'Text' => 'Good', 'Confidence' => 95.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'Quality', 'Confidence' => 93.0, 'Page' => 1],

            // Page 2: Low confidence (avg < 0.80)
            ['BlockType' => 'WORD', 'Text' => 'Bad', 'Confidence' => 65.0, 'Page' => 2],
            ['BlockType' => 'WORD', 'Text' => 'Quality', 'Confidence' => 70.0, 'Page' => 2],
        ];

        // Act
        $result = $this->analyzer->analyzeFromBlocks($blocks);

        // Assert
        $this->assertEquals(2, $result['total_pages']);
        $this->assertEquals(1, $result['low_confidence_pages']);
        $this->assertContains(2, $result['low_confidence_page_numbers']);
        $this->assertNotContains(1, $result['low_confidence_page_numbers']);
    }

    /** @test */
    public function it_handles_blocks_without_confidence()
    {
        // Arrange: Some blocks missing confidence
        $blocks = [
            ['BlockType' => 'WORD', 'Text' => 'Word1', 'Confidence' => 95.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'Word2', 'Page' => 1], // Missing confidence
            ['BlockType' => 'WORD', 'Text' => 'Word3', 'Confidence' => 90.0, 'Page' => 1],
        ];

        // Act
        $result = $this->analyzer->analyzeFromBlocks($blocks);

        // Assert
        $this->assertEquals(3, $result['total_words']);
        $this->assertEquals(2, $result['words_with_confidence']);

        // Coverage = 2/3 = 0.6667
        $this->assertEquals(0.6667, $result['coverage']);

        // Confidence = (95 + 90) / 2 / 100 = 0.925
        $this->assertEquals(0.925, $result['confidence']);
    }

    /** @test */
    public function it_estimates_quality_from_raw_text()
    {
        // Arrange: Good quality text
        $goodText = <<<TEXT
Članak 1. Opće odredbe

Ovim Zakonom uređuju se temeljna prava i slobode čovjeka i građanina.
Jamči se zaštita ljudskih prava i temeljnih sloboda sukladno Ustavu Republike Hrvatske.

Članak 2. Primjena Zakona

Odredbe ovog Zakona primjenjuju se na sve građane Republike Hrvatske bez
obzira na njihovu nacionalnu, vjersku ili političku pripadnost.
TEXT;

        // Act
        $result = $this->analyzer->estimateFromText($goodText);

        // Assert
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('coverage', $result);
        $this->assertTrue($result['estimated']);
        $this->assertEquals('text_heuristics', $result['method']);

        // Good text should have reasonable confidence
        $this->assertGreaterThan(0.6, $result['confidence']);
    }

    /** @test */
    public function it_detects_poor_quality_text()
    {
        // Arrange: Poor quality text (garbled, fragmented)
        $poorText = "a b c d\ne\nf\ng h\ni j\nk\nl m n o\np\nq r\ns";

        // Act
        $result = $this->analyzer->estimateFromText($poorText);

        // Assert: Poor quality text should have lower confidence
        $this->assertLessThan(0.7, $result['confidence']);
    }

    /** @test */
    public function it_returns_zero_metrics_for_empty_text()
    {
        // Act
        $result = $this->analyzer->estimateFromText('');

        // Assert
        $this->assertEquals(0.0, $result['confidence']);
        $this->assertEquals(0.0, $result['coverage']);
        $this->assertEquals(0, $result['total_words']);
    }

    /** @test */
    public function it_provides_page_statistics()
    {
        // Arrange: Multi-page document
        $blocks = [
            ['BlockType' => 'WORD', 'Text' => 'Page1Word1', 'Confidence' => 95.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'Page1Word2', 'Confidence' => 93.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'Page2Word1', 'Confidence' => 88.0, 'Page' => 2],
            ['BlockType' => 'WORD', 'Text' => 'Page2Word2', 'Confidence' => 85.0, 'Page' => 2],
            ['BlockType' => 'WORD', 'Text' => 'Page2Word3', 'Confidence' => 82.0, 'Page' => 2],
        ];

        // Act
        $result = $this->analyzer->analyzeFromBlocks($blocks);

        // Assert
        $this->assertArrayHasKey('page_stats', $result);

        $pageStats = $result['page_stats'];
        $this->assertArrayHasKey(1, $pageStats);
        $this->assertArrayHasKey(2, $pageStats);

        // Page 1: 2 words
        $this->assertEquals(2, $pageStats[1]['words']);
        $this->assertEquals(2, $pageStats[1]['words_with_confidence']);

        // Page 2: 3 words
        $this->assertEquals(3, $pageStats[2]['words']);
        $this->assertEquals(3, $pageStats[2]['words_with_confidence']);

        // Page 1 avg confidence: (95 + 93) / 2 / 100 = 0.94
        $this->assertEquals(0.94, $pageStats[1]['avg_confidence']);

        // Page 2 avg confidence: (88 + 85 + 82) / 3 / 100 = 0.85
        $this->assertEquals(0.85, $pageStats[2]['avg_confidence']);
    }

    /** @test */
    public function it_calculates_coverage_correctly()
    {
        // Arrange: 60% of words have confidence data
        $blocks = [
            ['BlockType' => 'WORD', 'Text' => 'Word1', 'Confidence' => 95.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'Word2', 'Confidence' => 93.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'Word3', 'Confidence' => 91.0, 'Page' => 1],
            ['BlockType' => 'WORD', 'Text' => 'Word4', 'Page' => 1], // Missing confidence
            ['BlockType' => 'WORD', 'Text' => 'Word5', 'Page' => 1], // Missing confidence
        ];

        // Act
        $result = $this->analyzer->analyzeFromBlocks($blocks);

        // Assert: Coverage = 3/5 = 0.6
        $this->assertEquals(0.6, $result['coverage']);
    }
}
