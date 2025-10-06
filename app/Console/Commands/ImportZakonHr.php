<?php

namespace App\Console\Commands;

use App\Services\{LawParser, MetadataBuilder, PdfRenderer};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportZakonHr extends Command
{
    protected $signature = 'hrlaws:import-zakonhr
        {--url= : URL pojedinačnog zakona na zakon.hr}
        {--list-file= : Putanja do .txt s popisom URL-ova (po jedan u retku)}
        {--out= : Root izlaznog direktorija (default: storage/app/hr-laws/zakonhr)}
        {--title= : Override naslova (ako HTML nema čist naslov)}
        {--date= : Datum objave (YYYY-MM-DD)}
        {--pdf-service-url= : Vanjski servis za HTML→PDF (POST {html}, Content-Type: application/pdf)}
        {--sidecar : Zapiši .json atribute uz članak}
        {--embed-xmp : Ugradi JSON u XMP uz exiftool}
        {--attrs= : Dodatni atributi (CSV k=v)}
        {--dry : Suhi run}';

    protected $description = 'Uvoz zakona sa zakon.hr: scrape HTML-a, razloma u članke i konverzija u PDF (lokalno ili preko vanjskog servisa).';

    public function handle(LawParser $parser, PdfRenderer $renderer, MetadataBuilder $mb): int
    {
        $url = (string)($this->option('url') ?? '');
        $listFile = (string)($this->option('list-file') ?? '');
        $outRoot = rtrim($this->option('out') ?: storage_path('app/hr-laws/zakonhr'), '/');
        $titleOverride = $this->option('title') ?: null;
        $pubDate = $this->option('date') ?: null;
        $service = $this->option('pdf-service-url') ?: null;
        $sidecar = (bool)$this->option('sidecar');
        $embedXmp = (bool)$this->option('embed-xmp');
        $dry = (bool)$this->option('dry');

        // parse attrs
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

        // Skupi listu URL-ova
        $urls = [];
        if ($url) $urls[] = $url;
        if ($listFile && is_file($listFile)) {
            foreach (file($listFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $u) {
                $u = trim($u); if ($u) $urls[] = $u;
            }
        }
        $urls = array_values(array_unique($urls));
        if (!$urls) {
            $this->error('Nije specificiran --url ili --list-file.');
            return self::INVALID;
        }

        foreach ($urls as $one) {
            $this->info("GET: {$one}");
            $resp = @file_get_contents($one);
            if ($resp === false) {
                $this->warn("Neuspješno dohvaćanje: {$one}");
                continue;
            }

            // Nađi neku heuristiku za title iz <title> ili H1
            $title = $titleOverride ?: $this->extractTitle($resp) ?: 'Zakon (zakon.hr)';
            $slug = Str::slug($title);

            // Split na članke
            $articles = $parser->splitIntoArticles($resp);

            $baseDir = "{$outRoot}/{$slug}/".($pubDate ?: date('Y-m-d'));
            @mkdir(storage_path('app/'.$baseDir), 0775, true);

            foreach ($articles as $art) {
                $num = (string)$art['number'];
                $fileName = "clanak-{$num}.pdf";
                $fileName = $title.' - '.$fileName;
                $dest = storage_path('app/'.$baseDir.'/'.$fileName);

                // Lokalni render ili vanjski servis
                if (!$dry) {
                    if ($service) {
                        $html = view('pdf.article', [
                            'law_title' => $title,
                            'law_eli' => '',
                            'law_pub_date' => $pubDate ?: '',
                            'article_number' => $num,
                            'article_html' => $art['html'],
                            'generated_at' => gmdate('Y-m-d H:i:s').'Z',
                            'generator_version' => '1.0.0',
                            'search_tags' => array_unique([$title, 'Članak '.$num, $pubDate]),
                        ])->render();

                        $r = Http::asJson()->post($service, ['html' => $html]);
                        if (!$r->successful() || stripos($r->header('Content-Type', ''), 'application/pdf') === false) {
                            $this->warn("Servis nije vratio PDF (status {$r->status()}); fallback na lokalni render.");
                            $renderer->renderArticle([
                                'law_title' => $title,
                                'law_eli' => '',
                                'law_pub_date' => $pubDate ?: '',
                                'article_number' => $num,
                                'article_html' => $art['html'],
                                'generated_at' => gmdate('Y-m-d H:i:s').'Z',
                                'generator_version' => '1.0.0',
                                'search_tags' => array_unique([$title, 'Članak '.$num, $pubDate]),
                            ], $dest);
                        } else {
                            file_put_contents($dest, $r->body());
                        }
                    } else {
                        $renderer->renderArticle([
                            'law_title' => $title,
                            'law_eli' => '',
                            'law_pub_date' => $pubDate ?: '',
                            'article_number' => $num,
                            'article_html' => $art['html'],
                            'generated_at' => gmdate('Y-m-d H:i:s').'Z',
                            'generator_version' => '1.0.0',
                            'search_tags' => array_unique([$title, 'Članak '.$num, $pubDate]),
                        ], $dest);
                    }
                }

                $bytes = $dry ? null : @filesize($dest);
                $sha256 = $dry ? null : @hash_file('sha256', $dest);

                // Metadata JSON (schema-ish)
                $meta = $mb->buildArticleMetadata([
                    'title' => $title,
                    'eli_resource' => null,
                    'eli_expression' => null,
                    'html_url' => $one,
                    'pdf_url' => null,
                    'year' => (int)date('Y'),
                    'edition' => null,
                    'act' => $slug,
                    'type_document' => 'law',
                    'is_consolidated' => false,
                    'date_publication' => $pubDate ?: date('Y-m-d'),
                    'article_number' => $num,
                    'heading_chain' => $art['heading_chain'] ?? [],
                    'file_path' => $baseDir.'/'.$fileName,
                    'file_bytes' => $bytes,
                    'file_sha256' => $sha256,
                ]);
                if (!$dry) {
                    Storage::put($baseDir."/article-{$num}.json", json_encode($meta, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
                }

                $attrs = array_merge([
                    'title' => $title,
                    'article' => $num,
                    'eli' => '',
                    'publication_date' => (string)($pubDate ?: ''),
                    'keywords' => implode(', ', array_unique([$title, 'Članak '.$num, $pubDate])),
                    'file_name' => $fileName,
                    'sha256' => (string)$sha256,
                    'source' => 'zakon.hr',
                ], $extraAttrs);

                if ($sidecar && !$dry) {
                    Storage::put($baseDir."/article-{$num}.attrs.json", json_encode($attrs, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
                }
            }

            $manifest = [
                'source' => 'zakon.hr',
                'url' => $one,
                'title' => $title,
                'date_publication' => $pubDate ?: null,
                'count_articles' => count($articles),
                'generated_at' => gmdate('c'),
            ];
            if (!$dry) {
                Storage::put($baseDir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            }
        }

        return self::SUCCESS;
    }

    private function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return null;
    }
}
