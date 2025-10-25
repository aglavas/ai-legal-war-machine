<?php

namespace App\Console\Commands;

use App\Jobs\GenerateLawMetadata;
use App\Models\IngestedLaw;
use App\Models\Law;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Command to backfill AI-generated metadata for ingested laws.
 *
 * This command identifies laws lacking AI-generated metadata and dispatches
 * jobs to generate it. Supports batch processing with rate limiting to avoid
 * overwhelming the OpenAI API.
 *
 * Usage examples:
 *   # Preview what would be processed
 *   php artisan laws:regen-metadata --dry-run
 *
 *   # Process specific law
 *   php artisan laws:regen-metadata --doc-id=nn-2021-12-1234
 *
 *   # Process all with custom batch size and rate limit
 *   php artisan laws:regen-metadata --batch-size=5 --rate-limit=30
 *
 *   # Force regeneration even if metadata exists
 *   php artisan laws:regen-metadata --force
 */
class LawsRegenMetadata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laws:regen-metadata
                            {--dry-run : Show what would be processed without actually dispatching jobs}
                            {--doc-id= : Filter by specific doc_id}
                            {--batch-size=10 : Number of laws to process per batch}
                            {--rate-limit=60 : Seconds to wait between batches}
                            {--force : Force regeneration even if metadata exists}
                            {--limit= : Maximum number of laws to process (useful for testing)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill AI-generated metadata for ingested laws that lack it';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $docIdFilter = $this->option('doc-id');
        $batchSize = (int) $this->option('batch-size');
        $rateLimit = (int) $this->option('rate-limit');
        $force = $this->option('force');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        // Validate options
        if ($batchSize < 1) {
            $this->error('Batch size must be at least 1');
            return self::FAILURE;
        }

        if ($rateLimit < 0) {
            $this->error('Rate limit cannot be negative');
            return self::FAILURE;
        }

        if ($limit !== null && $limit < 1) {
            $this->error('Limit must be at least 1');
            return self::FAILURE;
        }

        $this->info('Starting laws metadata regeneration...');
        $this->table(
            ['Option', 'Value'],
            [
                ['Dry Run', $dryRun ? 'Yes' : 'No'],
                ['Doc ID Filter', $docIdFilter ?? 'None'],
                ['Batch Size', $batchSize],
                ['Rate Limit', $rateLimit . 's between batches'],
                ['Force Regeneration', $force ? 'Yes' : 'No'],
                ['Limit', $limit ?? 'None'],
            ]
        );
        $this->newLine();

        // Build query for laws needing metadata
        $query = IngestedLaw::query();

        if ($docIdFilter) {
            $query->where('doc_id', $docIdFilter);
            $this->info("Filtering by doc_id: {$docIdFilter}");
        }

        if (!$force) {
            // Only select laws without AI-generated metadata
            $query->where(function ($q) {
                $q->whereNull('metadata->ai_generated')
                  ->orWhere('metadata->ai_generated', '=', '');
            });
        }

        // Apply limit if specified
        if ($limit !== null) {
            $query->limit($limit);
        }

        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->info('No laws found that need metadata generation.');
            return self::SUCCESS;
        }

        $effectiveCount = $limit !== null ? min($totalCount, $limit) : $totalCount;
        $this->info("Found {$effectiveCount} law(s) needing metadata generation.");
        if ($limit !== null && $totalCount > $limit) {
            $this->warn("Note: Total available is {$totalCount}, but processing only {$limit} due to --limit option");
        }
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No jobs will be dispatched');
            $this->newLine();

            $laws = $query->select('id', 'doc_id', 'title', 'law_number')->limit(10)->get();
            $this->table(
                ['ID', 'Doc ID', 'Title', 'Law Number'],
                $laws->map(fn($law) => [
                    $law->id,
                    $law->doc_id,
                    substr($law->title ?? '', 0, 50),
                    $law->law_number ?? 'N/A',
                ])
            );

            if ($totalCount > 10) {
                $this->info("... and " . ($totalCount - 10) . " more");
            }

            return self::SUCCESS;
        }

        // Confirm before proceeding
        if (!$this->confirm("Do you want to dispatch jobs for {$totalCount} law(s)?", true)) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Processing laws in batches...');

        $progressBar = $this->output->createProgressBar($totalCount);
        $progressBar->start();

        $processed = 0;
        $dispatched = 0;
        $failed = 0;
        $batchCount = 0;

        // Process in batches
        $query->chunk($batchSize, function ($laws) use (
            &$processed,
            &$dispatched,
            &$failed,
            &$batchCount,
            $batchSize,
            $rateLimit,
            $progressBar,
            $totalCount
        ) {
            $batchCount++;

            foreach ($laws as $law) {
                try {
                    // Fetch articles for this law from the laws table
                    $articles = $this->getArticlesForLaw($law);

                    if (empty($articles)) {
                        Log::warning('No articles found for law', [
                            'ingested_law_id' => $law->id,
                            'doc_id' => $law->doc_id,
                        ]);
                        $failed++;
                        $progressBar->advance();
                        continue;
                    }

                    // Dispatch the job
                    GenerateLawMetadata::dispatch($law->id, $articles);
                    $dispatched++;

                } catch (\Throwable $e) {
                    $failed++;
                    Log::error('Failed to dispatch metadata generation job', [
                        'ingested_law_id' => $law->id,
                        'doc_id' => $law->doc_id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $processed++;
                $progressBar->advance();
            }

            // Rate limiting: sleep between batches (but not after the last batch)
            if ($processed < $totalCount && $rateLimit > 0) {
                sleep($rateLimit);
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->newLine();
        $this->info('Metadata regeneration complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total processed', $processed],
                ['Jobs dispatched', $dispatched],
                ['Failed', $failed],
                ['Success rate', $dispatched > 0 ? round(($dispatched / $processed) * 100, 2) . '%' : 'N/A'],
                ['Batches', $batchCount],
            ]
        );

        if ($failed > 0) {
            $this->warn("Warning: {$failed} law(s) failed to dispatch. Check logs for details.");
        }

        Log::info('Laws metadata regeneration completed', [
            'total_processed' => $processed,
            'dispatched' => $dispatched,
            'failed' => $failed,
            'success_rate' => $dispatched > 0 ? round(($dispatched / $processed) * 100, 2) : 0,
            'batches' => $batchCount,
            'doc_id_filter' => $this->option('doc-id'),
            'force' => $this->option('force'),
            'limit' => $limit,
        ]);

        return self::SUCCESS;
    }

    /**
     * Get articles for a given ingested law from the laws table
     *
     * @param IngestedLaw $law
     * @return array
     */
    protected function getArticlesForLaw(IngestedLaw $law): array
    {
        $lawChunks = Law::where('doc_id', $law->doc_id)
            ->orderBy('chunk_index')
            ->get();

        if ($lawChunks->isEmpty()) {
            return [];
        }

        $articles = [];
        foreach ($lawChunks as $chunk) {
            $metadata = is_string($chunk->metadata) ? json_decode($chunk->metadata, true) : $chunk->metadata;

            $articles[] = [
                'article_number' => $metadata['article_number'] ?? 'N/A',
                'content' => $chunk->content,
                'heading_chain' => $metadata['heading_chain'] ?? [],
            ];
        }

        return $articles;
    }
}
