<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\App;
use App\Services\OpenAIService;
use App\Services\ZakonHrIngestService;
use App\Services\Odluke\OdlukeIngestService;
use App\Services\Odluke\OdlukeClient;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Illuminate\Support\Facades\DB;
use App\Models\IngestedLaw;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fetch list of responses (logs)
Artisan::command('openai:responses {--include=* : Repeatable include[]= query params} {--input_item_limit=} {--output_item_limit=}', function () {
    /** @var OpenAIService $svc */
    $svc = App::make(OpenAIService::class);

    $include = (array) $this->option('include');
    $query = [];
    if ($this->option('input_item_limit') !== null) {
        $query['input_item_limit'] = $this->option('input_item_limit');
    }
    if ($this->option('output_item_limit') !== null) {
        $query['output_item_limit'] = $this->option('output_item_limit');
    }

    $data = $svc->responsesList($query, $include);
    $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return SymfonyCommand::SUCCESS;
})->purpose('List OpenAI Responses API logs (beta)');

// Fetch input_items for a given response
Artisan::command('openai:response:input-items {responseId} {--include=* : Repeatable include[]= filters}', function (string $responseId) {
    /** @var OpenAIService $svc */
    $svc = App::make(OpenAIService::class);

    $include = (array) $this->option('include');
    $data = $svc->responseInputItems($responseId, $include);
    $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return SymfonyCommand::SUCCESS;
})->purpose('Fetch input_items for a specific Response (beta)');

// ZakonHR ingestion to embeddings
Artisan::command('zakonhr:ingest-embeddings
    {--url=* : One or more zakon.hr URLs}
    {--list= : Path to a text file with URLs (one per line)}
    {--offline-html= : Path to an offline HTML file to ingest (bypasses network)}
    {--agent=law : Agent name to attribute documents to}
    {--namespace=zakonhr : Namespace for storage}
    {--model= : Embedding model override}
    {--chunk=1200 : Chunk size in chars}
    {--overlap=150 : Overlap in chars}
    {--title= : Optional title override}
    {--date= : Optional publication date YYYY-MM-DD}
    {--dry : Dry-run; do not call embeddings; print counts only}
', function () {
    /** @var ZakonHrIngestService $svc */
    $svc = App::make(ZakonHrIngestService::class);

    $offline = (string) ($this->option('offline-html') ?? '');
    if ($offline !== '') {
        if (!is_file($offline)) {
            $this->error('Offline HTML file not found: '.$offline);
            return SymfonyCommand::FAILURE;
        }
        $html = file_get_contents($offline);
        $opts = [
            'agent' => (string)($this->option('agent') ?? 'law'),
            'namespace' => (string)($this->option('namespace') ?? 'zakonhr'),
            'model' => $this->option('model') ?: null,
            'chunk_chars' => (int)($this->option('chunk') ?? 1200),
            'overlap' => (int)($this->option('overlap') ?? 150),
            'title' => $this->option('title') ?: null,
            'date' => $this->option('date') ?: null,
            'dry' => (bool)$this->option('dry'),
        ];
        $res = $svc->ingestHtml($html, $opts, 'offline://zakonhr-sample');
        $this->info('ZakonHR (offline): Articles='.$res['articles_seen'].'; Inserted='.$res['inserted'].'; Would-chunks='.$res['would_chunks'].'; Errors='.$res['errors'].'; Dry='.(int)$res['dry']);
        $this->line('Agent='.$res['agent'].' Namespace='.$res['namespace'].' Model='.$res['model']);
        return SymfonyCommand::SUCCESS;
    }

    $urls = (array) $this->option('url');
    $list = (string) ($this->option('list') ?? '');
    if ($list && is_file($list)) {
        foreach (file($list, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $u) {
            $u = trim($u); if ($u) $urls[] = $u;
        }
    }
    $urls = array_values(array_unique(array_filter($urls)));
    if (!$urls) {
        $this->error('Provide at least one --url or a --list file, or use --offline-html.');
        return SymfonyCommand::FAILURE;
    }

    $opts = [
        'agent' => (string)($this->option('agent') ?? 'law'),
        'namespace' => (string)($this->option('namespace') ?? 'zakonhr'),
        'model' => $this->option('model') ?: null,
        'chunk_chars' => (int)($this->option('chunk') ?? 1200),
        'overlap' => (int)($this->option('overlap') ?? 150),
        'title' => $this->option('title') ?: null,
        'date' => $this->option('date') ?: null,
        'dry' => (bool)$this->option('dry'),
    ];

    $res = $svc->ingestUrls($urls, $opts);
    dd($res);
    $this->info('ZakonHR: URLs processed='.$res['urls_processed'].'; Articles seen='.$res['articles_seen'].'; Inserted='.$res['inserted'].'; Would-chunks='.$res['would_chunks'].'; Errors='.$res['errors'].'; Dry='.(int)$res['dry']);
    $this->line('Agent='.$res['agent'].' Namespace='.$res['namespace'].' Model='.$res['model']);
    return SymfonyCommand::SUCCESS;
})->purpose('Fetch ZakonHR pages, split into articles, embed and store.');

// Odluke suda ingestion to embeddings
Artisan::command('odluke:ingest-embeddings
    {--ids= : Comma-separated list of decision IDs}
    {--q= : Optional search query to collect IDs}
    {--params= : Extra query string for /Document/DisplayList}
    {--limit=50 : Max IDs to collect when using search}
    {--offline-text= : Path to an offline text file to ingest (bypasses network)}
    {--agent=odluke : Agent name}
    {--namespace=odluke : Namespace for storage}
    {--model= : Embedding model}
    {--chunk=1500 : Chunk size}
    {--overlap=200 : Overlap}
    {--prefer=auto : auto|html|pdf}
    {--dry : Dry-run; do not call embeddings; print counts only}
', function () {
    /** @var OdlukeIngestService $svc */
    $svc = App::make(OdlukeIngestService::class);
    /** @var OdlukeClient $client */
    $client = App::make(OdlukeClient::class);

    $offline = (string) ($this->option('offline-text') ?? '');
    if ($offline !== '') {
        if (!is_file($offline)) {
            $this->error('Offline text file not found: '.$offline);
            return SymfonyCommand::FAILURE;
        }
        $text = file_get_contents($offline) ?: '';
        $opts = [
            'agent' => (string)($this->option('agent') ?? 'odluke'),
            'namespace' => (string)($this->option('namespace') ?? 'odluke'),
            'model' => $this->option('model') ?: null,
            'chunk_chars' => (int)($this->option('chunk') ?? 1500),
            'overlap' => (int)($this->option('overlap') ?? 200),
            'dry' => (bool)$this->option('dry'),
        ];
        $res = $svc->ingestText($text, ['id' => 'offline-odluka'], $opts);
        $this->info('Odluke (offline): Inserted='.$res['inserted'].'; Would-chunks='.$res['would_chunks'].'; Skipped='.$res['skipped'].'; Errors='.$res['errors'].'; Dry='.(int)$res['dry']);
        $this->line('Agent='.$res['agent'].' Namespace='.$res['namespace'].' Model='.$res['model']);
        return SymfonyCommand::SUCCESS;
    }

    $ids = [];
    $idsOpt = trim((string) ($this->option('ids') ?? ''));
    if ($idsOpt !== '') {
        $ids = array_values(array_filter(array_map('trim', explode(',', $idsOpt))));
    } else {
        $q = (string) ($this->option('q') ?? '');
        $params = (string) ($this->option('params') ?? '');
        $limit = (int) ($this->option('limit') ?? 50);
        $res = $client->collectIdsFromList($q, $params, $limit, 1);
        $ids = $res['ids'] ?? [];
        if (!$ids) {
            $this->warn('No IDs collected. Provide --ids or use --q/--params or --offline-text.');
            return SymfonyCommand::FAILURE;
        }
    }

    $opts = [
        'agent' => (string)($this->option('agent') ?? 'odluke'),
        'namespace' => (string)($this->option('namespace') ?? 'odluke'),
        'model' => $this->option('model') ?: null,
        'chunk_chars' => (int)($this->option('chunk') ?? 1500),
        'overlap' => (int)($this->option('overlap') ?? 200),
        'prefer' => (string)($this->option('prefer') ?? 'auto'),
        'dry' => (bool)$this->option('dry'),
    ];

    $res = $svc->ingestByIds($ids, $opts);
    $this->info('Odluke: IDs processed='.$res['ids_processed'].'; Inserted='.$res['inserted'].'; Would-chunks='.$res['would_chunks'].'; Skipped='.$res['skipped'].'; Errors='.$res['errors'].'; Dry='.(int)$res['dry']);
    $this->line('Agent='.$res['agent'].' Namespace='.$res['namespace'].' Model='.$res['model']);
    return SymfonyCommand::SUCCESS;
})->purpose('Fetch Odluke decisions (HTML/PDF), extract text, embed and store.');

// Backfill ingested_law_id on laws and law_uploads
Artisan::command('laws:backfill-ingested-refs {--dry}', function () {
    $dry = (bool) $this->option('dry');
    $lawsTable = config('vizra-adk.tables.laws', 'laws');
    $uploadsTable = config('vizra-adk.tables.law_uploads', 'law_uploads');

    $totalLawRows = 0; $totalUploadRows = 0; $idx = 0;
    IngestedLaw::query()->orderBy('doc_id')->chunk(200, function ($chunk) use ($dry, $lawsTable, $uploadsTable, &$totalLawRows, &$totalUploadRows, &$idx) {
        foreach ($chunk as $ing) {
            $idx++;
            $docId = (string) $ing->doc_id;
            $id = (string) $ing->id;

            $lawQuery = DB::table($lawsTable)
                ->whereNull('ingested_law_id')
                ->where('doc_id', $docId);
            $affectedLaws = $dry ? $lawQuery->count() : $lawQuery->update(['ingested_law_id' => $id]);

            $upQuery = DB::table($uploadsTable)
                ->whereNull('ingested_law_id')
                ->where(function ($q) use ($docId) {
                    $q->where('doc_id', $docId)
                      ->orWhere('doc_id', 'like', $docId.'-clanak-%');
                });
            $affectedUploads = $dry ? $upQuery->count() : $upQuery->update(['ingested_law_id' => $id]);

            $totalLawRows += (int) $affectedLaws;
            $totalUploadRows += (int) $affectedUploads;
            $this->line(sprintf('#%d %s -> laws: %d, uploads: %d', $idx, $docId, (int)$affectedLaws, (int)$affectedUploads));
        }
    });

    $this->info('Backfill complete.');
    $this->info('Updated laws rows: '.$totalLawRows);
    $this->info('Updated law_uploads rows: '.$totalUploadRows);
    $this->info('Dry-run: '.($dry ? 'yes' : 'no'));
    return \Symfony\Component\Console\Command\Command::SUCCESS;
})->purpose('Backfill ingested_law_id on laws and law_uploads.');
