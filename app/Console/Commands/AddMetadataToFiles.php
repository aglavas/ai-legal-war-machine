<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use setasign\Fpdi\Tfpdf\Fpdi;

class AddMetadataToFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:add-metadata-to-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filesPath = "/reposss/FileoviMetadata";
        $metadataPath = storage_path('app/tagged');
        $mappingPath = $metadataPath . '/mappping.json';
        $mappingJson = file_get_contents($mappingPath);
        $mappingArray = json_decode($mappingJson, true);

        $progressBar = $this->output->createProgressBar(count($mappingArray));
        $progressBar->start();

        foreach ($mappingArray as $mapping) {
            $progressBar->advance();
            $filePath = $mapping['file_path'] ?? null;
            $fileMetadataPath = $mapping['file_response_metadata'] ?? null;
            $outPath = Str::replace('/reposss/Fileovi', $filesPath, $filePath);
            $metaJson = file_get_contents($fileMetadataPath);
            $this->addMeta($metaJson, $filePath);
            $this->info("JSON ugrađen u XMP dc:Description: {$outPath}");
            dd($outPath);
        }

        $progressBar->finish();
        $this->info('Gotovo.');
        dd($mappingArray);
    }

    public function addMeta(string $jsonInput, string $inputPdfPath): void
    {
        if (is_string($jsonInput)) {
            try {
                $decoded = json_decode($jsonInput, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                // Not valid JSON; print as-is
                $decoded = null;
            }
        } else {
            $decoded = $jsonInput; // already array/object
        }

        $prettyJson = $decoded !== null
            ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : (string) $jsonInput;

        // Create new PDF and prepare to import the old one
        $pdf = new Fpdi(); // tFPDF + FPDI
        $pdf->SetAutoPageBreak(true, 15);
        $pageCount = $pdf->setSourceFile($inputPdfPath);

        // Determine page size/orientation from original first page so the new page matches
        $tplForSize = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($tplForSize);

        // Add fonts (monospaced TTF for JSON)
        $fontPath = resource_path('fonts/DejaVuSansMono.ttf');
        if (!file_exists($fontPath)) {
            dd($fontPath);
        }
        // Add TTF font and use it
        $pdf->AddFont('DejaVuSansMono', '', 'DejaVuSansMono.ttf', true);
        $pdf->SetFont('DejaVuSansMono', '', 9);

        // Create the new first page and print the JSON
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->SetMargins(12, 15, 12);
        $pdf->SetTextColor(0, 0, 0);

        // Optional title
        $pdf->SetFont('DejaVuSansMono', '', 12);
        $pdf->Cell(0, 8, 'JSON Payload', 0, 1, 'L');
        $pdf->Ln(2);

        // Body (JSON)
        $pdf->SetFont('DejaVuSansMono', '', 9);
        // MultiCell width 0 means use remaining line width; height 5mm per line
        // MultiCell automatically page-breaks if content is long
        $pdf->MultiCell(0, 5, $prettyJson);

        // Append original PDF pages
        for ($i = 1; $i <= $pageCount; $i++) {
            $tpl = $pdf->importPage($i);
            $ps = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($ps['orientation'], [$ps['width'], $ps['height']]);
            $pdf->useTemplate($tpl);
        }

        // Output
        $outPath = 'tmp/prepended_' . Str::uuid() . '.pdf';
        $fullOut = storage_path('app/' . $outPath);
        $pdf->Output($fullOut, 'F');
    }

}
