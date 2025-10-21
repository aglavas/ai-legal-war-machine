<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EoglasnaService;

class EoglasnaSyncCourts extends Command
{
    /**
     * @var string $signature
     */
    protected $signature = 'eoglasna:sync-courts';

    /**
     * @var string $description
     */
    protected $description = 'Sync court list from e-Oglasna API';

    /**
     * @param EoglasnaService $service
     */
    public function __construct(protected EoglasnaService $service)
    {
        parent::__construct();
    }

    /**
     * @return int
     */
    public function handle(): int
    {
        $count = $this->service->syncCourts();
        $this->info("Synchronized {$count} courts.");
        return self::SUCCESS;
    }
}
