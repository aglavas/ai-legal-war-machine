<?php

namespace App\Console\Commands;

use App\Services\{LawFetcher, LawParser, MetadataBuilder, NnApiClient, PdfRenderer};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportCroatianLaws extends Command
{
    protected $signature = 'hrlaws:ingest
        {--since= : Početna godina (npr. 2022)}
        {--only-latest=1 : Zaustavi nakon pronalaska prvog najnovijeg pročišćenog}
        {--limit= : Max broj akata za obradu}
        {--out= : Root izlaznog direktorija (default: storage/app/hr-laws)}
        {--mode=render : render (PDF iz HTML članaka)}
        {--sidecar : Zapiši .json metapodatke uz svaki članak}
        {--embed-xmp : Ugradi JSON u XMP dc:Description (exiftool)}
        {--attrs= : Dodatni atributi (CSV k=v) za pretraživanje}
        {--only-consolidated=1 : Uzimaj samo pročišćene tekstove}
        {--throttle-ms=350 : Pauza između NN poziva}';

    protected $description = 'Dohvati pročišćene tekstove iz NN, razlomi po člancima i generiraj PDF + metapodatke';

    public function handle(
        NnApiClient $api,
        LawFetcher $fetcher,
        LawParser $parser,
        PdfRenderer $pdf,
        MetadataBuilder $meta
    ): int {
        $since = $this->option('since') ? (int)$this->option('since') : null;
        $onlyLatest = (bool)$this->option('only-latest');
        $onlyConsolidated = (bool)$this->option('only-consolidated');
        $limit = $this->option('limit') ? (int)$this->option('limit') : null;
        $outRoot = rtrim($this->option('out') ?: storage_path('app/hr-laws'), '/');
        $mode = $this->option('mode') ?: 'render';
        $sidecar = (bool)$this->option('sidecar');
        $embedXmp = (bool)$this->option('embed-xmp');
        $throttleMs = (int)$this->option('throttle-ms');

        // parse extra attrs
        $extraAttrs = [];
        $attrsCsv = (string)($this->option('attrs') ?? '');
        if ($attrsCsv) {
            foreach (explode(',', $attrsCsv) as $pair) {
                $pair = trim($pair);
                if ($pair === '' || strpos($pair, '=') === false) continue;
                [$k, $v] = array_map('trim', explode('=', $pair, 2));
                if ($k !== '' && $v !== '') $extraAttrs[$k] = $v;
            }
        }

        $countActs = 0;

        foreach ($fetcher->latestConsolidations($since) as $act) {
            if ($onlyConsolidated && !$act['is_consolidated']) {
                if ($throttleMs > 0) usleep($throttleMs * 1000);
                continue;
            }

            $countActs++;
            $this->info("Akt: {$act['title']} [{$act['year']}/{$act['edition']}/{$act['act']}] ({$act['date_publication']})");

            // Dohvati HTML za parsiranje
            $htmlUrl = $act['html_url'];
            if (!$htmlUrl) {
                $this->warn("No HTML/printhtml for {$act['title']}, skipping.");
                if ($onlyLatest || ($limit && $countActs >= $limit)) break;
                continue;
            }
            $html = @file_get_contents($htmlUrl);
            if ($html === false) {
                $this->warn("Failed to download HTML for {$act['title']}, skipping.");
                if ($onlyLatest || ($limit && $countActs >= $limit)) break;
                continue;
            }

            $articles = $parser->splitIntoArticles($html);

            // Katalozi za verziju
            $baseDir = sprintf('%s/%d/%d/%s/v%s', $outRoot, $act['year'], $act['edition'], $act['act'], $act['date_publication']);
            foreach ($articles as $art) {
                $articleNumber = (string) $art['number'];
                $fileName = sprintf('article-%s.pdf', $articleNumber);
                $destPath = $baseDir.'/'.$fileName;

                $searchTags = array_filter([
                    $act['title'],
                    'Članak '.$articleNumber,
                    $act['eli_resource'],
                    $act['date_publication'],
                ]);
                foreach ($extraAttrs as $k => $v) $searchTags[] = "{$k}:{$v}";

                // Render PDF iz članka HTML
                $ctx = [
                    'law_title' => $act['title'],
                    'law_eli' => $act['eli_resource'],
                    'law_pub_date' => $act['date_publication'],
                    'article_number' => $articleNumber,
                    'article_html' => $art['html'],
                    'generated_at' => gmdate('Y-m-d H:i:s').'Z',
                    'generator_version' => '1.0.0',
                    'search_tags' => array_unique($searchTags),
                ];

                @mkdir(dirname($destPath), 0775, true);
                $pdf->renderArticle($ctx, storage_path('app/'.$destPath));

                $bytes = filesize(storage_path('app/'.$destPath));
                $sha256 = hash_file('sha256', storage_path('app/'.$destPath));

                // Metapodaci (schema-friendly)
                $metaArr = $meta->buildArticleMetadata([
                    'title' => $act['title'],
                    'eli_resource' => $act['eli_resource'],
                    'eli_expression' => $act['eli_expression'],
                    'html_url' => $act['html_url'],
                    'pdf_url' => $act['pdf_url'],
                    'year' => $act['year'],
                    'edition' => $act['edition'],
                    'act' => $act['act'],
                    'type_document' => $act['type_document'],
                    'is_consolidated' => $act['is_consolidated'],
                    'date_publication' => $act['date_publication'],
                    'article_number' => $articleNumber,
                    'heading_chain' => $art['heading_chain'] ?? [],
                    'file_path' => $destPath,
                    'file_bytes' => $bytes,
                    'file_sha256' => $sha256,
                ]);

                Storage::put($baseDir.'/article-'.$articleNumber.'.json', json_encode($metaArr, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

                // “Search attributes” sidecar + XMP
                $attrs = array_merge([
                    'title' => $act['title'],
                    'article' => $articleNumber,
                    'eli' => (string) ($act['eli_resource'] ?? ''),
                    'publication_date' => (string) ($act['date_publication'] ?? ''),
                    'keywords' => implode(', ', array_unique($searchTags)),
                    'file_name' => basename($destPath),
                    'sha256' => $sha256,
                ], $extraAttrs);

                if ($sidecar) {
                    Storage::put($baseDir.'/article-'.$articleNumber.'.attrs.json', json_encode($attrs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
                if ($embedXmp) {
                    $this->embedJsonXmp(storage_path('app/'.$destPath), $attrs);
                }
            }

            // manifest na razini akta/verzije
            $manifest = [
                'eli_resource' => $act['eli_resource'],
                'date_publication' => $act['date_publication'],
                'count_articles' => count($articles),
                'generated_at' => gmdate('c'),
            ];
            Storage::put($baseDir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

            if ($onlyLatest || ($limit && $countActs >= $limit)) break;
            if ($throttleMs > 0) usleep($throttleMs * 1000);
        }

        $this->info("Done. Consolidated acts processed: {$countActs}");
        return self::SUCCESS;
    }

    private function embedJsonXmp(string $pdfPath, array $json): void
    {
        $bin = trim((string) shell_exec("which exiftool"));
        if ($bin === '') {
            $this->warn("Preskačem XMP: exiftool nije pronađen.");
            return;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'xmp_').'.json';
        file_put_contents($tmp, json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        try {
            $args = [$bin, '-charset','UTF8','-P','XMP:Label=AI_Metadata','XMP:MetadataDate=now','XMP-dc:Description<='.$tmp,'-overwrite_original',$pdfPath];
            $p = proc_open($args, [1=>['pipe','w'], 2=>['pipe','w']], $pipes);
            if (is_resource($p)) {
                stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
                proc_close($p);
            }
        } finally {
            @unlink($tmp);
        }
    }
}
