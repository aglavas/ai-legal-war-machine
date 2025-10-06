<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class DownloadOdluke extends Command
{
    protected $signature = 'odluke:download
        {--q= : Slobodni upit za pretragu (primjer: "ugovor o radu")}
        {--limit=50 : Maksimalan broj odluka za preuzimanje}
        {--format=pdf : pdf|html|both}
        {--out= : Direktorij za spremanje (default: storage/app/odluke)}
        {--delay= : Pauza između zahtjeva u ms (default iz config/odluke.php)}
        {--resume : Preskoči već preuzete (po ID-u datoteke)}
        {--ids= : Zarezom odvojen popis ID-eva (decisionView?id=...); za direktan download bez pretrage}
        {--params= : Dodatni query string za /Document/DisplayList (npr. "sk=GRAĐANSKO PRAVO")}
        {--since= : Filtriraj po datumu odluke OD (YYYY-MM-DD) – primjenjuje se nakon dohvaćanja}
        {--until= : Filtriraj po datumu odluke DO (YYYY-MM-DD) – primjenjuje se nakon dohvaćanja}
        {--dry-run : Ne preuzimati datoteke, samo ispisati koje bi se preuzele}';

    protected $description = 'Preuzimanje sudskih odluka s odluke.sudovi.hr u PDF/HTML formatu uz metapodatke.';

    protected string $baseUrl;
    protected int $timeout;
    protected int $retry;
    protected int $delayMs;

    public function handle(): int
    {
        $cfg = config('odluke');
        $this->baseUrl = $cfg['base_url'] ?? 'https://odluke.sudovi.hr';
        $this->timeout = (int)($cfg['timeout'] ?? 30);
        $this->retry   = (int)($cfg['retry'] ?? 2);
        $this->delayMs = (int)($this->option('delay') ?? ($cfg['delay_ms'] ?? 700));

        $outDir = $this->option('out') ?: storage_path('app/odluke');
        if (!is_dir($outDir)) {
            @mkdir($outDir, 0775, true);
        }

        $format = strtolower($this->option('format') ?? 'pdf');
        if (!in_array($format, ['pdf','html','both'], true)) {
            $this->error('Nevažeća vrijednost --format. Dozvoljeno: pdf|html|both');
            return self::INVALID;
        }

        $limit  = (int)$this->option('limit');
        $query  = trim((string)$this->option('q'));
        $params = trim((string)$this->option('params'));
        $idsOpt = trim((string)$this->option('ids'));
        $resume = (bool)$this->option('resume');
        $dryRun = (bool)$this->option('dry-run');

        $since  = $this->option('since') ? date('Y-m-d', strtotime($this->option('since'))) : null;
        $until  = $this->option('until') ? date('Y-m-d', strtotime($this->option('until'))) : null;

        // Skupljanje ID-ova
        $ids = [];
        if ($idsOpt !== '') {
            $ids = array_values(array_filter(array_map('trim', explode(',', $idsOpt))));
        } else {
            $ids = $this->collectIdsFromList($query, $params, $limit);
            if (empty($ids)) {
                $this->warn('Nisam pronašao ID‑eve s liste. Pokušajte s --q, --params ili direktno --ids.');
                return self::FAILURE;
            }
        }

        $this->info(sprintf('Nađeno %d ID‑eva. Počinjem preuzimanje (%s)...', count($ids), strtoupper($format)));

        $processed = 0;
        foreach ($ids as $id) {
            if ($processed >= $limit) {
                break;
            }

            // Ako je --resume, preskači ako već postoji bilo koji output za taj ID.
            if ($resume && $this->alreadyDownloaded($outDir, $id, $format)) {
                $this->line("SKIP {$id} (postoji)");
                continue;
            }

            $meta = $this->fetchDecisionMeta($id);
            if (!$meta) {
                $this->warn("Ne mogu dohvatiti metapodatke za {$id}.");
                $this->sleep();
                continue;
            }

            // Lokalni filter po datumu odluke (nakon dohvaćanja)
            if ($since || $until) {
                $d = $meta['datum_odluke'] ?? null;
                if ($d) {
                    $d = date('Y-m-d', strtotime($d));
                    if ($since && $d < $since) { $this->line("SKIP {$id} (prije {$since})"); $this->sleep(); continue; }
                    if ($until && $d > $until) { $this->line("SKIP {$id} (poslije {$until})"); $this->sleep(); continue; }
                }
            }

            $basename = $this->buildBaseFileName($meta, $id); // npr. VSRH_Revd-2423-2022-2_2022-06-07_090216ba80d36218
            $savedAny = false;

            // PDF
            if (in_array($format, ['pdf','both'], true)) {
                $pdfStatus = $dryRun ? 'OK (dry-run)' : $this->downloadPdf($id, "{$outDir}/{$basename}.pdf");
                if ($pdfStatus === true || $dryRun) {
                    $savedAny = true;
                } else {
                    $this->warn("PDF nije preuzet za {$id}: {$pdfStatus}");
                }
            }

            // HTML
            if (in_array($format, ['html','both'], true)) {
                $htmlStatus = $dryRun ? 'OK (dry-run)' : $this->downloadHtml($id, "{$outDir}/{$basename}.html");
                if ($htmlStatus === true || $dryRun) {
                    $savedAny = true;
                } else {
                    $this->warn("HTML nije preuzet za {$id}: {$htmlStatus}");
                }
            }

            // Spremi metapodatke
            if ($savedAny && !$dryRun) {
                $meta['src'] = $this->baseUrl . '/decisionView?id=' . $id;
                $meta['id']  = $id;
                file_put_contents("{$outDir}/{$basename}.json", json_encode($meta, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            }

            $processed++;
            $this->sleep();
        }

        $this->info("Gotovo. Preuzeto / obrađeno: {$processed}");
        return self::SUCCESS;
    }

    protected function http()
    {
        return Http::withHeaders([
            'User-Agent'      => 'Mozilla/5.0 (compatible; OdlukeScraper/1.0; +https://example.com)',
            'Accept-Language' => 'hr-HR,hr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Referer'         => $this->baseUrl . '/',
        ])
            ->timeout($this->timeout)
            ->retry($this->retry, 500);
    }

    protected function collectIdsFromList(string $q, string $params, int $limit): array
    {
        $url = rtrim($this->baseUrl, '/') . '/Document/DisplayList';

        $qs = [];
        if ($q !== '') {
            $qs['q'] = $q;
        }

        if ($params !== '') {
            $url .= (strpos($url, '?') === false ? '?' : '&')
                . http_build_query($qs)
                . ($qs ? '&' : '')
                . ltrim($params, '&');
        } else {
            if ($qs) {
                $url .= '?' . http_build_query($qs);
            }
        }

        $this->line("Lista: {$url}");

        $resp = $this->http()->get($url);
        if (!$resp->ok()) {
            $this->warn('Neuspješan dohvat liste (' . $resp->status() . ')');
            return [];
        }

        $html = $resp->body();
        $ids = [];

        // 1) Preferiraj DOM parsiranje: pokupi sve linkove koji imaju query parametar ?id=…
        if (class_exists(\Symfony\Component\DomCrawler\Crawler::class)) {
            try {
                $crawler = new Crawler($html);
                $crawler->filter('a[href*="id="]')->each(function (Crawler $a) use (&$ids) {
                    $href = $a->attr('href') ?? '';
                    if ($href === '') return;

                    // dekodiraj relativne/HTML‑entitete i izvuci query parametre
                    $href = html_entity_decode($href, ENT_QUOTES);
                    $query = parse_url($href, PHP_URL_QUERY) ?: '';
                    parse_str($query, $qarr);
                    $id = $qarr['id'] ?? null;

                    // prihvati GUID ili heks string s crticama (min 8 znakova radi sigurnosti)
                    if ($id && preg_match('~^[0-9a-fA-F-]{8,}$~', $id)) {
                        $ids[$id] = true;
                    }
                });
            } catch (\Throwable $e) {
                // ignore i nastavi s regex fallbackom
            }
        }

        // 2) Fallback regex (ako DomCrawler nije dostupan ili nije našao ništa)
        if (!$ids) {
            if (preg_match_all(
            // hvataj i stare i nove rute: /Document/View|Text|Download i legacy decisionView|Text|Download
                '~/(?:Document/(?:View|Text|Download)|decision(?:View|Text|Download))\?[^"\']*?\bid=([0-9a-fA-F-]{8,})~',
                $html,
                $m
            )) {
                foreach ($m[1] as $id) {
                    $ids[$id] = true;
                }
            }
        }

        // Dedup + limit
        $ids = array_slice(array_keys($ids), 0, $limit);

        $this->info('Pronađeno ID‑eva na listi: ' . count($ids));
        return $ids;
    }

    protected function fetchDecisionMeta(string $id): ?array
    {
        $url = rtrim($this->baseUrl, '/') . '/Document/View?id=' . urlencode($id);
        $resp = $this->http()->get($url);
        if (!$resp->ok()) {
            return null;
        }
        $html = $resp->body();

        $text = trim(strip_tags($html));
        $meta = [
            'broj_odluke'   => $this->rx($text, '~Broj odluke:\s*([^\r\n]+)~u'),
            'sud'           => $this->rx($text, '~Sud:\s*([^\r\n]+)~u'),
            'datum_odluke'  => $this->rx($text, '~Datum odluke:\s*([0-9.\-\/]+)~u'),
            'pravomocnost'  => $this->rx($text, '~Pravomoćnost:\s*([^\r\n]+)~u'),
            'datum_objave'  => $this->rx($text, '~Datum objave:\s*([0-9.\-\/]+)~u'),
            'upisnik'       => $this->rx($text, '~Upisnik:\s*([^\r\n]+)~u'),
            'vrsta_odluke'  => $this->rx($text, '~Vrsta odluke:\s*([^\r\n]+)~u'),
            'ecli'          => $this->rx($text, '~ECLI broj:\s*([A-Z0-9:\.\-]+)~u'),
        ];

        if (class_exists(Crawler::class)) {
            try {
                $c = new Crawler($html);
                // proširi selektor i na novu rutu za download
                $dl = $c->filter('a[title*="Preuzmi"],a[aria-label*="Preuzmi"],a[href*="Download"],a[href*="decisionDownload"]');
                $meta['_download_href'] = $dl->count() ? $dl->first()->attr('href') : null;
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return $meta;
    }

    protected function rx(string $text, string $pattern): ?string
    {
        if (preg_match($pattern, $text, $m)) {
            // normaliziraj razmake
            return trim(preg_replace('~\s+~u', ' ', $m[1]));
        }
        return null;
    }

    protected function buildBaseFileName(array $meta, string $id): string
    {
        $court = $meta['sud'] ?? '';
        // izvuci kratice iz naziva suda kao grubi alias (npr. Vrhovni sud -> VSRH)
        $alias = $this->guessCourtAlias($court);

        $broj  = $meta['broj_odluke'] ?? 'NEPOZNATO';
        $date  = $meta['datum_odluke'] ? date('Y-m-d', strtotime($meta['datum_odluke'])) : '0000-00-00';

        $base = sprintf('%s_%s_%s_%s', $alias, $this->slug($broj), $date, $id);
        return substr($base, 0, 220); // safety limit
    }

    protected function guessCourtAlias(string $court): string
    {
        $c = mb_strtolower($court);
        return match (true) {
            str_contains($c, 'vrhovni sud')         => 'VSRH',
            str_contains($c, 'visoki kazneni sud')  => 'VKSRH',
            str_contains($c, 'visoki prekršajni')   => 'VPSRH',
            str_contains($c, 'visoki trgovački')    => 'VTSRH',
            str_contains($c, 'visoki upravni')      => 'VUSR',
            str_contains($c, 'županijski sud')      => 'ZUP',
            str_contains($c, 'općinski')            => 'OPS',
            str_contains($c, 'trgovački sud')       => 'TS',
            str_contains($c, 'upravni sud')         => 'US',
            default                                 => 'SUD',
        };
    }

    protected function slug(string $s): string
    {
        $s = preg_replace('~[^\pL\pN]+~u', '-', $s);
        $s = trim($s, '-');
        $s = mb_strtolower($s);
        $s = preg_replace('~[^a-z0-9\-]+~', '', $s);
        return $s ?: 'x';
    }

    protected function alreadyDownloaded(string $outDir, string $id, string $format): bool
    {
        // gleda postoji li bilo koja datoteka s tim ID-om u imenu
        $files = glob($outDir . '/*' . $id . '*');
        if (!$files) return false;
        if ($format === 'pdf')  return (bool)array_filter($files, fn($f) => str_ends_with($f, '.pdf'));
        if ($format === 'html') return (bool)array_filter($files, fn($f) => str_ends_with($f, '.html'));
        return true;
    }

    protected function downloadPdf(string $id, string $savePath)
    {
        $pdfUrl = null;

        // pokušaj iz decision view meta
        $meta = $this->fetchDecisionMeta($id);
        if ($meta && !empty($meta['_download_href'])) {
            $href = $meta['_download_href'];
            $pdfUrl = str_starts_with($href, 'http')
                ? $href
                : rtrim($this->baseUrl, '/') . '/' . ltrim($href, '/');
        }

        if (!$pdfUrl) {
            // nova ruta
            $pdfUrl = rtrim($this->baseUrl, '/') . '/Document/DownloadPDF?id=' . urlencode($id);
        }

        $resp = $this->http()->get($pdfUrl);
        if (!$resp->ok() || stripos((string)$resp->header('Content-Type'), 'pdf') === false) {
            $pdfUrl = rtrim($this->baseUrl, '/') . '/decisionDownload?id=' . urlencode($id);
            $resp = $this->http()->get($pdfUrl);
            if (!$resp->ok()) {
                return 'HTTP ' . $resp->status();
            }
            if (stripos((string)$resp->header('Content-Type'), 'pdf') === false) {
                return 'Content-Type nije PDF (' . ($resp->header('Content-Type') ?? 'n/a') . ')';
            }
        }

        if (!@file_put_contents($savePath, $resp->body())) {
            return 'Ne mogu zapisati datoteku';
        }
        return true;
    }

    protected function downloadHtml(string $id, string $savePath)
    {
        $primary = rtrim($this->baseUrl, '/') . '/Document/Text?id=' . urlencode($id);
        $resp = $this->http()->get($primary);
        if (!$resp->ok()) {
            $fallback = rtrim($this->baseUrl, '/') . '/decisionText?id=' . urlencode($id);
            $resp = $this->http()->get($fallback);
            if (!$resp->ok()) {
                return 'HTTP ' . $resp->status();
            }
        }

        $html = $resp->body();
        $hasHtmlTag = stripos($html, '<html') !== false || stripos($html, '<body') !== false;
        if (!$hasHtmlTag) {
            $html = '<!doctype html><meta charset="utf-8"><pre style="white-space:pre-wrap;font:14px/1.4 sans-serif">'
                . htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</pre>';
        }

        if (!@file_put_contents($savePath, $html)) {
            return 'Ne mogu zapisati HTML datoteku';
        }

        return true;
    }

    protected function sleep(): void
    {
        usleep(max(0, $this->delayMs) * 1000);
    }
}
