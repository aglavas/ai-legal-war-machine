<?php

namespace Tests\Unit\Jobs;

use App\Jobs\GenerateLawMetadata;
use App\Models\IngestedLaw;
use App\Services\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class GenerateLawMetadataTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_generates_metadata_and_stores_it_successfully(): void
    {
        // Arrange: Create a test IngestedLaw
        $law = IngestedLaw::create([
            'id' => '01HXC9K8P5B6M2QWERTY12345',
            'doc_id' => 'zakonhr-test-law-2024',
            'title' => 'Test Croatian Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'source_url' => 'https://example.com/test-law',
            'metadata' => ['source' => 'zakon.hr'],
            'ingested_at' => now(),
        ]);

        $articles = [
            [
                'content' => 'Članak 1: Ovo je testni članak.',
                'article_number' => '1',
                'heading_chain' => ['Glava I', 'Opće odredbe'],
            ],
            [
                'content' => 'Članak 2: Drugi testni članak.',
                'article_number' => '2',
                'heading_chain' => ['Glava I', 'Opće odredbe'],
            ],
        ];

        // Mock OpenAI service
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->withArgs(function ($messages, $model, $options) {
                // Verify the chat is called with correct structure
                return is_array($messages)
                    && count($messages) === 2
                    && $messages[0]['role'] === 'system'
                    && $messages[1]['role'] === 'user'
                    && isset($options['response_format'])
                    && $options['response_format']['type'] === 'json_object';
            })
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'summary' => 'Test sažetak zakona',
                                'key_topics' => ['tema1', 'tema2'],
                                'practice_areas' => ['pravo1', 'pravo2'],
                                'tags' => ['tag1', 'tag2'],
                                'affected_parties' => ['stranke'],
                                'complexity_level' => 'basic',
                                'estimated_articles' => 2,
                            ]),
                        ],
                    ],
                ],
                'model' => 'gpt-4o-mini',
                'usage' => [
                    'prompt_tokens' => 100,
                    'completion_tokens' => 50,
                    'total_tokens' => 150,
                ],
            ]);

        $this->app->instance(OpenAIService::class, $mockOpenAI);

        // Act: Execute the job
        $job = new GenerateLawMetadata($law->id, $articles);
        $job->handle($mockOpenAI);

        // Assert: Verify metadata was stored
        $law->refresh();
        $this->assertNotNull($law->metadata);
        $this->assertArrayHasKey('ai_generated', $law->metadata);
        $this->assertArrayHasKey('ai_generated_at', $law->metadata);

        $aiMetadata = $law->metadata['ai_generated'];
        $this->assertEquals('Test sažetak zakona', $aiMetadata['summary']);
        $this->assertContains('tema1', $aiMetadata['key_topics']);
        $this->assertContains('pravo1', $aiMetadata['practice_areas']);
        $this->assertEquals('basic', $aiMetadata['complexity_level']);
        $this->assertEquals(2, $aiMetadata['estimated_articles']);

        // Verify usage stats were stored
        $this->assertArrayHasKey('openai_usage', $aiMetadata);
        $this->assertEquals(150, $aiMetadata['openai_usage']['total_tokens']);
    }

    public function test_job_handles_missing_law_gracefully(): void
    {
        // Arrange: Non-existent law ID
        $nonExistentId = '01HXC9K8P5B6M2QWERTY99999';
        $articles = [
            ['content' => 'Test', 'article_number' => '1', 'heading_chain' => []],
        ];

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldNotReceive('chat');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) use ($nonExistentId) {
                return $message === 'GenerateLawMetadata: IngestedLaw not found'
                    && $context['ingested_law_id'] === $nonExistentId;
            });

        // Act: Execute the job
        $job = new GenerateLawMetadata($nonExistentId, $articles);
        $job->handle($mockOpenAI);

        // Assert: Job completes without throwing (logged warning instead)
        $this->assertTrue(true);
    }

    public function test_job_marks_failure_on_openai_error(): void
    {
        // Arrange: Create a test law
        $law = IngestedLaw::create([
            'id' => '01HXC9K8P5B6M2QWERTY54321',
            'doc_id' => 'zakonhr-error-test-2024',
            'title' => 'Error Test Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'metadata' => [],
            'ingested_at' => now(),
        ]);

        $articles = [
            ['content' => 'Test', 'article_number' => '1', 'heading_chain' => []],
        ];

        $mockOpenAI = Mockery::mock(OpenAIService::class);

        // Act & Assert: Simulate OpenAI error
        $exception = new \Exception('OpenAI API error');
        $job = new GenerateLawMetadata($law->id, $articles);
        $job->failed($exception);

        $law->refresh();
        $this->assertArrayHasKey('ai_generation_failed', $law->metadata);
        $this->assertTrue($law->metadata['ai_generation_failed']);
        $this->assertArrayHasKey('ai_generation_error', $law->metadata);
        $this->assertEquals('OpenAI API error', $law->metadata['ai_generation_error']);
    }

    public function test_job_builds_full_law_text_correctly(): void
    {
        // This tests the protected method indirectly by verifying the chat prompt contains expected data
        $law = IngestedLaw::create([
            'id' => '01HXC9K8P5B6M2QWERTY11111',
            'doc_id' => 'zakonhr-build-test-2024',
            'title' => 'Build Test Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'metadata' => ['date_published' => '2024-01-01'],
            'ingested_at' => now(),
        ]);

        $articles = [
            [
                'content' => 'First article content',
                'article_number' => '1',
                'heading_chain' => ['Chapter 1'],
            ],
            [
                'content' => 'Second article content',
                'article_number' => '2',
                'heading_chain' => ['Chapter 1', 'Section A'],
            ],
        ];

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->withArgs(function ($messages) {
                $userMessage = $messages[1]['content'] ?? '';
                // Verify the full text contains law metadata and article content
                return str_contains($userMessage, 'Build Test Law')
                    && str_contains($userMessage, 'Datum objave: 2024-01-01')
                    && str_contains($userMessage, 'Članak 1:')
                    && str_contains($userMessage, 'First article content')
                    && str_contains($userMessage, 'Članak 2:')
                    && str_contains($userMessage, 'Second article content')
                    && str_contains($userMessage, 'Chapter 1');
            })
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode(['summary' => 'Test'])]]
                ],
                'model' => 'gpt-4o-mini',
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2],
            ]);

        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $job = new GenerateLawMetadata($law->id, $articles);
        $job->handle($mockOpenAI);

        // If we get here without errors, the prompt was built correctly
        $this->assertTrue(true);
    }
}
