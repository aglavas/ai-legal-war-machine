<?php

namespace App\Console\Commands;

use App\Services\ZakonHrIngestService;
use Illuminate\Console\Command;

class ImportZakonHr extends Command
{
    /**
     * @var string $signature
     */
    protected $signature = 'import:zakonhr
        {--url=* : One or more zakon.hr URLs}
        {--file= : Path to a local HTML file for offline import}
        {--title= : Optional title override}
        {--date= : Optional YYYY-MM-DD publication date}
        {--dry : Dry run, do not persist}';

    /**
     * @var string $description
     */
    protected $description = 'Import laws from zakon.hr (by URL or offline HTML)';

    /**
     * @param ZakonHrIngestService $ingest
     */
    public function __construct(protected ZakonHrIngestService $ingest)
    {
        parent::__construct();
    }

    /**
     * @return int
     */
    public function handle(): int
    {
        $dry   = (bool) $this->option('dry');
        $title = $this->option('title');
        $date  = $this->option('date');

        if ($file = $this->option('file')) {
            if (!is_file($file)) {
                $this->error("File not found: {$file}");
                return 1;
            }
            $html = file_get_contents($file) ?: '';
            $res = $this->ingest->ingestHtml($html, ['title' => $title, 'date' => $date, 'dry' => $dry], $file);
            $this->line(json_encode($res));
            return 0;
        }

        $urls = (array) $this->option('url');
        if (empty($urls)) {
            $this->error('Provide at least one --url or a --file.');
            return 1;
        }

        $res = $this->ingest->ingestUrls($urls, ['title' => $title, 'date' => $date, 'dry' => $dry]);
        $this->line(json_encode($res));
        return 0;
    }
}
