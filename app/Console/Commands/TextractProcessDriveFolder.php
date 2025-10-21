<?php

namespace App\Console\Commands;

use Google\Service\Exception;
use Illuminate\Console\Command;
use App\Actions\Textract\ListDrivePdfs;
use App\Actions\Textract\EnsureTextractJob;
use App\Actions\Textract\ProcessDrivePdf;
use App\Models\LegalCase;
use App\Models\TextractJob;

class TextractProcessDriveFolder extends Command
{
    protected $signature = 'textract:process-drive-folder {folderId?} {--limit=0} {--sync} {--force} {--case=}';
    protected $description = 'Dohvati PDF-ove iz Google Drive foldera i pošalji na Textract OCR + rekonstrukciju';

    /**
     * @throws Exception
     */
    public function handle()
    {
        $folderId = (string) ($this->argument('folderId') ?: env('GOOGLE_DRIVE_FOLDER_ID'));
        if (!$folderId) {
            $this->error('Folder ID nije zadan. Koristite argument ili .env GOOGLE_DRIVE_FOLDER_ID.');
            return Command::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');
        $sync = (bool) $this->option('sync');
        $caseId = (string) ($this->option('case') ?? '');
        if ($caseId === '') {
            $this->error('Case ID is required. Provide with --case=CASE_ID');
            return Command::FAILURE;
        }
        if (!LegalCase::query()->where('id', $caseId)->exists()) {
            $this->error('Selected case not found: ' . $caseId);
            return Command::FAILURE;
        }

        $this->info("Listing PDFs in Drive folder: {$folderId}");
        $files = ListDrivePdfs::run($folderId);

        if (empty($files)) {
            $this->info('Nema PDF-ova u folderu.');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($files as $f) {
            if ($limit > 0 && $count >= $limit) break;

            $driveId = (string) ($f['id'] ?? '');
            $name = (string) ($f['name'] ?? 'unknown.pdf');

            if ($driveId === '') {
                $this->warn('Skipping file with missing id.');
                continue;
            }

            // Centralized skip/create logic
            $decision = EnsureTextractJob::run($driveId, $name, $force);
            if (!$decision['shouldProcess']) {
                $this->info("SKIP (already done): {$name} ({$driveId})");
                continue;
            }

            // Ensure job has the case assigned
            /** @var TextractJob $job */
            $job = $decision['job'];
            if ($job && $job->case_id !== $caseId) {
                $job->update(['case_id' => $caseId]);
            }

            if ($sync) {
                $this->info("RUN SYNC: {$name} ({$driveId})");
                ProcessDrivePdf::run($driveId, $name);
            } else {
                $this->info("QUEUE: {$name} ({$driveId})");
                ProcessDrivePdf::dispatch($driveId, $name);
            }

            $count++;
        }

        $this->info("Ukupno obrađeno/poslano u red: {$count}");
        return Command::SUCCESS;
    }
}
