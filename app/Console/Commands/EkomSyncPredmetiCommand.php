<?php

namespace App\Console\Commands;

use App\Services\EkomService;
use Illuminate\Console\Command;

class EkomSyncPredmetiCommand extends Command
{
    protected $signature = 'ekom:predmeti:sync
        {--status=* : Filter by status U_RADU or ARHIVIRAN}
        {--sudId=* : Filter by court IDs}
        {--size= : Page size (max 100)}
        {--pages=1 : Max number of pages to fetch}
        {--shouldCheckPredmetDoNotDisturb= : true/false}';

    protected $description = 'Sync Predmeti (cases) from e-Komunikacija API into local database';

    public function handle(EkomService $service): int
    {
        $filters = [];

        $status = $this->option('status');
        if (!empty($status)) {
            $filters['status'] = $status;
        }
        $sudId = $this->option('sudId');
        if (!empty($sudId)) {
            $filters['sudId'] = array_map('intval', $sudId);
        }
        $dnd = $this->option('shouldCheckPredmetDoNotDisturb');
        if ($dnd !== null) {
            $filters['shouldCheckPredmetDoNotDisturb'] = filter_var($dnd, FILTER_VALIDATE_BOOLEAN);
        }

        $size = (int) ($this->option('size') ?? config('ekom.default_page_size'));
        if ($size <= 0 || $size > 100) {
            $size = min(100, max(1, $size));
        }
        $pages = max(1, (int) $this->option('pages'));

        $count = $service->syncPredmeti($filters, $pages, $size);

        $this->info("Synced {$count} predmet(a).");
        return self::SUCCESS;
    }
}
