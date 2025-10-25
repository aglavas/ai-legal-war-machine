<?php

namespace App\Console\Commands;

use App\Services\GraphRagService;
use App\Services\Odluke\OdlukeClient;
use App\Services\Odluke\OdlukeIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DownloadOdluke extends Command
{
    /**
     * @var string $signature
     */
    protected $signature = 'odluke:ingest
        {--id=* : One or more decision IDs}
        {--query= : Optional search query to collect IDs}
        {--params= : Optional raw query params from the site}
        {--limit=50 : Max IDs to collect when using --query/--params}
        {--page=1 : Page number for search results}
        {--prefer=auto : Source preference: auto|html|pdf}
        {--model= : Embedding model override}
        {--chunk=1500 : Chunk size in characters}
        {--overlap=200 : Overlap in characters}
        {--sync-graph : Sync ingested decisions to graph database}
        {--dry : Dry run, do not persist}';

    /**
     * @var string $description
     */
    protected $description = 'Ingest court decisions (odluke.sudovi.hr) by IDs or by search, with optional graph sync';

    /**
     * @param OdlukeIngestService $ingest
     * @param OdlukeClient $client
     * @param GraphRagService|null $graphRag
     */
    public function __construct(
        protected OdlukeIngestService $ingest,
        protected OdlukeClient $client,
        protected ?GraphRagService $graphRag = null
    ) {
        parent::__construct();
    }

    /**
     * @return int
     */
    public function handle(): int
    {
        $this->info('Starting court decisions ingestion from odluke.sudovi.hr');

        $ids = (array) $this->option('id');

        // Collect IDs by search if none provided
        if (empty($ids) && ($this->option('query') !== null || $this->option('params') !== null)) {
            $q      = $this->option('query');
            $params = $this->option('params');
            $limit  = (int) $this->option('limit');
            $page   = (int) $this->option('page');

            $this->line(sprintf(
                'Searching: query="%s" params="%s" limit=%d page=%d',
                $q ?? 'none',
                $params ?? 'none',
                $limit,
                $page
            ));

            try {
                $col = $this->client->collectIdsFromList($q, $params, $limit, $page);
                $ids = $col['ids'] ?? [];
                $this->info('Collected IDs: ' . count($ids));
            } catch (\Throwable $e) {
                $this->error('Failed to collect IDs: ' . $e->getMessage());
                Log::error('Odluke search failed', [
                    'query' => $q,
                    'params' => $params,
                    'error' => $e->getMessage(),
                ]);
                return self::FAILURE;
            }
        }

        if (empty($ids)) {
            $this->error('Provide at least one --id, or use --query/--params to collect IDs.');
            return self::FAILURE;
        }

        $this->info(sprintf('Processing %d decision(s)...', count($ids)));

        $prefer = (string) $this->option('prefer') ?: 'auto';
        $dry    = (bool) $this->option('dry');
        $model  = $this->option('model') ?: config('openai.models.embeddings');
        $chunk  = (int) ($this->option('chunk') ?? 1500);
        $overlap = (int) ($this->option('overlap') ?? 200);

        if (!in_array($prefer, ['auto','html','pdf'], true)) {
            $this->warn("Invalid --prefer value '{$prefer}', using 'auto'");
            $prefer = 'auto';
        }

        if ($dry) {
            $this->warn('DRY RUN MODE - No data will be persisted');
        }

        $this->line(sprintf(
            'Options: model=%s chunk=%d overlap=%d prefer=%s',
            $model,
            $chunk,
            $overlap,
            $prefer
        ));

        // Run ingestion with progress bar
        $progressBar = $this->output->createProgressBar(count($ids));
        $progressBar->start();

        try {
            $res = $this->ingest->ingestByIds($ids, [
                'prefer' => $prefer,
                'dry'    => $dry,
                'model'  => $model,
                'chunk_chars' => $chunk,
                'overlap' => $overlap,
            ]);

            $progressBar->finish();
            $this->newLine(2);

            // Display results in table format
            $this->info('Ingestion completed!');
            $this->newLine();

            $this->table(
                ['Metric', 'Value'],
                [
                    ['IDs Processed', $res['ids_processed'] ?? 0],
                    ['Documents Inserted', $res['inserted'] ?? 0],
                    ['Chunks Generated', $res['would_chunks'] ?? 0],
                    ['Errors', $res['errors'] ?? 0],
                    ['Skipped', $res['skipped'] ?? 0],
                    ['Model', $res['model'] ?? 'unknown'],
                    ['Dry Run', ($res['dry'] ?? false) ? 'Yes' : 'No'],
                ]
            );

            // Sync to graph if requested
            if ($this->option('sync-graph') && !$dry) {
                $this->syncToGraph($res);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $progressBar->finish();
            $this->newLine(2);
            $this->error('Ingestion failed: ' . $e->getMessage());
            Log::error('Odluke ingestion failed', [
                'ids_count' => count($ids),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }

    protected function syncToGraph(array $stats): void
    {
        if (!$this->graphRag) {
            $this->warn('GraphRagService not available, skipping graph sync');
            return;
        }

        if (($stats['inserted'] ?? 0) === 0) {
            $this->line('No documents inserted, skipping graph sync');
            return;
        }

        $this->info('Syncing decisions to graph database...');

        try {
            // Get recently ingested decisions
            $recentDecisions = DB::table('court_decisions')
                ->orderBy('created_at', 'desc')
                ->limit($stats['ids_processed'] ?? 10)
                ->pluck('id');

            $synced = 0;
            $errors = 0;

            $progressBar = $this->output->createProgressBar($recentDecisions->count());
            $progressBar->start();

            foreach ($recentDecisions as $decisionId) {
                try {
                    $this->graphRag->syncCourtDecision($decisionId);
                    $synced++;
                } catch (\Throwable $e) {
                    $errors++;
                    Log::warning('Failed to sync decision to graph', [
                        'decision_id' => $decisionId,
                        'error' => $e->getMessage(),
                    ]);
                }
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->info(sprintf('Graph sync completed: %d synced, %d errors', $synced, $errors));
        } catch (\Throwable $e) {
            $this->error('Graph sync failed: ' . $e->getMessage());
            Log::error('Graph sync failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
