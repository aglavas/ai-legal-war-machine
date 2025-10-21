<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EoglasnaService;
use Illuminate\Support\Facades\Log;

class EoglasnaWatch extends Command
{
    /**
     * @var string $signature
     */
    protected $signature = 'eoglasna:watch
        {--deep= : Run deep scan for exact term}
        {--scope=notice : Scope: notice|court|institution|court_legal_bankruptcy|court_natural_bankruptcy}';

    /**
     * @var string $description
     */
    protected $description = 'Monitor e-Oglasna feed for predefined keywords or run a deep scan for an exact term across all pagination.';

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
        $deep = $this->option('deep');
        $scope = $this->option('scope') ?: 'notice';

        if ($deep !== null && $deep !== '') {
            $this->info("Running deep scan for term [{$deep}] in scope [{$scope}]...");
            $count = $this->service->deepScanExact($deep, $scope);
            $this->info("Deep scan complete. Exact matches persisted: {$count}");
            return self::SUCCESS;
        }

        $this->info('Monitoring e-Oglasna feed for predefined keywords...');
        try {
            $this->service->monitorKeywords();
            $this->info('Monitoring run completed.');
        } catch (\Throwable $e) {
            Log::error('eoglasna:watch failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->error('Monitoring failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
