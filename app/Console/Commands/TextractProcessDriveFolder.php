<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoogleDriveService;
use App\Jobs\ProcessDrivePdfJob;
use Illuminate\Console\Attributes\AsCommand;

#[AsCommand(name: 'textract:process-drive-folder', description: 'Dohvati PDF-ove iz Google Drive foldera i pošalji na Textract OCR + rekonstrukciju')]
class TextractProcessDriveFolder extends Command
{
    protected $signature = 'textract:process-drive-folder {folderId?} {--limit=0}';
    protected $description = 'Dohvati PDF-ove iz Google Drive foldera i pošalji na Textract OCR + rekonstrukciju';

    public function handle(GoogleDriveService $drive)
    {
        $folderId = (string) ($this->argument('folderId') ?: env('GOOGLE_DRIVE_FOLDER_ID'));
        if (!$folderId) {
            $this->error('Folder ID nije zadan. Koristite argument ili .env GOOGLE_DRIVE_FOLDER_ID.');
            return Command::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $files = $drive->listPdfsInFolder($folderId);

        if (empty($files)) {
            $this->info('Nema PDF-ova u folderu.');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($files as $f) {
            if ($limit > 0 && $count >= $limit) break;

            ProcessDrivePdfJob::dispatch($f['id'], $f['name'])
                ->onQueue('textract');
            $this->info("Queued: {$f['name']} ({$f['id']})");
            $count++;
        }

        $this->info("Ukupno stavljeno u red: {$count}");
        return Command::SUCCESS;
    }
}
