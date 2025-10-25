<?php

namespace Tests\Unit;

use App\Services\RagOrchestrator;
use App\Services\QueryNormalizer;
use App\Services\LegalCitations\HrLegalCitationsDetector;
use App\Services\GraphRagService;
use App\Services\OpenAIService;
use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Unit tests for MMR and RRF algorithms in RAG retrieval
 */
class RetrieverFusionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test RRF (Reciprocal Rank Fusion) correctness
     */
    public function test_rrf_fusion_correctness(): void
    {
        $orchestrator = $this->createOrchestratorMock();

        // Create test ranked lists
        $rankedLists = [
            'vector' => [
                ['id' => 'doc1', 'corpus' => 'laws', 'score' => 0.95, 'content' => 'content1'],
                ['id' => 'doc2', 'corpus' => 'laws', 'score' => 0.85, 'content' => 'content2'],
                ['id' => 'doc3', 'corpus' => 'laws', 'score' => 0.75, 'content' => 'content3'],
            ],
            'keyword' => [
                ['id' => 'doc2', 'corpus' => 'laws', 'score' => 0.90, 'content' => 'content2'],
                ['id' => 'doc1', 'corpus' => 'laws', 'score' => 0.80, 'content' => 'content1'],
                ['id' => 'doc4', 'corpus' => 'laws', 'score' => 0.70, 'content' => 'content4'],
            ],
        ];

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($orchestrator);
        $method = $reflection->getMethod('reciprocalRankFusion');
        $method->setAccessible(true);

        $result = $method->invoke($orchestrator, $rankedLists, 60);

        // Verify results
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        // doc1 and doc2 should rank higher as they appear in both lists
        $topDocs = array_slice($result, 0, 2);
        $topIds = array_map(fn($doc) => $doc['id'], $topDocs);

        $this->assertContains('doc1', $topIds, 'doc1 should be in top 2 (appears in both lists)');
        $this->assertContains('doc2', $topIds, 'doc2 should be in top 2 (appears in both lists)');

        // Verify RRF scores are calculated
        foreach ($result as $doc) {
            $this->assertArrayHasKey('rrf_score', $doc);
            $this->assertGreaterThan(0, $doc['rrf_score']);
        }
    }

    /**
     * Test RRF score calculation formula
     */
    public function test_rrf_score_calculation(): void
    {
        $orchestrator = $this->createOrchestratorMock();

        $rankedLists = [
            'method1' => [
                ['id' => 'doc1', 'corpus' => 'laws', 'score' => 1.0, 'content' => 'content'],
            ],
        ];

        $reflection = new \ReflectionClass($orchestrator);
        $method = $reflection->getMethod('reciprocalRankFusion');
        $method->setAccessible(true);

        $k = 60;
        $result = $method->invoke($orchestrator, $rankedLists, $k);

        // For a document at rank 0, RRF score should be 1/(k+0+1) = 1/61
        $expectedScore = 1.0 / ($k + 1);
        $actualScore = $result[0]['rrf_score'];

        $this->assertEqualsWithDelta(
            $expectedScore,
            $actualScore,
            0.0001,
            'RRF score should match formula: 1/(k+rank+1)'
        );
    }

    /**
     * Test MMR (Maximal Marginal Relevance) diversity
     */
    public function test_mmr_diversity(): void
    {
        $orchestrator = $this->createOrchestratorMock();

        // Create candidates with embeddings
        $candidates = [
            [
                'id' => 'doc1',
                'corpus' => 'laws',
                'score' => 0.95,
                'content' => 'similar content A',
                'embedding' => [0.9, 0.1, 0.0], // Very similar to doc2
            ],
            [
                'id' => 'doc2',
                'corpus' => 'laws',
                'score' => 0.90,
                'content' => 'similar content A variant',
                'embedding' => [0.85, 0.15, 0.0], // Very similar to doc1
            ],
            [
                'id' => 'doc3',
                'corpus' => 'laws',
                'score' => 0.80,
                'content' => 'different content B',
                'embedding' => [0.1, 0.1, 0.9], // Very different
            ],
        ];

        $queryEmbedding = [0.8, 0.2, 0.0];

        // Mock getDocumentEmbedding to return the embeddings we set
        $orchestratorPartial = Mockery::mock(RagOrchestrator::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $orchestratorPartial->shouldReceive('getDocumentEmbedding')
            ->andReturnUsing(function ($doc) {
                return $doc['embedding'] ?? null;
            });

        $reflection = new \ReflectionClass($orchestratorPartial);
        $method = $reflection->getMethod('maximalMarginalRelevance');
        $method->setAccessible(true);

        // Test with high lambda (favor relevance)
        $resultHighLambda = $method->invoke($orchestratorPartial, $candidates, $queryEmbedding, 0.9, 3);

        // Test with low lambda (favor diversity)
        $resultLowLambda = $method->invoke($orchestratorPartial, $candidates, $queryEmbedding, 0.1, 3);

        // With high lambda, should prefer more similar to query (doc1, doc2)
        // With low lambda, should diversify (doc1, doc3)
        $this->assertIsArray($resultHighLambda);
        $this->assertIsArray($resultLowLambda);

        // Verify MMR scores are calculated
        foreach ($resultHighLambda as $doc) {
            $this->assertArrayHasKey('mmr_score', $doc);
        }
    }

    /**
     * Test MMR selects top K items
     */
    public function test_mmr_respects_top_k(): void
    {
        $orchestrator = $this->createOrchestratorMock();

        $candidates = [];
        for ($i = 1; $i <= 20; $i++) {
            $candidates[] = [
                'id' => "doc{$i}",
                'corpus' => 'laws',
                'score' => 1.0 / $i,
                'content' => "content {$i}",
            ];
        }

        $queryEmbedding = array_fill(0, 10, 0.5);

        $reflection = new \ReflectionClass($orchestrator);
        $method = $reflection->getMethod('maximalMarginalRelevance');
        $method->setAccessible(true);

        $topK = 5;
        $result = $method->invoke($orchestrator, $candidates, $queryEmbedding, 0.5, $topK);

        $this->assertCount($topK, $result, "Should return exactly {$topK} results");
    }

    /**
     * Test cosine similarity calculation
     */
    public function test_cosine_similarity(): void
    {
        $orchestrator = $this->createOrchestratorMock();

        $reflection = new \ReflectionClass($orchestrator);
        $method = $reflection->getMethod('cosineSimilarity');
        $method->setAccessible(true);

        // Test identical vectors
        $vec1 = [1.0, 0.0, 0.0];
        $vec2 = [1.0, 0.0, 0.0];
        $similarity = $method->invoke($orchestrator, $vec1, $vec2);
        $this->assertEquals(1.0, $similarity, 'Identical vectors should have similarity 1.0');

        // Test orthogonal vectors
        $vec1 = [1.0, 0.0, 0.0];
        $vec2 = [0.0, 1.0, 0.0];
        $similarity = $method->invoke($orchestrator, $vec1, $vec2);
        $this->assertEquals(0.0, $similarity, 'Orthogonal vectors should have similarity 0.0');

        // Test opposite vectors
        $vec1 = [1.0, 0.0, 0.0];
        $vec2 = [-1.0, 0.0, 0.0];
        $similarity = $method->invoke($orchestrator, $vec1, $vec2);
        $this->assertEquals(-1.0, $similarity, 'Opposite vectors should have similarity -1.0');

        // Test partial similarity
        $vec1 = [1.0, 1.0, 0.0];
        $vec2 = [1.0, 0.0, 0.0];
        $similarity = $method->invoke($orchestrator, $vec1, $vec2);
        $this->assertGreaterThan(0.0, $similarity);
        $this->assertLessThan(1.0, $similarity);
    }

    /**
     * Test RRF stability with different orderings
     */
    public function test_rrf_stability(): void
    {
        $orchestrator = $this->createOrchestratorMock();

        $rankedLists1 = [
            'list1' => [
                ['id' => 'doc1', 'corpus' => 'laws', 'score' => 0.9, 'content' => 'c1'],
                ['id' => 'doc2', 'corpus' => 'laws', 'score' => 0.8, 'content' => 'c2'],
            ],
            'list2' => [
                ['id' => 'doc2', 'corpus' => 'laws', 'score' => 0.85, 'content' => 'c2'],
                ['id' => 'doc1', 'corpus' => 'laws', 'score' => 0.75, 'content' => 'c1'],
            ],
        ];

        // Same lists but in different order
        $rankedLists2 = [
            'list2' => [
                ['id' => 'doc2', 'corpus' => 'laws', 'score' => 0.85, 'content' => 'c2'],
                ['id' => 'doc1', 'corpus' => 'laws', 'score' => 0.75, 'content' => 'c1'],
            ],
            'list1' => [
                ['id' => 'doc1', 'corpus' => 'laws', 'score' => 0.9, 'content' => 'c1'],
                ['id' => 'doc2', 'corpus' => 'laws', 'score' => 0.8, 'content' => 'c2'],
            ],
        ];

        $reflection = new \ReflectionClass($orchestrator);
        $method = $reflection->getMethod('reciprocalRankFusion');
        $method->setAccessible(true);

        $result1 = $method->invoke($orchestrator, $rankedLists1, 60);
        $result2 = $method->invoke($orchestrator, $rankedLists2, 60);

        // Extract RRF scores
        $scores1 = [];
        $scores2 = [];
        foreach ($result1 as $doc) {
            $scores1[$doc['id']] = $doc['rrf_score'];
        }
        foreach ($result2 as $doc) {
            $scores2[$doc['id']] = $doc['rrf_score'];
        }

        // Scores should be identical regardless of input order
        foreach ($scores1 as $id => $score) {
            $this->assertArrayHasKey($id, $scores2);
            $this->assertEqualsWithDelta(
                $score,
                $scores2[$id],
                0.0001,
                "RRF scores should be stable regardless of input order"
            );
        }
    }

    /**
     * Test per-corpus caps application
     */
    public function test_corpus_caps_application(): void
    {
        $orchestrator = $this->createOrchestratorMock();

        $results = [
            ['id' => 'law1', 'corpus' => 'laws', 'score' => 0.9],
            ['id' => 'law2', 'corpus' => 'laws', 'score' => 0.85],
            ['id' => 'law3', 'corpus' => 'laws', 'score' => 0.8],
            ['id' => 'case1', 'corpus' => 'cases_documents', 'score' => 0.75],
            ['id' => 'case2', 'corpus' => 'cases_documents', 'score' => 0.7],
        ];

        $caps = [
            'laws' => 2,
            'cases_documents' => 1,
        ];

        $reflection = new \ReflectionClass($orchestrator);
        $method = $reflection->getMethod('applyCorpusCaps');
        $method->setAccessible(true);

        $capped = $method->invoke($orchestrator, $results, $caps);

        // Count results per corpus
        $counts = [];
        foreach ($capped as $result) {
            $corpus = $result['corpus'];
            $counts[$corpus] = ($counts[$corpus] ?? 0) + 1;
        }

        $this->assertLessThanOrEqual(2, $counts['laws'] ?? 0, 'Should cap laws to 2');
        $this->assertLessThanOrEqual(1, $counts['cases_documents'] ?? 0, 'Should cap cases to 1');
        $this->assertLessThanOrEqual(3, count($capped), 'Total should not exceed sum of caps');
    }

    /**
     * Test confidence calculation
     */
    public function test_confidence_calculation(): void
    {
        $orchestrator = $this->createOrchestratorMock();

        $results = [
            [
                'id' => 'doc1',
                'corpus' => 'laws',
                'content' => 'This is about pretres and nalog',
                'rrf_score' => 0.5,
                'retrieval_method' => 'graph_citation',
            ],
            [
                'id' => 'doc2',
                'corpus' => 'laws',
                'content' => 'Unrelated content',
                'score' => 0.3,
                'retrieval_method' => 'vector',
            ],
        ];

        $normalizedQuery = [
            'ključne_riječi' => ['pretres', 'nalog'],
        ];

        $citations = [];

        $reflection = new \ReflectionClass($orchestrator);
        $method = $reflection->getMethod('calculateConfidence');
        $method->setAccessible(true);

        $scored = $method->invoke($orchestrator, $results, $normalizedQuery, $citations);

        // All results should have confidence scores
        foreach ($scored as $result) {
            $this->assertArrayHasKey('confidence', $result);
            $this->assertGreaterThanOrEqual(0, $result['confidence']);
            $this->assertLessThanOrEqual(1, $result['confidence']);
        }

        // Graph citation should have higher confidence
        $this->assertGreaterThan(
            $scored[1]['confidence'] ?? 0,
            $scored[0]['confidence'] ?? 0,
            'Graph citation match should have higher confidence'
        );
    }

    /**
     * Test keyword score calculation
     */
    public function test_keyword_score_calculation(): void
    {
        $orchestrator = $this->createOrchestratorMock();

        $content = 'This document discusses pretres informatičkog uređaja and nalog requirements.';
        $keywords = ['pretres', 'nalog', 'sud'];

        $reflection = new \ReflectionClass($orchestrator);
        $method = $reflection->getMethod('calculateKeywordScore');
        $method->setAccessible(true);

        $score = $method->invoke($orchestrator, $content, $keywords);

        // Should match 2 out of 3 keywords
        $expectedScore = 2.0 / 3.0;
        $this->assertEqualsWithDelta($expectedScore, $score, 0.01, 'Should calculate correct keyword match ratio');

        // Test with no keywords
        $scoreNoKeywords = $method->invoke($orchestrator, $content, []);
        $this->assertEquals(0, $scoreNoKeywords, 'Should return 0 when no keywords provided');

        // Test with all matching keywords
        $allMatchingKeywords = ['pretres', 'nalog'];
        $scoreAllMatch = $method->invoke($orchestrator, $content, $allMatchingKeywords);
        $this->assertEquals(1.0, $scoreAllMatch, 'Should return 1.0 when all keywords match');
    }

    /**
     * Create a mock RagOrchestrator for testing
     */
    protected function createOrchestratorMock(): RagOrchestrator
    {
        $queryNormalizer = Mockery::mock(QueryNormalizer::class);
        $citationDetector = Mockery::mock(HrLegalCitationsDetector::class);
        $graphRag = Mockery::mock(GraphRagService::class);
        $openAI = Mockery::mock(OpenAIService::class);

        return new RagOrchestrator(
            $queryNormalizer,
            $citationDetector,
            $graphRag,
            $openAI
        );
    }
}
