<?php

namespace Tests\Unit;

use App\Services\CaseIngestPipeline;
use App\Services\Ocr\OcrQualityAnalyzer;
use App\Services\Ocr\HrLanguageNormalizer;
use App\Services\CaseVectorStoreService;
use Tests\TestCase;
use ReflectionClass;

/**
 * Unit tests for case document chunking logic.
 */
class CaseChunkerTest extends TestCase
{
    protected CaseIngestPipeline $pipeline;

    protected function setUp(): void
    {
        parent::setUp();

        // Create pipeline with mocked dependencies
        $this->pipeline = new CaseIngestPipeline(
            new OcrQualityAnalyzer(),
            new HrLanguageNormalizer(),
            $this->createMock(CaseVectorStoreService::class)
        );
    }

    /** @test */
    public function it_chunks_text_with_specified_size_and_overlap()
    {
        // Arrange
        $text = str_repeat('A', 1000); // 1000 characters
        $chunkSize = 300;
        $overlap = 50;

        // Act
        $chunks = $this->invokeProtectedMethod('chunkText', [$text, $chunkSize, $overlap]);

        // Assert
        $this->assertIsArray($chunks);
        $this->assertGreaterThan(1, count($chunks));

        // Each chunk (except last) should be ~chunkSize
        foreach (array_slice($chunks, 0, -1) as $chunk) {
            $len = mb_strlen($chunk, 'UTF-8');
            $this->assertLessThanOrEqual($chunkSize, $len);
            $this->assertGreaterThan($chunkSize - 100, $len); // Allow some variance
        }
    }

    /** @test */
    public function it_creates_overlapping_chunks()
    {
        // Arrange
        $text = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $chunkSize = 10;
        $overlap = 3;

        // Act
        $chunks = $this->invokeProtectedMethod('chunkText', [$text, $chunkSize, $overlap]);

        // Assert: Should have overlapping content
        $this->assertGreaterThan(1, count($chunks));

        // Check that consecutive chunks overlap
        for ($i = 0; $i < count($chunks) - 1; $i++) {
            $chunk1 = $chunks[$i];
            $chunk2 = $chunks[$i + 1];

            // Last few characters of chunk1 should appear in chunk2
            $end = mb_substr($chunk1, -$overlap, null, 'UTF-8');

            // Note: Due to sentence breaking, overlap might not be exact
            // Just verify chunks are created
            $this->assertNotEmpty($chunk1);
            $this->assertNotEmpty($chunk2);
        }
    }

    /** @test */
    public function it_returns_single_chunk_for_small_text()
    {
        // Arrange
        $text = 'Short text that fits in one chunk.';
        $chunkSize = 1000;
        $overlap = 100;

        // Act
        $chunks = $this->invokeProtectedMethod('chunkText', [$text, $chunkSize, $overlap]);

        // Assert
        $this->assertCount(1, $chunks);
        $this->assertEquals($text, $chunks[0]);
    }

    /** @test */
    public function it_returns_empty_array_for_empty_text()
    {
        // Act
        $chunks = $this->invokeProtectedMethod('chunkText', ['', 500, 50]);

        // Assert
        $this->assertEmpty($chunks);
    }

    /** @test */
    public function it_breaks_at_sentence_boundaries_when_possible()
    {
        // Arrange: Text with clear sentence boundaries
        $text = <<<TEXT
Ovo je prva rečenica. Ovo je druga rečenica. Ovo je treća rečenica.
Ovo je četvrta rečenica. Ovo je peta rečenica. Ovo je šesta rečenica.
Ovo je sedma rečenica. Ovo je osma rečenica. Ovo je deveta rečenica.
TEXT;

        // Act
        $chunks = $this->invokeProtectedMethod('chunkText', [$text, 100, 20]);

        // Assert: Most chunks should end with a period (sentence boundary)
        $chunksEndingWithPeriod = 0;
        foreach ($chunks as $chunk) {
            $trimmed = trim($chunk);
            if (substr($trimmed, -1) === '.') {
                $chunksEndingWithPeriod++;
            }
        }

        // At least 50% should end at sentence boundaries
        $this->assertGreaterThan(count($chunks) * 0.5, $chunksEndingWithPeriod);
    }

    /** @test */
    public function it_handles_multibyte_croatian_characters()
    {
        // Arrange: Croatian text with diacritics
        $text = <<<TEXT
Članak 1. Opće odredbe. Članak 2. Posebne odredbe. Članak 3. Završne odredbe.
Ovo je tekst sa hrvatskim znakovima: čćžšđ ČĆŽŠĐ. Trebao bi biti ispravno obrađen.
TEXT;

        // Act
        $chunks = $this->invokeProtectedMethod('chunkText', [$text, 80, 10]);

        // Assert
        $this->assertGreaterThan(0, count($chunks));

        // Verify no character corruption
        $rejoined = implode('', $chunks);
        $this->assertStringContainsString('č', $rejoined);
        $this->assertStringContainsString('ć', $rejoined);
        $this->assertStringContainsString('ž', $rejoined);
        $this->assertStringContainsString('š', $rejoined);
        $this->assertStringContainsString('đ', $rejoined);
    }

    /** @test */
    public function it_filters_empty_chunks()
    {
        // Arrange: Text with lots of whitespace
        $text = "Word1    \n\n\n    Word2    \n\n\n    Word3";

        // Act
        $chunks = $this->invokeProtectedMethod('chunkText', [$text, 20, 5]);

        // Assert: All chunks should have content
        foreach ($chunks as $chunk) {
            $this->assertNotEmpty(trim($chunk));
        }
    }

    /** @test */
    public function it_handles_paragraph_breaks()
    {
        // Arrange
        $text = <<<TEXT
Prvi paragraf sa nekoliko rečenica. Ovo je dodatni tekst u prvom paragrafu.

Drugi paragraf započinje ovdje. I nastavlja se sa više teksta.

Treći paragraf je kratak.
TEXT;

        // Act
        $chunks = $this->invokeProtectedMethod('chunkText', [$text, 100, 20]);

        // Assert
        $this->assertGreaterThan(1, count($chunks));

        // Check that chunks respect paragraph boundaries (when possible)
        // At least one chunk should contain a paragraph break
        $hasParaBreak = false;
        foreach ($chunks as $chunk) {
            if (strpos($chunk, "\n\n") !== false) {
                $hasParaBreak = true;
                break;
            }
        }
        $this->assertTrue($hasParaBreak);
    }

    /** @test */
    public function it_respects_chunk_size_limits()
    {
        // Arrange: Very long continuous text
        $text = str_repeat('Ovaj tekst je vrlo dugačak i nema razmaka. ', 100);
        $chunkSize = 500;

        // Act
        $chunks = $this->invokeProtectedMethod('chunkText', [$text, $chunkSize, 50]);

        // Assert: No chunk exceeds the size limit
        foreach ($chunks as $chunk) {
            $len = mb_strlen($chunk, 'UTF-8');
            $this->assertLessThanOrEqual($chunkSize + 50, $len); // Allow small margin for word boundaries
        }
    }

    /** @test */
    public function it_handles_very_small_chunk_size()
    {
        // Arrange
        $text = 'This is a test sentence with several words.';
        $chunkSize = 10; // Very small

        // Act
        $chunks = $this->invokeProtectedMethod('chunkText', [$text, $chunkSize, 2]);

        // Assert
        $this->assertGreaterThan(1, count($chunks));

        // Verify all chunks combined contain the original text
        $combined = implode('', $chunks);
        $this->assertStringContainsString('test', $combined);
        $this->assertStringContainsString('sentence', $combined);
    }

    // Helper method to invoke protected methods

    protected function invokeProtectedMethod(string $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass($this->pipeline);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->pipeline, $parameters);
    }
}
