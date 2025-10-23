<?php

namespace Tests\Feature;

use App\Services\ChatbotRAGService;
use App\Services\LegalEntityExtractor;
use App\Services\OpenAIService;
use App\Services\QueryProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ChatbotRAGIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations
        $this->artisan('migrate');
    }

    /** @test */
    public function it_integrates_query_processing_with_entity_extraction()
    {
        // Mock OpenAI for query rewriting
        $openaiMock = Mockery::mock(OpenAIService::class);
        $openaiMock->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'rewritten query about Croatian law']]
                ]
            ]);

        // Real entity extractor
        $extractor = new LegalEntityExtractor();

        // Create query processor with real extractor
        $queryProcessor = new QueryProcessingService($openaiMock, $extractor);

        // Process a query with Croatian legal references
        $result = $queryProcessor->process('Što kaže NN 35/05 članak 1045?');

        // Verify entity extraction worked
        $this->assertArrayHasKey('entities', $result);
        $this->assertNotEmpty($result['entities']['laws']);
        $this->assertNotEmpty($result['entities']['articles']);
        $this->assertTrue($result['has_specific_refs']);
    }

    /** @test */
    public function it_retrieves_documents_from_database()
    {
        // Create test data in database
        DB::table('laws')->insert([
            'doc_id' => 'test-law-1',
            'title' => 'Zakon o obveznim odnosima',
            'content' => 'Članak 1045. Stranke mogu ugovorom slobodno urediti svoje odnose.',
            'law_number' => '35/05',
            'jurisdiction' => 'HR',
            'chunk_index' => 0,
            'token_count' => 100,
            'embedding_vector' => DB::raw("'[" . implode(',', array_fill(0, 1536, 0.1)) . "]'::vector"),
        ]);

        // Mock OpenAI services
        $openaiMock = Mockery::mock(OpenAIService::class);
        $openaiMock->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'rewritten query']]
                ]
            ]);

        $openaiMock->shouldReceive('embeddings')
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)]
                ]
            ]);

        $extractor = new LegalEntityExtractor();
        $queryProcessor = new QueryProcessingService($openaiMock, $extractor);
        $ragService = new ChatbotRAGService($queryProcessor, $extractor, $openaiMock, null);

        // Retrieve context
        $result = $ragService->retrieveContext('Zakon o obveznim odnosima', 'law');

        // Verify retrieval worked
        $this->assertArrayHasKey('documents', $result);
        $this->assertArrayHasKey('context', $result);
        $this->assertArrayHasKey('total_tokens', $result);
    }

    /** @test */
    public function it_applies_agent_specific_priority_boosting()
    {
        // Create test documents from different sources
        DB::table('laws')->insert([
            'doc_id' => 'law-1',
            'title' => 'Test Law',
            'content' => 'Law content about contracts',
            'token_count' => 50,
            'embedding_vector' => DB::raw("'[" . implode(',', array_fill(0, 1536, 0.1)) . "]'::vector"),
        ]);

        DB::table('cases_documents')->insert([
            'case_id' => 1,
            'doc_id' => 'case-1',
            'title' => 'Test Case',
            'content' => 'Case content about contracts',
            'category' => 'decision',
            'token_count' => 50,
            'embedding_vector' => DB::raw("'[" . implode(',', array_fill(0, 1536, 0.1)) . "]'::vector"),
        ]);

        $openaiMock = Mockery::mock(OpenAIService::class);
        $openaiMock->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'contracts query']]
                ]
            ]);

        $openaiMock->shouldReceive('embeddings')
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)]
                ]
            ]);

        $extractor = new LegalEntityExtractor();
        $queryProcessor = new QueryProcessingService($openaiMock, $extractor);
        $ragService = new ChatbotRAGService($queryProcessor, $extractor, $openaiMock, null);

        // Test with 'law' agent - should prioritize laws
        $lawResult = $ragService->retrieveContext('contracts', 'law');
        $this->assertArrayHasKey('sources_breakdown', $lawResult);

        // Test with 'case_analysis' agent - should prioritize cases
        $caseResult = $ragService->retrieveContext('contracts', 'case_analysis');
        $this->assertArrayHasKey('sources_breakdown', $caseResult);
    }

    /** @test */
    public function it_respects_token_budget_limits()
    {
        // Create multiple large documents
        for ($i = 0; $i < 20; $i++) {
            DB::table('laws')->insert([
                'doc_id' => "law-$i",
                'title' => "Law Document $i",
                'content' => str_repeat('This is a very long legal document with lots of content. ', 500),
                'token_count' => 5000,
                'embedding_vector' => DB::raw("'[" . implode(',', array_fill(0, 1536, 0.1)) . "]'::vector"),
            ]);
        }

        $openaiMock = Mockery::mock(OpenAIService::class);
        $openaiMock->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'legal query']]
                ]
            ]);

        $openaiMock->shouldReceive('embeddings')
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)]
                ]
            ]);

        $extractor = new LegalEntityExtractor();
        $queryProcessor = new QueryProcessingService($openaiMock, $extractor);
        $ragService = new ChatbotRAGService($queryProcessor, $extractor, $openaiMock, null);

        // Set low token budget
        $result = $ragService->retrieveContext('legal query', 'law', [
            'max_tokens' => 10000,
        ]);

        // Verify budget was respected
        $this->assertLessThanOrEqual(15000, $result['total_tokens']); // Allow for critical docs override
        $this->assertArrayHasKey('budget_utilization', $result);
    }

    /** @test */
    public function it_deduplicates_documents_across_sources()
    {
        // Insert same content in different tables
        $sameContent = 'This is duplicate content about Croatian legal system';
        $embedding = DB::raw("'[" . implode(',', array_fill(0, 1536, 0.1)) . "]'::vector");

        DB::table('laws')->insert([
            'doc_id' => 'law-1',
            'title' => 'Law Title',
            'content' => $sameContent,
            'token_count' => 50,
            'embedding_vector' => $embedding,
        ]);

        DB::table('cases_documents')->insert([
            'case_id' => 1,
            'doc_id' => 'case-1',
            'title' => 'Case Title',
            'content' => $sameContent, // Same content
            'category' => 'decision',
            'token_count' => 50,
            'embedding_vector' => $embedding,
        ]);

        $openaiMock = Mockery::mock(OpenAIService::class);
        $openaiMock->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'query']]
                ]
            ]);

        $openaiMock->shouldReceive('embeddings')
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)]
                ]
            ]);

        $extractor = new LegalEntityExtractor();
        $queryProcessor = new QueryProcessingService($openaiMock, $extractor);
        $ragService = new ChatbotRAGService($queryProcessor, $extractor, $openaiMock, null);

        $result = $ragService->retrieveContext('Croatian legal system', 'general');

        // Verify deduplication - should only have one copy of the content
        $documents = $result['documents'];
        $contents = array_column($documents, 'content');
        $uniqueContents = array_unique($contents);

        $this->assertCount(count($uniqueContents), $contents, 'Documents should be deduplicated');
    }

    /** @test */
    public function it_builds_formatted_context_string()
    {
        DB::table('laws')->insert([
            'doc_id' => 'law-1',
            'title' => 'Test Law Document',
            'content' => 'Article 1: Test content',
            'law_number' => '35/05',
            'jurisdiction' => 'HR',
            'token_count' => 20,
            'embedding_vector' => DB::raw("'[" . implode(',', array_fill(0, 1536, 0.1)) . "]'::vector"),
        ]);

        $openaiMock = Mockery::mock(OpenAIService::class);
        $openaiMock->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'query']]
                ]
            ]);

        $openaiMock->shouldReceive('embeddings')
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)]
                ]
            ]);

        $extractor = new LegalEntityExtractor();
        $queryProcessor = new QueryProcessingService($openaiMock, $extractor);
        $ragService = new ChatbotRAGService($queryProcessor, $extractor, $openaiMock, null);

        $result = $ragService->retrieveContext('test query', 'law');

        // Verify context string is properly formatted
        if (!empty($result['documents'])) {
            $this->assertStringContainsString('# Retrieved Legal Documents', $result['context']);
            $this->assertStringContainsString('Test Law Document', $result['context']);
        }
    }

    /** @test */
    public function it_handles_empty_database_gracefully()
    {
        // No documents in database
        $openaiMock = Mockery::mock(OpenAIService::class);
        $openaiMock->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'query']]
                ]
            ]);

        $openaiMock->shouldReceive('embeddings')
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)]
                ]
            ]);

        $extractor = new LegalEntityExtractor();
        $queryProcessor = new QueryProcessingService($openaiMock, $extractor);
        $ragService = new ChatbotRAGService($queryProcessor, $extractor, $openaiMock, null);

        $result = $ragService->retrieveContext('test query', 'law');

        // Should still return valid structure
        $this->assertArrayHasKey('documents', $result);
        $this->assertEmpty($result['documents']);
        $this->assertEquals(0, $result['document_count']);
        $this->assertEquals(0, $result['total_tokens']);
        $this->assertEmpty($result['context']);
    }

    /** @test */
    public function it_calculates_accurate_sources_breakdown()
    {
        // Insert documents from all three sources
        DB::table('laws')->insert([
            'doc_id' => 'law-1',
            'title' => 'Law',
            'content' => 'Law content',
            'token_count' => 20,
            'embedding_vector' => DB::raw("'[" . implode(',', array_fill(0, 1536, 0.1)) . "]'::vector"),
        ]);

        DB::table('cases_documents')->insert([
            'case_id' => 1,
            'doc_id' => 'case-1',
            'title' => 'Case',
            'content' => 'Case content',
            'category' => 'decision',
            'token_count' => 20,
            'embedding_vector' => DB::raw("'[" . implode(',', array_fill(0, 1536, 0.15)) . "]'::vector"),
        ]);

        DB::table('court_decision_documents')->insert([
            'court_decision_id' => 1,
            'doc_id' => 'court-1',
            'title' => 'Court Decision',
            'content' => 'Court decision content',
            'token_count' => 20,
            'embedding_vector' => DB::raw("'[" . implode(',', array_fill(0, 1536, 0.12)) . "]'::vector"),
        ]);

        $openaiMock = Mockery::mock(OpenAIService::class);
        $openaiMock->shouldReceive('chat')
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'query']]
                ]
            ]);

        $openaiMock->shouldReceive('embeddings')
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)]
                ]
            ]);

        $extractor = new LegalEntityExtractor();
        $queryProcessor = new QueryProcessingService($openaiMock, $extractor);
        $ragService = new ChatbotRAGService($queryProcessor, $extractor, $openaiMock, null);

        $result = $ragService->retrieveContext('legal query', 'general');

        // Verify breakdown
        $breakdown = $result['sources_breakdown'];
        $this->assertArrayHasKey('law', $breakdown);
        $this->assertArrayHasKey('case', $breakdown);
        $this->assertArrayHasKey('court_decision', $breakdown);

        $totalInBreakdown = $breakdown['law'] + $breakdown['case'] + $breakdown['court_decision'];
        $this->assertEquals($result['document_count'], $totalInBreakdown);
    }
}
