<?php

namespace App\Jobs;

use App\Models\IngestedLaw;
use App\Services\OpenAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateLawMetadata implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public int $backoff = 60;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $ingestedLawId,
        public array $articles
    ) {}

    /**
     * Execute the job.
     */
    public function handle(OpenAIService $openai): void
    {
        Log::info('GenerateLawMetadata: Starting', [
            'ingested_law_id' => $this->ingestedLawId,
            'article_count' => count($this->articles),
        ]);

        $ingestedLaw = IngestedLaw::find($this->ingestedLawId);

        if (!$ingestedLaw) {
            Log::warning('GenerateLawMetadata: IngestedLaw not found', [
                'ingested_law_id' => $this->ingestedLawId,
            ]);
            return;
        }

        // Combine all article contents into one text
        $fullText = $this->buildFullLawText($ingestedLaw, $this->articles);

        // Call OpenAI ONCE for the entire law
        try {
            $enhancedMetadata = $this->generateMetadataFromOpenAI($openai, $fullText, $ingestedLaw);

            // Update the IngestedLaw with enhanced metadata
            $currentMetadata = $ingestedLaw->metadata ?? [];
            $ingestedLaw->metadata = array_merge($currentMetadata, [
                'ai_generated' => $enhancedMetadata,
                'ai_generated_at' => now()->toIso8601String(),
            ]);
            $ingestedLaw->save();

            Log::info('GenerateLawMetadata: Successfully generated and saved metadata', [
                'ingested_law_id' => $this->ingestedLawId,
            ]);

        } catch (Throwable $e) {
            Log::error('GenerateLawMetadata: Failed to generate metadata', [
                'ingested_law_id' => $this->ingestedLawId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Build the full law text from all articles.
     */
    protected function buildFullLawText(IngestedLaw $law, array $articles): string
    {
        $parts = [];
        $parts[] = "Zakon: {$law->title}";
        $parts[] = "Jurisdikcija: {$law->jurisdiction}";

        if (!empty($law->metadata['date_published'])) {
            $parts[] = "Datum objave: {$law->metadata['date_published']}";
        }

        $parts[] = "\n=== SADRŽAJ ZAKONA ===\n";

        foreach ($articles as $article) {
            $articleNum = $article['article_number'] ?? 'N/A';
            $content = $article['content'] ?? '';
            $headingChain = $article['heading_chain'] ?? [];

            if (!empty($headingChain)) {
                $parts[] = "Dio: " . implode(' > ', $headingChain);
            }
            $parts[] = "Članak {$articleNum}:";
            $parts[] = $content;
            $parts[] = "---";
        }

        return implode("\n", $parts);
    }

    /**
     * Generate enhanced metadata using OpenAI.
     */
    protected function generateMetadataFromOpenAI(OpenAIService $openai, string $fullText, IngestedLaw $law): array
    {
        // Truncate if too long (OpenAI has token limits)
        $maxChars = 120000; // Approx 30k tokens for gpt-4o-mini
        if (mb_strlen($fullText) > $maxChars) {
            $fullText = mb_substr($fullText, 0, $maxChars) . "\n\n[...truncated...]";
        }

        $systemPrompt = "Ti si stručnjak za hrvatsko zakonodavstvo. Analiziraj sljedeći zakon i generiraj strukturirane metapodatke.";

        $userPrompt = <<<PROMPT
Analiziraj sljedeći hrvatski zakon i generiraj strukturirane metapodatke u JSON formatu.

{$fullText}

Generiraj JSON objekt sa sljedećim poljima:
{
  "summary": "Kratak sažetak zakona (2-3 rečenice) na hrvatskom jeziku",
  "key_topics": ["lista", "ključnih", "tema"],
  "practice_areas": ["lista", "pravnih", "područja"],
  "tags": ["lista", "oznaka", "za", "pretraživanje"],
  "affected_parties": ["lista", "strana", "na", "koje", "se", "primjenjuje"],
  "complexity_level": "basic/intermediate/advanced",
  "estimated_articles": broj članaka kao broj
}

Odgovori SAMO sa JSON objektom, bez dodatnog teksta.
PROMPT;

        $response = $openai->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ], config('openai.models.chat'), [
            'temperature' => 0.3,
            'response_format' => ['type' => 'json_object'],
        ]);

        $content = $response['choices'][0]['message']['content'] ?? '{}';
        $metadata = json_decode($content, true) ?? [];

        // Add usage stats for monitoring
        $metadata['openai_usage'] = [
            'prompt_tokens' => $response['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
            'total_tokens' => $response['usage']['total_tokens'] ?? 0,
            'model' => $response['model'] ?? config('openai.models.chat'),
        ];

        return $metadata;
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('GenerateLawMetadata: Job failed after all retries', [
            'ingested_law_id' => $this->ingestedLawId,
            'exception' => $exception->getMessage(),
        ]);

        // Mark the law with failed metadata generation
        $ingestedLaw = IngestedLaw::find($this->ingestedLawId);
        if ($ingestedLaw) {
            $currentMetadata = $ingestedLaw->metadata ?? [];
            $ingestedLaw->metadata = array_merge($currentMetadata, [
                'ai_generation_failed' => true,
                'ai_generation_error' => $exception->getMessage(),
                'ai_generation_failed_at' => now()->toIso8601String(),
            ]);
            $ingestedLaw->save();
        }
    }
}
