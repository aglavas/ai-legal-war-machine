<?php

namespace App\Console\Commands;

use App\Services\LawIngestService;
use Illuminate\Console\Command;

class IngestCroatianLawsEmbeddings extends Command
{
    protected $signature = 'hrlaws:ingest-embeddings
        {--since= : Start year}
        {--limit= : Max acts to process}
        {--agent=law : Agent name}
        {--namespace=nn : Namespace}
        {--model= : Embedding model}
        {--chunk=1200 : Chunk size in chars}
        {--overlap=150 : Overlap in chars}';

    protected $description = 'Download consolidated Croatian laws from NN, split into articles, embed and store in pgvector table.';

    public function handle(LawIngestService $service): int
    {
        $opts = [
            'since_year' => $this->option('since') ? (int)$this->option('since') : null,
            'max_acts' => $this->option('limit') ? (int)$this->option('limit') : null,
            'agent' => (string)($this->option('agent') ?? 'law'),
            'namespace' => (string)($this->option('namespace') ?? 'nn'),
            'model' => $this->option('model') ?: null,
            'chunk_chars' => (int)($this->option('chunk') ?? 1200),
            'overlap' => (int)($this->option('overlap') ?? 150),
        ];

        $res = $service->ingest($opts);
        $this->info('Acts processed: '.$res['acts_processed'].'; Articles seen: '.$res['articles_seen'].'; Inserted: '.$res['inserted'].'; Errors: '.$res['errors']);
        if (!empty($res['skipped'])) {
            $this->info('Skipped acts: '.$res['skipped']);
            foreach ((array)($res['skip_messages'] ?? []) as $msg) {
                $this->line(' - '.$msg);
            }
        }
        $this->line('Agent='.$res['agent'].' Namespace='.$res['namespace'].' Model='.$res['model']);
        return self::SUCCESS;
    }
}
