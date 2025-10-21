<?php

namespace App\Console\Commands;

use App\Services\EkomService;
use Illuminate\Console\Command;

class EkomSyncOtpravciCommand extends Command
{
    protected $signature = 'ekom:otpravci:sync
        {--status=* : PRIMLJEN or U_DOSTAVI}
        {--sudId=* : Filter by court IDs}
        {--size= : Page size (max 100)}
        {--pages=1 : Max number of pages to fetch}';

    protected $description = 'Sync Otpravci (dispatches) from e-Komunikacija API into local database';

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

        $size = (int) ($this->option('size') ?? config('ekom.default_page_size'));
        $size = max(1, min(100, $size));
        $pages = max(1, (int) $this->option('pages'));

        $count = $service->syncOtpravci($filters, $pages, $size);
        $this->info("Synced {$count} otpravak(a).");
        return self::SUCCESS;
    }
}
