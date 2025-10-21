<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EoglasnaService;
use Illuminate\Support\Facades\Log;

class EoglasnaWatchOsijek extends Command
{
    protected $signature = 'eoglasna:watch-osijek';

    protected $description = 'Fetch and upsert ALL e-Oglasna items originating from Općinski sud u Osijeku.';

    public function __construct(protected EoglasnaService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Fetching ALL e-Oglasna items from Općinski sud u Osijeku...');
        try {
            $count = $this->service->monitorOsijekCourtAll();
            $this->info("Osijek court monitoring done. Items processed: {$count}");
        } catch (\Throwable $e) {
            Log::error('eoglasna:watch-osijek failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->error('Osijek monitoring failed: ' . $e->getMessage());
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}

