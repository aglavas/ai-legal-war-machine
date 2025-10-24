<?php

namespace Tests\Unit;

use App\Services\LegalEntityExtractor;
use App\Services\OpenAIService;
use App\Services\QueryProcessingService;
use Mockery;
use Tests\TestCase;

class QueryProcessingServiceTest extends TestCase
{
    private QueryProcessingService $service;
    private $openAIMock;
    private $extractorMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->extractorMock = Mockery::mock(LegalEntityExtractor::class);

        $this->service = new QueryProcessingService(
            $this->openAIMock,
            $this->extractorMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_cleans_query_text()
    {
        // Mock extractor to return empty results
        $this->extractorMock
            ->shouldReceive('extract')
            ->andReturn([
                'laws' => [],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => [],
                'has_specific_refs' => false,
            ]);

        // Mock OpenAI to return a simple rewritten query
        $this->openAIMock
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'cleaned query']]
                ]
            ]);

        $result = $this->service->process('  Query   with   spaces  ');

        $this->assertArrayHasKey('original', $result);
        $this->assertArrayHasKey('cleaned', $result);
        $this->assertEquals('Query with spaces', $result['cleaned']);
    }

    /** @test */
    public function it_extracts_entities_from_query()
    {
        $expectedEntities = [
            'laws' => [
                ['type' => 'nn_reference', 'number' => '123', 'year' => '45', 'value' => 'NN 123/45']
            ],
            'articles' => [
                ['type' => 'article', 'number' => '10', 'value' => 'članak 10']
            ],
            'case_numbers' => [],
            'court_types' => [],
            'legal_terms' => ['tužba'],
            'has_specific_refs' => true,
        ];

        $this->extractorMock
            ->shouldReceive('extract')
            ->once()
            ->with(Mockery::type('string'))
            ->andReturn($expectedEntities);

        $this->openAIMock
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'rewritten query']]
                ]
            ]);

        $result = $this->service->process('Prema NN 123/45 članak 10');

        $this->assertArrayHasKey('entities', $result);
        $this->assertEquals($expectedEntities, $result['entities']);
        $this->assertTrue($result['has_specific_refs']);
    }

    /** @test */
    public function it_rewrites_query_using_openai()
    {
        $this->extractorMock
            ->shouldReceive('extract')
            ->andReturn([
                'laws' => [],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => [],
                'has_specific_refs' => false,
            ]);

        $this->openAIMock
            ->shouldReceive('chat')
            ->once()
            ->with(
                Mockery::on(function ($messages) {
                    return is_array($messages) &&
                           isset($messages[0]['role']) &&
                           $messages[0]['role'] === 'system';
                }),
                null,
                Mockery::on(function ($options) {
                    return isset($options['temperature']) &&
                           $options['temperature'] === 0.3 &&
                           isset($options['max_tokens']) &&
                           $options['max_tokens'] === 150;
                })
            )
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'This is the rewritten query']]
                ]
            ]);

        $result = $this->service->process('Original query');

        $this->assertArrayHasKey('rewritten', $result);
        $this->assertEquals('This is the rewritten query', $result['rewritten']);
    }

    /** @test */
    public function it_classifies_intent_as_law_lookup_when_law_patterns_found()
    {
        $this->extractorMock
            ->shouldReceive('extract')
            ->andReturn([
                'laws' => [],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => [],
                'has_specific_refs' => false,
            ]);

        $this->openAIMock
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'rewritten']]
                ]
            ]);

        $result = $this->service->process('Što kaže Zakon o obveznim odnosima članak 1045?');

        $this->assertArrayHasKey('intent', $result);
        $this->assertEquals('law_lookup', $result['intent']);
    }

    /** @test */
    public function it_classifies_intent_as_case_lookup_when_case_number_found()
    {
        $this->extractorMock
            ->shouldReceive('extract')
            ->andReturn([
                'laws' => [],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => [],
                'has_specific_refs' => false,
            ]);

        $this->openAIMock
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'rewritten']]
                ]
            ]);

        $result = $this->service->process('Što je odlučeno u predmetu P-123/2023?');

        $this->assertArrayHasKey('intent', $result);
        $this->assertEquals('case_lookup', $result['intent']);
    }

    /** @test */
    public function it_classifies_intent_as_definition_when_asking_about_meaning()
    {
        $this->extractorMock
            ->shouldReceive('extract')
            ->andReturn([
                'laws' => [],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => [],
                'has_specific_refs' => false,
            ]);

        $this->openAIMock
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'rewritten']]
                ]
            ]);

        $result = $this->service->process('Što znači pravna osobnost?');

        $this->assertArrayHasKey('intent', $result);
        $this->assertEquals('definition', $result['intent']);
    }

    /** @test */
    public function it_generates_search_variants()
    {
        $this->extractorMock
            ->shouldReceive('extract')
            ->andReturn([
                'laws' => [],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => ['ugovor'],
                'has_specific_refs' => false,
            ]);

        $this->openAIMock
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'rewritten query']]
                ]
            ]);

        $result = $this->service->process('Ugovori u poslovnom pravu');

        $this->assertArrayHasKey('search_variants', $result);
        $this->assertIsArray($result['search_variants']);
        $this->assertGreaterThan(0, count($result['search_variants']));
    }

    /** @test */
    public function it_handles_openai_failure_gracefully()
    {
        $this->extractorMock
            ->shouldReceive('extract')
            ->andReturn([
                'laws' => [],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => [],
                'has_specific_refs' => false,
            ]);

        $this->openAIMock
            ->shouldReceive('chat')
            ->andThrow(new \Exception('OpenAI API failed'));

        $result = $this->service->process('Test query');

        // Should still return a result with cleaned query
        $this->assertArrayHasKey('original', $result);
        $this->assertArrayHasKey('cleaned', $result);
        $this->assertArrayHasKey('entities', $result);
        // Rewritten should fall back to cleaned
        $this->assertEquals($result['cleaned'], $result['rewritten']);
    }

    /** @test */
    public function it_includes_all_required_fields_in_result()
    {
        $this->extractorMock
            ->shouldReceive('extract')
            ->andReturn([
                'laws' => [],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => [],
                'has_specific_refs' => false,
            ]);

        $this->openAIMock
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'rewritten']]
                ]
            ]);

        $result = $this->service->process('Test query');

        $requiredFields = [
            'original',
            'cleaned',
            'rewritten',
            'entities',
            'intent',
            'has_specific_refs',
            'search_variants',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $result, "Missing field: $field");
        }
    }

    /** @test */
    public function it_respects_agent_type_in_processing()
    {
        $this->extractorMock
            ->shouldReceive('extract')
            ->andReturn([
                'laws' => [],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => [],
                'has_specific_refs' => false,
            ]);

        $this->openAIMock
            ->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'rewritten']]
                ]
            ]);

        $result = $this->service->process('Test query', 'law');

        $this->assertIsArray($result);
        // Agent type should influence search variants
        $this->assertArrayHasKey('search_variants', $result);
    }
}
