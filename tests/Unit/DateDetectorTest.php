<?php

namespace Tests\Unit;

use App\Services\LegalCitations\DateDetector;
use Tests\TestCase;

class DateDetectorTest extends TestCase
{
    public function test_detects_date_and_includes_context(): void
    {
        $detector = new DateDetector();
        $text = 'Dana 1. 2. 2023. donesena je odluka prvostupanjskog suda.';

        $results = $detector->detect($text);
        $this->assertNotEmpty($results, 'Should detect at least one date');

        $first = $results[0];
        $this->assertSame('2023-02-01', $first['iso']);
        $this->assertArrayHasKey('context', $first);
        $this->assertIsString($first['context']);
        $this->assertStringContainsString($first['raw'], $first['context']);
        $this->assertStringContainsString('Dana', $first['context']);
        $this->assertStringContainsString(' donesena', $first['context']);
    }

    public function test_detects_compact_date_and_context(): void
    {
        $detector = new DateDetector();
        $text = 'Objavljeno 31.12.1999 u Narodnim novinama.';

        $results = $detector->detect($text);
        $this->assertNotEmpty($results, 'Should detect compact date format');

        $first = $results[0];
        $this->assertSame('1999-12-31', $first['iso']);
        $this->assertArrayHasKey('context', $first);
        $this->assertStringContainsString('Objavljeno', $first['context']);
        $this->assertStringContainsString(' u Narodnim', $first['context']);
    }
}

