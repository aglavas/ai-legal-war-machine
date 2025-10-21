<?php

namespace App\Console\Commands;

use App\Services\EkomService;
use Illuminate\Console\Command;

class EkomCreatePodnesakCommand extends Command
{
    protected $signature = 'ekom:podnesci:create
        {--json= : Path to JSON meta file (CreateEkomPodnesakRequest)}
        {--file=* : One or more file paths to attach}';

    protected $description = 'Create a new Podnesak draft (multipart upload with JSON meta + files)';

    public function handle(EkomService $service): int
    {
        $jsonPath = $this->option('json');
        $files = $this->option('file');

        if (!$jsonPath || !is_file($jsonPath)) {
            $this->error('Please provide --json path to meta file.');
            return self::INVALID;
        }
        if (empty($files)) {
            $this->error('Please provide at least one --file to attach.');
            return self::INVALID;
        }
        foreach ($files as $path) {
            if (!is_file($path)) {
                $this->error("File not found: {$path}");
                return self::INVALID;
            }
        }

        $payload = json_decode(file_get_contents($jsonPath), true);
        if (!is_array($payload)) {
            $this->error('Invalid JSON payload.');
            return self::INVALID;
        }

        try {
            $result = $service->createPodnesak($payload, $files);
            $this->info('Created Podnesak: ' . json_encode($result));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
