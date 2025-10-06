<?php

namespace App\Console\Commands;

use App\Services\Pdf\PdfArticleSplitter2 as PdfArticleSplitter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SplitLawPdf extends Command
{
    protected $signature = 'hrlaws:split-pdf
        {pdf : Putanja do PDF-a zakona}
        {--out= : Izlazni direktorij (default: storage/app/hr-laws/split/<ime_pdfa>/)}
        {--mode=pages : pages|render}
        {--title= : Naslov zakona (npr. "Zakon o ...")}
        {--eli= : ELI identifikator (opcionalno)}
        {--pubdate= : Datum objave, npr. 2025-09-17}
        {--start-page=1 : Početna stranica (preskoči sadržaj/uvod ako je potrebno)}
        {--only= : Obradi samo određene članke (npr. 1,2,3a; bez razmaka)}
        {--sidecar : Zapiši .attrs.json uz svaku izlaznu PDF datoteku}
        {--embed-xmp : Ugradi JSON u XMP dc:Description (zahtijeva exiftool u PATH-u)}
        {--attrs= : Dodatni atributi za pretraživanje (CSV), npr. grupa=Zakoni,izvor=NN}
        {--title-normalize=none : none|snake|kebab – način normalizacije u nazivu file-a}
        {--dry : Suhi run – bez stvaranja datoteka}';

    protected $description = 'Razloži lokalni PDF zakona na male PDF-ove po članku (Članak N.)';

    public function handle(PdfArticleSplitter $splitter): int
    {
        $pdf = $this->argument('pdf');
        $mode = $this->option('mode') ?: 'pages';
        $title = $this->option('title');
        $eli = $this->option('eli');
        $pubDate = $this->option('pubdate');
        $startPage = (int)($this->option('start-page') ?: 1);
        $only = $this->option('only') ? array_filter(array_map('trim', explode(',', $this->option('only')))) : [];
        $sidecar = (bool)$this->option('sidecar');
        $embedXmp = (bool)$this->option('embed-xmp');
        $dry = (bool)$this->option('dry');
        $attrsCsv = (string)($this->option('attrs') ?? '');
        $titleNormalize = (string)($this->option('title-normalize') ?? 'none');

        if (!in_array($mode, ['pages', 'render'])) {
            $this->error("Nepoznat --mode: $mode (dozvoljeno: pages, render)");
            return self::INVALID;
        }

        // Provjeri ovisnosti (pdftotext, pdfinfo)
        foreach (['pdftotext', 'pdfinfo'] as $bin) {
            $which = trim((string)shell_exec("which $bin"));
            if ($which === '') {
                $this->error("Nedostaje alat '$bin'. Instaliraj poppler-utils (npr. sudo apt-get install poppler-utils).");
                return self::FAILURE;
            }
        }
        if ($embedXmp) {
            $which = trim((string) shell_exec("which exiftool"));
            if ($which === '') {
                $this->warn("Upozorenje: --embed-xmp traži exiftool u PATH-u; opcija će biti ignorirana.");
            }
        }

        $out = $this->option('out');
        if (!$out) {
            $baseName = pathinfo($pdf, PATHINFO_FILENAME);
            $out = storage_path('app/hr-laws/split/'.$baseName);
        }
        @mkdir($out, 0775, true);

        // Normalizacija naziva zbog konzistentnog imenovanja datoteka
        if ($titleNormalize === 'snake') {
            $title = $title ? Str::snake($title) : null;
        } elseif ($titleNormalize === 'kebab') {
            $title = $title ? Str::kebab($title) : null;
        }

        // Parsiraj dodatne atribute iz CSV "k=v,k2=v2"
        $extraAttrs = [];
        if ($attrsCsv) {
            foreach (explode(',', $attrsCsv) as $pair) {
                $pair = trim($pair);
                if ($pair === '' || strpos($pair, '=') === false) continue;
                [$k, $v] = array_map('trim', explode('=', $pair, 2));
                if ($k !== '' && $v !== '') $extraAttrs[$k] = $v;
            }
        }

        $this->info("Ulaz: $pdf");
        $this->info("Način: $mode; Izlaz: $out");
        $this->info("Početna stranica: $startPage");

        try {
            $manifest = $splitter->split(
                pdfPath: $pdf,
                outDir: $out,
                mode: $mode,
                lawTitle: $title,
                eli: $eli,
                pubDate: $pubDate,
                startPage: $startPage,
                opts: [
                    'only_numbers' => $only,
                    'sidecar' => $sidecar,
                    'embed_xmp' => $embedXmp,
                    'extra_attrs' => $extraAttrs,
                    'dry' => $dry,
                    'title_normalize' => $titleNormalize,
                ]
            );
        } catch (\Throwable $e) {
            $this->error("Greška: ".$e->getMessage());
            return self::FAILURE;
        }

        $this->info("Gotovo. Generirano članaka: ".count($manifest));
        $this->line("Manifest: ".$out.'/manifest.json');

        return self::SUCCESS;
    }
}
