<?php

namespace Tests\Unit;

use App\Services\LegalMetadata\KeyPhraseExtractor;
use Tests\TestCase;

class KeyPhraseExtractorTest extends TestCase
{
    public function test_extracts_common_croatian_legal_phrases(): void
    {
        $extractor = new KeyPhraseExtractor();

        $text = "\n            U IME   REPUBLIKE    HRVATSKE\n            Sud donosi presudu i obrazloženje.\n            Protiv ove presude dopušten je pravni lijek - žalba u roku od 15 dana.\n        ";

        $found = $extractor->extract($text);
        $this->assertNotEmpty($found, 'Should find at least one key phrase');

        // Index by key for easier assertions
        $byKey = [];
        foreach ($found as $item) {
            $byKey[$item['key']] = $item;
        }

        $this->assertArrayHasKey('u-ime-republike-hrvatske', $byKey);
        $this->assertSame('u ime republike hrvatske', $byKey['u-ime-republike-hrvatske']['phrase']);
        $this->assertGreaterThanOrEqual(1, $byKey['u-ime-republike-hrvatske']['count']);
        $this->assertStringContainsString('U IME', $byKey['u-ime-republike-hrvatske']['context']);

        $this->assertArrayHasKey('pravni-lijek', $byKey);
        $this->assertSame('pravni lijek', $byKey['pravni-lijek']['phrase']);
        $this->assertStringContainsString('pravni lijek', mb_strtolower($byKey['pravni-lijek']['context'], 'UTF-8'));

        $this->assertArrayHasKey('zalba', $byKey);
        $this->assertSame('žalba', $byKey['zalba']['phrase']);
        $this->assertStringContainsString('žalba', mb_strtolower($byKey['zalba']['context'], 'UTF-8'));
    }
}

