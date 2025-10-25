<?php

namespace App\Console\Commands;

use App\Services\GraphRagService;
use App\Services\Odluke\OdlukeClient;
use App\Services\Odluke\OdlukeIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IngestCourtDecisionsEmbeddings extends Command
{
    protected $signature = 'court-decisions:ingest
        {--id=* : One or more decision IDs to ingest}
        {--query= : Search query to collect decision IDs (e.g., "Ugovor o radu")}
        {--params= : Raw query params for advanced filtering (e.g., "sud=Vrhovni+sud&vo=Presuda&od=2024-01-01")}
        {--limit=50 : Max decision IDs to collect when using --query/--params}
        {--page=1 : Page number for search results}
        {--prefer=auto : Content source preference: auto|html|pdf}
        {--model= : Embedding model (default from config)}
        {--chunk=1500 : Chunk size in characters}
        {--overlap=200 : Overlap in characters}
        {--sync-graph : Sync ingested decisions to graph database}
        {--dry : Dry run - preview without persisting}';

    protected $description = 'Ingest court decisions from odluke.sudovi.hr with embeddings and optional graph sync';

    public function __construct(
        protected OdlukeIngestService $ingest,
        protected OdlukeClient $client,
        protected ?GraphRagService $graphRag = null
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting court decisions ingestion from odluke.sudovi.hr');

        // Collect decision IDs
        $ids = $this->collectDecisionIds();

        if (empty($ids)) {
            $this->error('No decision IDs to process. Provide --id, or use --query/--params to search.');
            return self::FAILURE;
        }

        $this->info(sprintf('Processing %d decision(s)...', count($ids)));

        // Ingest decisions
        $stats = $this->ingestDecisions($ids);

        // Display results
        $this->displayResults($stats);

        // Sync to graph if requested
        if ($this->option('sync-graph') && !$this->option('dry')) {
            $this->syncToGraph($stats);
        }

        return self::SUCCESS;
    }

    protected function collectDecisionIds(): array
    {
        $ids = (array) $this->option('id');

        // If explicit IDs provided, use them
        if (!empty($ids)) {
            return array_filter(array_map('trim', $ids));
        }

        // Otherwise, search for IDs
        $query = $this->option('query');
        $params = $this->option('params');

        if ($query === null && $params === null) {
            return [];
        }

        $limit = (int) $this->option('limit');
        $page = (int) $this->option('page');

        $this->line(sprintf(
            'Searching decisions: query="%s" params="%s" limit=%d page=%d',
            $query ?? 'none',
            $params ?? 'none',
            $limit,
            $page
        ));

        try {
            $result = $this->client->collectIdsFromList($query, $params, $limit, $page);
            $collectedIds = $result['ids'] ?? [];

            $this->info(sprintf('Found %d decision ID(s)', count($collectedIds)));

            if ($this->option('verbose') && !empty($collectedIds)) {
                $this->line('IDs: ' . implode(', ', array_slice($collectedIds, 0, 10)));
                if (count($collectedIds) > 10) {
                    $this->line('... and ' . (count($collectedIds) - 10) . ' more');
                }
            }

            return $collectedIds;
        } catch (\Throwable $e) {
            $this->error('Failed to search decisions: ' . $e->getMessage());
            Log::error('Court decision search failed', [
                'query' => $query,
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    protected function ingestDecisions(array $ids): array
    {
        $model = $this->option('model') ?: config('openai.models.embeddings');
        $chunkChars = (int) ($this->option('chunk') ?? 1500);
        $overlap = (int) ($this->option('overlap') ?? 200);
        $prefer = $this->option('prefer') ?? 'auto';
        $dry = (bool) $this->option('dry');

        if (!in_array($prefer, ['auto', 'html', 'pdf'], true)) {
            $this->warn("Invalid --prefer value '{$prefer}', using 'auto'");
            $prefer = 'auto';
        }

        $options = [
            'model' => $model,
            'chunk_chars' => $chunkChars,
            'overlap' => $overlap,
            'prefer' => $prefer,
            'dry' => $dry,
        ];

        if ($dry) {
            $this->warn('DRY RUN MODE - No data will be persisted');
        }

        $this->line(sprintf(
            'Options: model=%s chunk_size=%d overlap=%d prefer=%s',
            $model,
            $chunkChars,
            $overlap,
            $prefer
        ));

        try {
            $progressBar = $this->output->createProgressBar(count($ids));
            $progressBar->start();

            $result = $this->ingest->ingestByIds($ids, $options);

            $progressBar->finish();
            $this->newLine(2);

            return $result;
        } catch (\Throwable $e) {
            $this->error('Ingestion failed: ' . $e->getMessage());
            Log::error('Court decision ingestion failed', [
                'ids_count' => count($ids),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'ids_processed' => 0,
                'inserted' => 0,
                'would_chunks' => 0,
                'errors' => 1,
                'skipped' => count($ids),
                'model' => $model,
                'dry' => $dry,
            ];
        }
    }

    protected function displayResults(array $stats): void
    {
        $this->info('Ingestion completed!');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['IDs Processed', $stats['ids_processed'] ?? 0],
                ['Documents Inserted', $stats['inserted'] ?? 0],
                ['Chunks Generated', $stats['would_chunks'] ?? 0],
                ['Errors', $stats['errors'] ?? 0],
                ['Skipped', $stats['skipped'] ?? 0],
                ['Model', $stats['model'] ?? 'unknown'],
                ['Dry Run', ($stats['dry'] ?? false) ? 'Yes' : 'No'],
            ]
        );
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
