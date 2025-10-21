<?php

namespace App\Console\Commands;

use App\Services\Odluke\OdlukeClient;
use App\Services\Odluke\OdlukeIngestService;
use Illuminate\Console\Command;

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
        {--prefer=auto : Source preference: auto|html|pdf}
        {--dry : Dry run, do not persist}';

    /**
     * @var string $description
     */
    protected $description = 'Ingest court decisions (odluke.sudovi.hr) by IDs or by search';

    /**
     * @param OdlukeIngestService $ingest
     * @param OdlukeClient $client
     */
    public function __construct(
        protected OdlukeIngestService $ingest,
        protected OdlukeClient $client
    ) {
        parent::__construct();
    }

    /**
     * @return int
     */
    public function handle(): int
    {
        $ids = (array) $this->option('id');

        // Collect IDs by search if none provided
        if (empty($ids) && ($this->option('query') !== null || $this->option('params') !== null)) {
            $q      = $this->option('query');
            $params = $this->option('params');
            $limit  = (int) $this->option('limit');
            $col    = $this->client->collectIdsFromList($q, $params, $limit);
            $ids    = $col['ids'] ?? [];
            $this->info('Collected IDs: '.count($ids));
        }

        if (empty($ids)) {
            $this->error('Provide at least one --id, or use --query/--params to collect IDs.');
            return 1;
        }

        $prefer = (string) $this->option('prefer') ?: 'auto';
        $dry    = (bool) $this->option('dry');

        $res = $this->ingest->ingestByIds($ids, [
            'prefer' => in_array($prefer, ['auto','html','pdf'], true) ? $prefer : 'auto',
            'dry'    => $dry,
        ]);

        $this->line(json_encode($res));
        return 0;
    }
}
