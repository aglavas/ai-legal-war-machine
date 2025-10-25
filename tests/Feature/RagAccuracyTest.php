<?php

namespace Tests\Feature;

use App\Services\RagOrchestrator;
use App\Services\QueryNormalizer;
use App\Services\LegalCitations\HrLegalCitationsDetector;
use App\Services\GraphRagService;
use App\Services\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Test RAG accuracy with curated Q/A sets and expected citations
 */
class RagAccuracyTest extends TestCase
{
    use RefreshDatabase;

    protected RagOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize test data
        $this->seedTestLegalDocuments();

        // Mock OpenAI embeddings for testing
        $this->fakeOpenAIResponses();
    }

    /**
     * Seed database with test legal documents
     */
    protected function seedTestLegalDocuments(): void
    {
        // Create test law document
        DB::table('laws')->insert([
            'id' => '01JCHG1000000000000000001',
            'doc_id' => 'zkp-2008',
            'title' => 'Zakon o kaznenom postupku',
            'law_number' => '152/08',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Članak 331. (1) Pretres stana ili drugih prostorija može se obaviti samo na temelju pisanog naloga suda. (2) Nalog mora sadržavati oznaku prostorije koja će se pretražiti i razloge pretresa.',
            'metadata' => json_encode([
                'article' => '331',
                'chapter' => 'Mjere prisile',
            ]),
            'embedding_vector' => json_encode($this->generateTestEmbedding('pretres stana nalog')),
            'chunk_index' => 0,
            'content_hash' => hash('sha256', 'zkp-331-1'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('laws')->insert([
            'id' => '01JCHG1000000000000000002',
            'doc_id' => 'zkp-2008',
            'title' => 'Zakon o kaznenom postupku',
            'law_number' => '152/08',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Članak 263. (1) Pretres informatičkog uređaja može se obaviti samo uz prisutnost vještaka. (2) Tijekom pretresa mora se osigurati očuvanje digitalnih dokaza.',
            'metadata' => json_encode([
                'article' => '263',
                'chapter' => 'Mjere prisile',
            ]),
            'embedding_vector' => json_encode($this->generateTestEmbedding('pretres informatički uređaj digitalni dokazi')),
            'chunk_index' => 0,
            'content_hash' => hash('sha256', 'zkp-263-1'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create test case document
        DB::table('cases_documents')->insert([
            'id' => '01JCHG1000000000000000003',
            'case_id' => 1,
            'doc_id' => 'Pp-2343/2025',
            'title' => 'Nalog za pretres mobilnog uređaja',
            'content' => 'Sud je izdao nalog za pretres mobilnog telefona marke iPhone 12, IMEI 123456789012345. Pretres će obaviti vještak prema članku 263. ZKP.',
            'metadata' => json_encode([
                'case_type' => 'criminal',
                'device_type' => 'mobile_phone',
            ]),
            'embedding_vector' => json_encode($this->generateTestEmbedding('nalog pretres mobitel iPhone')),
            'chunk_index' => 0,
            'content_hash' => hash('sha256', 'case-pp-2343'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Generate deterministic test embedding based on text
     */
    protected function generateTestEmbedding(string $text): array
    {
        $hash = crc32($text);
        $embedding = [];
        for ($i = 0; $i < 10; $i++) {
            $embedding[] = (($hash >> ($i * 3)) & 0xFF) / 255.0;
        }
        return $embedding;
    }

    /**
     * Mock OpenAI API responses
     */
    protected function fakeOpenAIResponses(): void
    {
        Http::fake([
            'https://api.openai.com/v1/embeddings' => function ($request) {
                $payload = $request->data();
                $input = $payload['input'] ?? '';

                return Http::response([
                    'data' => [
                        ['embedding' => $this->generateTestEmbedding($input)],
                    ],
                    'model' => 'text-embedding-3-small',
                    'usage' => ['total_tokens' => 100],
                ], 200);
            },
            'https://api.openai.com/v1/chat/completions' => function ($request) {
                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => 'Prema članku 263. ZKP, pretres informatičkog uređaja zahtijeva prisutnost vještaka [1]. Nalog mora izdati sud [2].',
                            ],
                        ],
                    ],
                    'usage' => ['total_tokens' => 150],
                ], 200);
            },
        ]);
    }

    /**
     * Curated Q/A test cases with expected citations
     */
    protected function getCuratedQASet(): array
    {
        return [
            [
                'question' => 'Tko može izdati nalog za pretres stana?',
                'expected_citations' => ['zkp-2008:čl.331'],
                'expected_keywords' => ['nalog', 'sud', 'pretres'],
                'min_accuracy' => 0.8,
            ],
            [
                'question' => 'Kako se obavlja pretres mobilnog telefona?',
                'expected_citations' => ['zkp-2008:čl.263'],
                'expected_keywords' => ['pretres', 'informatički', 'vještak'],
                'min_accuracy' => 0.7,
            ],
            [
                'question' => 'Koji postupak se primjenjuje kod pretresa iPhone uređaja u predmetu Pp-2343/2025?',
                'expected_citations' => ['Pp-2343/2025', 'zkp-2008:čl.263'],
                'expected_keywords' => ['iPhone', 'pretres', 'vještak'],
                'min_accuracy' => 0.9,
            ],
        ];
    }

    /**
     * Test accuracy@k - how many of top K results contain expected citations
     */
    public function test_accuracy_at_k(): void
    {
        $orchestrator = app(RagOrchestrator::class);
        $qaSet = $this->getCuratedQASet();

        $accuracies = [];

        foreach ($qaSet as $qa) {
            $result = $orchestrator->retrieve($qa['question'], [
                'top_k' => 5,
                'vector_limit' => 20,
                'keyword_limit' => 10,
            ]);

            $retrievedChunks = $result['chunks'] ?? [];
            $expectedCitations = $qa['expected_citations'];

            // Check if expected citations are in top K results
            $foundCitations = 0;
            foreach ($retrievedChunks as $chunk) {
                $docId = $chunk['doc_id'] ?? '';

                foreach ($expectedCitations as $expectedCitation) {
                    if (str_contains($docId, $expectedCitation) ||
                        str_contains($expectedCitation, $docId)) {
                        $foundCitations++;
                        break;
                    }
                }
            }

            $accuracy = count($expectedCitations) > 0
                ? $foundCitations / count($expectedCitations)
                : 0;

            $accuracies[] = $accuracy;

            // Assert minimum accuracy for this question
            $this->assertGreaterThanOrEqual(
                $qa['min_accuracy'],
                $accuracy,
                "Failed to achieve minimum accuracy for: {$qa['question']}"
            );
        }

        // Average accuracy across all questions
        $avgAccuracy = array_sum($accuracies) / count($accuracies);
        $this->assertGreaterThanOrEqual(0.75, $avgAccuracy, 'Average accuracy across all questions too low');
    }

    /**
     * Test coverage - how many relevant documents are retrieved
     */
    public function test_retrieval_coverage(): void
    {
        $orchestrator = app(RagOrchestrator::class);

        $result = $orchestrator->retrieve('Kako se obavlja pretres mobilnog telefona?', [
            'top_k' => 10,
        ]);

        $chunks = $result['chunks'] ?? [];

        // Should retrieve at least 2 relevant documents (law + case example)
        $this->assertGreaterThanOrEqual(2, count($chunks), 'Insufficient coverage - too few documents retrieved');

        // Check that both laws and cases are represented
        $corpora = array_unique(array_column($chunks, 'corpus'));
        $this->assertContains('laws', $corpora, 'Should retrieve laws');
    }

    /**
     * Test confidence scoring accuracy
     */
    public function test_confidence_scoring(): void
    {
        $orchestrator = app(RagOrchestrator::class);

        // High-relevance query
        $result = $orchestrator->retrieve('Pretres informatičkog uređaja prema članku 263 ZKP', [
            'top_k' => 5,
        ]);

        $chunks = $result['chunks'] ?? [];
        $this->assertNotEmpty($chunks);

        // Top result should have high confidence
        $topChunk = $chunks[0];
        $this->assertArrayHasKey('confidence', $topChunk);
        $this->assertGreaterThan(0.5, $topChunk['confidence'], 'Top result should have confidence > 0.5');

        // Confidence should decrease with rank (generally)
        $confidences = array_column($chunks, 'confidence');
        $avgFirstHalf = array_sum(array_slice($confidences, 0, 2)) / 2;
        $avgSecondHalf = count($confidences) > 2
            ? array_sum(array_slice($confidences, 2)) / max(1, count($confidences) - 2)
            : 0;

        $this->assertGreaterThanOrEqual(
            $avgSecondHalf,
            $avgFirstHalf,
            'First half of results should have higher average confidence'
        );
    }

    /**
     * Test citation detection in queries
     */
    public function test_citation_detection_in_queries(): void
    {
        $orchestrator = app(RagOrchestrator::class);

        $result = $orchestrator->retrieve('Što kaže članak 331. st. 1. ZKP o nalogu za pretres?', [
            'top_k' => 5,
        ]);

        $citations = $result['citations_detected'] ?? [];

        // Should detect statute citation
        $this->assertNotEmpty($citations['statutes'] ?? [], 'Should detect statute citations');

        $queryAnalysis = $result['query_analysis'] ?? [];
        $this->assertNotEmpty($queryAnalysis['članci_prioritet'] ?? [], 'Should extract citation priorities');
    }

    /**
     * Test retrieval statistics
     */
    public function test_retrieval_statistics(): void
    {
        $orchestrator = app(RagOrchestrator::class);

        $result = $orchestrator->retrieve('Pretres mobilnog telefona', [
            'top_k' => 10,
        ]);

        $stats = $result['retrieval_stats'] ?? [];

        $this->assertArrayHasKey('vector_results', $stats);
        $this->assertArrayHasKey('keyword_results', $stats);
        $this->assertArrayHasKey('merged_results', $stats);
        $this->assertArrayHasKey('final_results', $stats);

        // Vector search should return results
        $this->assertGreaterThan(0, $stats['vector_results'], 'Vector search should return results');

        // Final results should be reasonable
        $this->assertLessThanOrEqual(
            $stats['merged_results'],
            $stats['final_results'],
            'Final results should not exceed merged results'
        );
    }

    /**
     * Test per-corpus caps
     */
    public function test_per_corpus_caps(): void
    {
        $orchestrator = app(RagOrchestrator::class);

        $result = $orchestrator->retrieve('Pretres mobilnog telefona', [
            'top_k' => 20,
            'corpus_caps' => [
                'laws' => 2,
                'cases_documents' => 1,
            ],
        ]);

        $chunks = $result['chunks'] ?? [];

        // Count chunks per corpus
        $corpusCounts = [];
        foreach ($chunks as $chunk) {
            $corpus = $chunk['corpus'];
            $corpusCounts[$corpus] = ($corpusCounts[$corpus] ?? 0) + 1;
        }

        // Verify caps are respected
        $this->assertLessThanOrEqual(2, $corpusCounts['laws'] ?? 0, 'Laws corpus cap should be enforced');
        $this->assertLessThanOrEqual(1, $corpusCounts['cases_documents'] ?? 0, 'Cases corpus cap should be enforced');
    }

    /**
     * Test end-to-end grounded response generation
     */
    public function test_end_to_end_grounded_response(): void
    {
        $orchestrator = app(RagOrchestrator::class);
        $openAI = app(OpenAIService::class);

        // Retrieve relevant chunks
        $result = $orchestrator->retrieve('Kako se obavlja pretres informatičkog uređaja?', [
            'top_k' => 5,
        ]);

        $chunks = $result['chunks'] ?? [];
        $this->assertNotEmpty($chunks);

        // Generate grounded response
        $response = $openAI->groundedChatCompletion(
            'Kako se obavlja pretres informatičkog uređaja?',
            $chunks,
            [
                'min_confidence' => 0.3,
                'query_analysis' => $result['query_analysis'],
            ]
        );

        $this->assertTrue($response['grounded'], 'Response should be grounded');
        $this->assertArrayHasKey('answer', $response);
        $this->assertArrayHasKey('citations', $response);
        $this->assertNotEmpty($response['answer']);
        $this->assertNotEmpty($response['citations']);
    }

    /**
     * Test low-confidence refusal
     */
    public function test_low_confidence_refusal(): void
    {
        $openAI = app(OpenAIService::class);

        // Simulate low-confidence scenario with empty chunks
        $response = $openAI->groundedChatCompletion(
            'Neko potpuno irelevantno pitanje o svemiru',
            [], // No relevant chunks
            [
                'min_confidence' => 0.5,
                'query_analysis' => [],
            ]
        );

        $this->assertFalse($response['grounded'], 'Should not be grounded when confidence is low');
        $this->assertArrayHasKey('refusal', $response);
        $this->assertArrayHasKey('clarifications', $response);
        $this->assertNotEmpty($response['refusal']);
    }

    /**
     * Test metadata enrichment
     */
    public function test_metadata_enrichment(): void
    {
        $orchestrator = app(RagOrchestrator::class);

        $result = $orchestrator->retrieve('Pretres stana', [
            'top_k' => 3,
        ]);

        $chunks = $result['chunks'] ?? [];
        $this->assertNotEmpty($chunks);

        $chunk = $chunks[0];

        // Check for enriched metadata
        $this->assertArrayHasKey('_metadata', $chunk);
        $this->assertArrayHasKey('chunk_length', $chunk['_metadata']);
        $this->assertArrayHasKey('corpus_type', $chunk['_metadata']);
    }
}
