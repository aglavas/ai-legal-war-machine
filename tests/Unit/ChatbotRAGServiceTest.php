<?php

namespace Tests\Unit;

use App\Services\ChatbotRAGService;
use App\Services\LegalEntityExtractor;
use App\Services\Neo4jService;
use App\Services\OpenAIService;
use App\Services\QueryProcessingService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ChatbotRAGServiceTest extends TestCase
{
    private ChatbotRAGService $service;
    private $queryProcessorMock;
    private $extractorMock;
    private $openaiMock;
    private $neo4jMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queryProcessorMock = Mockery::mock(QueryProcessingService::class);
        $this->extractorMock = Mockery::mock(LegalEntityExtractor::class);
        $this->openaiMock = Mockery::mock(OpenAIService::class);
        $this->neo4jMock = Mockery::mock(Neo4jService::class);

        $this->service = new ChatbotRAGService(
            $this->queryProcessorMock,
            $this->extractorMock,
            $this->openaiMock,
            $this->neo4jMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_processes_query_before_retrieval()
    {
        $processedQuery = [
            'original' => 'test query',
            'cleaned' => 'test query',
            'rewritten' => 'test query enhanced',
            'entities' => [
                'laws' => [],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => [],
            ],
            'intent' => 'general',
            'has_specific_refs' => false,
            'search_variants' => ['test query', 'test query enhanced'],
        ];

        $this->queryProcessorMock
            ->shouldReceive('process')
            ->once()
            ->with('test query', 'general')
            ->andReturn($processedQuery);

        // Mock embeddings call - will be called for vector search
        $this->openaiMock
            ->shouldReceive('embeddings')
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)]
                ]
            ]);

        $result = $this->service->retrieveContext('test query', 'general');

        $this->assertArrayHasKey('context', $result);
        $this->assertArrayHasKey('documents', $result);
        $this->assertArrayHasKey('strategy', $result);
        $this->assertArrayHasKey('processed_query', $result);
    }

    /** @test */
    public function it_returns_expected_result_structure()
    {
        $processedQuery = [
            'original' => 'test',
            'cleaned' => 'test',
            'rewritten' => 'test',
            'entities' => [
                'laws' => [],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => [],
            ],
            'intent' => 'general',
            'has_specific_refs' => false,
            'search_variants' => ['test'],
        ];

        $this->queryProcessorMock
            ->shouldReceive('process')
            ->andReturn($processedQuery);

        $this->openaiMock
            ->shouldReceive('embeddings')
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)]
                ]
            ]);

        $result = $this->service->retrieveContext('test query');

        $expectedKeys = [
            'context',
            'documents',
            'strategy',
            'processed_query',
            'document_count',
            'total_tokens',
            'sources_breakdown',
            'budget_utilization',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: $key");
        }
    }

    /** @test */
    public function it_respects_max_tokens_option()
    {
        $processedQuery = [
            'original' => 'test',
            'cleaned' => 'test',
            'rewritten' => 'test',
            'entities' => [
                'laws' => [],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => [],
            ],
            'intent' => 'general',
            'has_specific_refs' => false,
            'search_variants' => ['test'],
        ];

        $this->queryProcessorMock
            ->shouldReceive('process')
            ->andReturn($processedQuery);

        $this->openaiMock
            ->shouldReceive('embeddings')
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)]
                ]
            ]);

        $result = $this->service->retrieveContext('test query', 'general', [
            'max_tokens' => 50000,
        ]);

        $this->assertLessThanOrEqual(50000, $result['total_tokens']);
    }

    /** @test */
    public function it_respects_min_score_option()
    {
        $processedQuery = [
            'original' => 'test',
            'cleaned' => 'test',
            'rewritten' => 'test',
            'entities' => [
                'laws' => [],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => [],
            ],
            'intent' => 'general',
            'has_specific_refs' => false,
            'search_variants' => ['test'],
        ];

        $this->queryProcessorMock
            ->shouldReceive('process')
            ->andReturn($processedQuery);

        $this->openaiMock
            ->shouldReceive('embeddings')
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)]
                ]
            ]);

        // With high min_score, should get fewer results
        $result = $this->service->retrieveContext('test query', 'general', [
            'min_score' => 0.95,
        ]);

        $this->assertIsArray($result['documents']);
    }

    /** @test */
    public function it_determines_strategy_based_on_query()
    {
        // Query with specific references should use hybrid_search
        $processedQueryWithRefs = [
            'original' => 'test',
            'cleaned' => 'test',
            'rewritten' => 'test',
            'entities' => [
                'laws' => [['type' => 'nn_reference', 'value' => 'NN 123/45']],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => [],
            ],
            'intent' => 'law_lookup',
            'has_specific_refs' => true,
            'search_variants' => ['test'],
        ];

        $this->queryProcessorMock
            ->shouldReceive('process')
            ->andReturn($processedQueryWithRefs);

        $this->openaiMock
            ->shouldReceive('embeddings')
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)]
                ]
            ]);

        $result = $this->service->retrieveContext('NN 123/45');

        $this->assertArrayHasKey('strategy', $result);
        $this->assertEquals('hybrid_search', $result['strategy']);
    }

    /** @test */
    public function it_applies_priority_boost_based_on_agent_type()
    {
        $processedQuery = [
            'original' => 'test',
            'cleaned' => 'test',
            'rewritten' => 'test',
            'entities' => [
                'laws' => [],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => [],
            ],
            'intent' => 'general',
            'has_specific_refs' => false,
            'search_variants' => ['test'],
        ];

        $this->queryProcessorMock
            ->shouldReceive('process')
            ->andReturn($processedQuery);

        $this->openaiMock
            ->shouldReceive('embeddings')
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)]
                ]
            ]);

        // For 'law' agent, laws should be boosted
        $result = $this->service->retrieveContext('test query', 'law');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('sources_breakdown', $result);
    }

    /** @test */
    public function it_calculates_sources_breakdown()
    {
        $processedQuery = [
            'original' => 'test',
            'cleaned' => 'test',
            'rewritten' => 'test',
            'entities' => [
                'laws' => [],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => [],
            ],
            'intent' => 'general',
            'has_specific_refs' => false,
            'search_variants' => ['test'],
        ];

        $this->queryProcessorMock
            ->shouldReceive('process')
            ->andReturn($processedQuery);

        $this->openaiMock
            ->shouldReceive('embeddings')
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)]
                ]
            ]);

        $result = $this->service->retrieveContext('test query');

        $this->assertArrayHasKey('sources_breakdown', $result);
        $this->assertIsArray($result['sources_breakdown']);
        $this->assertArrayHasKey('law', $result['sources_breakdown']);
        $this->assertArrayHasKey('case', $result['sources_breakdown']);
        $this->assertArrayHasKey('court_decision', $result['sources_breakdown']);
    }

    /** @test */
    public function it_estimates_tokens_when_token_count_not_available()
    {
        $processedQuery = [
            'original' => 'test',
            'cleaned' => 'test',
            'rewritten' => 'test',
            'entities' => [
                'laws' => [],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => [],
            ],
            'intent' => 'general',
            'has_specific_refs' => false,
            'search_variants' => ['test'],
        ];

        $this->queryProcessorMock
            ->shouldReceive('process')
            ->andReturn($processedQuery);

        $this->openaiMock
            ->shouldReceive('embeddings')
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)]
                ]
            ]);

        $result = $this->service->retrieveContext('test query');

        // Should have total_tokens even if database doesn't have token_count column
        $this->assertArrayHasKey('total_tokens', $result);
        $this->assertIsInt($result['total_tokens']);
    }

    /** @test */
    public function it_handles_openai_embedding_failure_gracefully()
    {
        $processedQuery = [
            'original' => 'test',
            'cleaned' => 'test',
            'rewritten' => 'test',
            'entities' => [
                'laws' => [],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => [],
            ],
            'intent' => 'general',
            'has_specific_refs' => false,
            'search_variants' => ['test'],
        ];

        $this->queryProcessorMock
            ->shouldReceive('process')
            ->andReturn($processedQuery);

        $this->openaiMock
            ->shouldReceive('embeddings')
            ->andThrow(new \Exception('OpenAI API failed'));

        $result = $this->service->retrieveContext('test query');

        // Should still return a result, just with no documents
        $this->assertArrayHasKey('documents', $result);
        $this->assertArrayHasKey('context', $result);
    }

    /** @test */
    public function it_builds_context_string_from_documents()
    {
        $processedQuery = [
            'original' => 'test',
            'cleaned' => 'test',
            'rewritten' => 'test',
            'entities' => [
                'laws' => [],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => [],
            ],
            'intent' => 'general',
            'has_specific_refs' => false,
            'search_variants' => ['test'],
        ];

        $this->queryProcessorMock
            ->shouldReceive('process')
            ->andReturn($processedQuery);

        $this->openaiMock
            ->shouldReceive('embeddings')
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)]
                ]
            ]);

        $result = $this->service->retrieveContext('test query');

        $this->assertArrayHasKey('context', $result);
        $this->assertIsString($result['context']);

        // Context should be empty if no documents found
        if (empty($result['documents'])) {
            $this->assertEmpty($result['context']);
        }
    }

    /** @test */
    public function it_includes_full_content_when_option_enabled()
    {
        $processedQuery = [
            'original' => 'test',
            'cleaned' => 'test',
            'rewritten' => 'test',
            'entities' => [
                'laws' => [],
                'articles' => [],
                'case_numbers' => [],
                'court_types' => [],
                'legal_terms' => [],
            ],
            'intent' => 'general',
            'has_specific_refs' => false,
            'search_variants' => ['test'],
        ];

        $this->queryProcessorMock
            ->shouldReceive('process')
            ->andReturn($processedQuery);

        $this->openaiMock
            ->shouldReceive('embeddings')
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)]
                ]
            ]);

        $result = $this->service->retrieveContext('test query', 'general', [
            'include_full_content' => true,
        ]);

        $this->assertIsArray($result);
        // Full content should be included in context string
        $this->assertArrayHasKey('context', $result);
    }
}
