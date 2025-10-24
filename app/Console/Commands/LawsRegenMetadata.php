<?php

namespace App\Console\Commands;

use App\Jobs\GenerateLawMetadata;
use App\Models\IngestedLaw;
use App\Models\Law;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                            {--force : Force regeneration even if metadata exists}';

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

        $this->info('Starting laws metadata regeneration...');
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

        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->info('No laws found that need metadata generation.');
            return self::SUCCESS;
        }

        $this->info("Found {$totalCount} law(s) needing metadata generation.");
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
        $this->info('Metadata regeneration complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total processed', $processed],
                ['Jobs dispatched', $dispatched],
                ['Failed', $failed],
                ['Batches', $batchCount],
            ]
        );

        Log::info('Laws metadata regeneration completed', [
            'total_processed' => $processed,
            'dispatched' => $dispatched,
            'failed' => $failed,
            'batches' => $batchCount,
            'doc_id_filter' => $this->option('doc-id'),
            'force' => $this->option('force'),
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
