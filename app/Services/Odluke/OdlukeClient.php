<?php

namespace App\Services\Odluke;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class OdlukeClient
{
    /**
     * @var int $rpm
     */
    protected int $rpm;

    /**
     * @var int $backoffMs
     */
    protected int $backoffMs;

    /**
     * @var float $lastCallAt
     */
    protected static float $lastCallAt = 0.0; // monotonic spacing between requests (per-process)

    /**
     * @param string $baseUrl
     * @param int $timeout
     * @param int $retry
     * @param int $delayMs
     * @param int|null $rpm
     * @param int|null $backoffMs
     */
    public function __construct(
        protected string $baseUrl,
        protected int $timeout = 30,
        protected int $retry   = 2,
        protected int $delayMs = 700,
        ?int $rpm = null,
        ?int $backoffMs = null,
    ) {
        $cfgRpm = $rpm ?? (int) (config('odluke.rpm') ?? 30);
        $this->rpm = $cfgRpm > 0 ? $cfgRpm : 30;
        $this->backoffMs = $backoffMs ?? (int) (config('odluke.backoff_ms') ?? 800);
    }

    /**
     * @return self
     */
    public static function fromConfig(): self
    {
        $cfg = config('odluke');
        return new self(
            baseUrl: rtrim($cfg['base_url'] ?? 'https://odluke.sudovi.hr', '/'),
            timeout: (int) ($cfg['timeout'] ?? 30),
            retry:   (int) ($cfg['retry'] ?? 2),
            delayMs: (int) ($cfg['delay_ms'] ?? 700),
            rpm:     (int) ($cfg['rpm'] ?? 30),
            backoffMs: (int) ($cfg['backoff_ms'] ?? 800),
        );
    }

    /**
     * @param string|null $baseUrl
     * @return $this|self
     */
    public function withBaseUrl(?string $baseUrl): self
    {
        if (!$baseUrl) return $this;
        return new self($baseUrl, $this->timeout, $this->retry, $this->delayMs, $this->rpm, $this->backoffMs);
    }

    /**
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function http()
    {
        // Simple retry with increasing sleep when 429/5xx
        $sleepMs = max(100, $this->delayMs);
        return Http::withHeaders([
            'User-Agent'      => 'Mozilla/5.0 (compatible; OdlukeMCP/1.0)',
            'Accept-Language' => 'hr-HR,hr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Referer'         => $this->baseUrl . '/',
        ])->timeout($this->timeout)
          ->retry($this->retry, $sleepMs, function ($exception, $request) {
              // Backoff on 429/5xx
              usleep($this->backoffMs * 1000);
              return true;
          });
    }

    /**
     * @return void
     */
    protected function throttle(): void
    {
        $minIntervalMs = (int) max($this->delayMs, floor(1000 / max(1, $this->rpm)));
        $now = microtime(true) * 1000;
        $waitMs = (int) max(0, (self::$lastCallAt + $minIntervalMs) - $now);
        if ($waitMs > 0) {
            usleep($waitMs * 1000);
        }
        self::$lastCallAt = microtime(true) * 1000;
    }

    /**
     * @param string|null $q
     * @param string|null $params
     * @param int $limit
     * @param int $page
     * @return array
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function collectIdsFromList(?string $q, ?string $params, int $limit = 50, int $page = 1): array
    {
        $url = $this->baseUrl . '/Document/DisplayList';
        $qs  = [];
        if ($q !== null && $q !== '') $qs['q'] = $q;
        if ($page > 1) $qs['page'] = $page;

        if ($params && $params !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?')
                . http_build_query($qs)
                . ($qs ? '&' : '')
                . ltrim($params, '&');
        } else {
            if ($qs) $url .= '?' . http_build_query($qs);
        }

        $this->throttle();
        $resp = $this->http()->get($url);
        if (!$resp->ok()) {
            return ['url' => $url, 'ids' => [], 'status' => $resp->status()];
        }

        $html = $resp->body();
        $ids  = $this->extractIds($html, $limit);

        return [
            'url' => $url,
            'ids' => $ids,
            'count' => count($ids),
        ];
    }

    /**
     * @param string $html
     * @param int $limit
     * @return array
     */
    protected function extractIds(string $html, int $limit): array
    {
        $found = [];

        // 1) DomCrawler
        try {
            if (class_exists(Crawler::class)) {
                $crawler = new Crawler($html);
                $crawler->filter('a[href*="id="]')->each(function (Crawler $a) use (&$found) {
                    $href  = $a->attr('href') ?? '';
                    if ($href === '') return;
                    $href  = html_entity_decode($href, ENT_QUOTES);
                    $query = parse_url($href, PHP_URL_QUERY) ?: '';
                    parse_str($query, $q);
                    $id = $q['id'] ?? null;
                    if ($id && preg_match('~^[0-9a-fA-F-]{8,}$~', $id)) {
                        $found[$id] = true;
                    }
                });
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // 2) Regex fallback za /Document/* i legacy decision*
        if (!$found) {
            if (preg_match_all(
                '~/(?:Document/(?:View|Text|Download)|decision(?:View|Text|Download))\?[^"\']*?\bid=([0-9a-fA-F-]{8,})~',
                $html,
                $m
            )) {
                foreach ($m[1] as $id) $found[$id] = true;
            }
        }

        return array_slice(array_keys($found), 0, $limit);
    }

    /**
     * @param string $id
     * @return array|null
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function fetchDecisionMeta(string $id): ?array
    {
        $url  = $this->baseUrl . '/Document/View?id=' . urlencode($id);
        $this->throttle();
        $resp = $this->http()->get($url);
        if (!$resp->ok()) return null;

        $html = $resp->body();
        $meta = [];

        // 1) Prefer structured DOM parsing
        try {
            if (class_exists(Crawler::class)) {
                $parsed = $this->parseMetadataModal($html);
                if ($parsed) {
                    $meta = $parsed;
                }
            }
        } catch (\Throwable $e) {
            // ignore and fallback to regex
        }

        // 2) Fallback to previous regex scraping for any missing fields
        $text = trim(strip_tags($html));
        $rx = fn (string $p) => (preg_match($p, $text, $m) ?  trim(preg_replace('~\s+~u', ' ', $m[1])) : null);

        $meta += array_filter([
            'broj_odluke'   => $meta['broj_odluke']   ?? $rx('~Broj odluke:\s*([^\r\n]+)~u'),
            'sud'           => $meta['sud']           ?? $rx('~Sud:\s*([^\r\n]+)~u'),
            'datum_odluke'  => $meta['datum_odluke']  ?? $this->normalizeHrDate($rx('~Datum odluke:\s*([0-9.\-\/]+)~u')),
            'pravomocnost'  => $meta['pravomocnost']  ?? $rx('~Pravomoćnost:\s*([^\r\n]+)~u'),
            'datum_objave'  => $meta['datum_objave']  ?? $this->normalizeHrDate($rx('~Datum objave:\s*([0-9.\-\/]+)~u')),
            'upisnik'       => $meta['upisnik']       ?? $rx('~Upisnik:\s*([^\r\n]+)~u'),
            'vrsta_odluke'  => $meta['vrsta_odluke']  ?? $rx('~Vrsta odluke:\s*([^\r\n]+)~u'),
            'ecli'          => $meta['ecli']          ?? $rx('~ECLI broj:\s*([A-Z0-9:\.\-]+)~u'),
        ], static fn($v) => $v !== null && $v !== '');

        // 3) Always include source and a direct download hint
        $meta['src'] = $url;
        try {
            if (class_exists(Crawler::class)) {
                $meta['_download_href'] = $this->baseUrl . '/Document/Download?id=' . urlencode($id);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $meta;
    }

    /**
     * Parse the structured metadata modal.
     */
    protected function parseMetadataModal(string $html): ?array
    {
        $dom = new Crawler($html);
        $container = $dom->filter('#MetadataModal .metadata')->first();
        if ($container->count() === 0) {
            $container = $dom->filter('.metadata')->first();
        }
        if ($container->count() === 0) return null;

        $meta = [
            // keep flat fields for backward compatibility
            'broj_odluke'  => null,
            'sud'          => null,
            'datum_odluke' => null,
            'pravomocnost' => null,
            'datum_objave' => null,
            'upisnik'      => null,
            'vrsta_odluke' => null,
            'ecli'         => null,

            // new structured fields
            'prethodna_odluka' => null,
            'stvarno_kazalo'   => [],
            'zakonsko_kazalo'  => [],
            'eurovoc'          => [],
        ];

        $laws = [];
        $currentLaw = null;

        $container->filter('.metadata-item')->each(function (Crawler $item) use (&$meta, &$laws, &$currentLaw) {
            $type = trim((string)($item->attr('data-metadata-type') ?? ''));

            // Simple single-value content
            $pContent = $item->filter('p.metadata-content');
            $pText = $this->crawlerText($pContent);

            switch ($type) {
                case 'decision-number':
                    $meta['broj_odluke'] = $pText;
                    break;

                case 'court':
                    $meta['sud'] = $pText;
                    break;

                case 'decision-date':
                    $meta['datum_odluke'] = $this->normalizeHrDate($pText);
                    break;

                case 'decision-finality':
                    $meta['pravomocnost'] = $pText;
                    break;

                case 'publication-date':
                    $meta['datum_objave'] = $this->normalizeHrDate($pText);
                    break;

                case 'court-registry-type':
                    $meta['upisnik'] = $pText;
                    break;

                case 'decision-type':
                    $meta['vrsta_odluke'] = $pText;
                    break;

                case 'previous-decisions':
                    // Keep as raw string; can be parsed further if needed
                    $meta['prethodna_odluka'] = $this->crawlerText($item->filter('.metadata-content'));
                    break;

                case 'ecli':
                case 'ecli-number':
                    $meta['ecli'] = $pText;
                    break;

                case 'stvarno-kazalo-index':
                    $list = $item->filter('ul.metadata-content > li');
                    $list->each(function (Crawler $li) use (&$meta) {
                        $label = $this->crawlerText($li->filter('a')) ?? $this->crawlerText($li);
                        $class = trim((string)($li->attr('class') ?? ''));
                        $level = null;
                        if (preg_match('~thesaurus-indent-(\d+)~', $class, $m)) {
                            $level = (int)$m[1];
                        }
                        $href = null;
                        try {
                            $a = $li->filter('a')->first();
                            if ($a->count() > 0) {
                                $href = $this->absolutize($a->attr('href'));
                            }
                        } catch (\Throwable $e) {}

                        $meta['stvarno_kazalo'][] = [
                            'label' => $label,
                            'level' => $level,
                            'href'  => $href,
                        ];
                    });
                    break;

                case 'zakonsko-kazalo-index':
                    $lis = $item->filter('ul.metadata-content > li');
                    $lis->each(function (Crawler $li) use (&$laws, &$currentLaw) {
                        $class = trim((string)($li->attr('class') ?? ''));

                        if (str_contains($class, 'law-title')) {
                            // finalize previous
                            if ($currentLaw && (!empty($currentLaw['title']) || !empty($currentLaw['articles']))) {
                                $laws[] = $currentLaw;
                            }
                            $currentLaw = [
                                'title'    => null,
                                'href'     => null,
                                'nn'       => null,
                                'nn_url'   => null,
                                'articles' => [],
                            ];
                            try {
                                $aLaw = $li->filter('a')->first();
                                if ($aLaw->count() > 0) {
                                    $currentLaw['title'] = trim($aLaw->text());
                                    $currentLaw['href']  = $this->absolutize($aLaw->attr('href'));
                                }
                                // optional NN link is usually the second <a>
                                $aLinks = $li->filter('a');
                                if ($aLinks->count() > 1) {
                                    $nn = $aLinks->eq(1);
                                    $currentLaw['nn']     = trim($nn->text());
                                    $currentLaw['nn_url'] = $this->absolutize($nn->attr('href'));
                                }
                            } catch (\Throwable $e) {}
                        } elseif (str_contains($class, 'law-article-index')) {
                            $article = $this->crawlerText($li->filter('span')) ?? $this->crawlerText($li);
                            if (!$currentLaw) {
                                $currentLaw = ['title' => null, 'href' => null, 'nn' => null, 'nn_url' => null, 'articles' => []];
                            }
                            if ($article) {
                                $currentLaw['articles'][] = $article;
                            }
                        }
                    });
                    // finalize last
                    if ($currentLaw && (!empty($currentLaw['title']) || !empty($currentLaw['articles']))) {
                        $laws[] = $currentLaw;
                    }
                    $meta['zakonsko_kazalo'] = $laws;
                    break;

                case 'eurovoc-index':
                    $list = $item->filter('ul.metadata-content > li');
                    $list->each(function (Crawler $li) use (&$meta) {
                        $label = $this->crawlerText($li->filter('a')) ?? $this->crawlerText($li);
                        $class = trim((string)($li->attr('class') ?? ''));
                        $level = null;
                        if (preg_match('~thesaurus-indent-(\d+)~', $class, $m)) {
                            $level = (int)$m[1];
                        }
                        $href = null;
                        try {
                            $a = $li->filter('a')->first();
                            if ($a->count() > 0) {
                                $href = $this->absolutize($a->attr('href'));
                            }
                        } catch (\Throwable $e) {}

                        $meta['eurovoc'][] = [
                            'label' => $label,
                            'level' => $level,
                            'href'  => $href,
                        ];
                    });
                    break;

                default:
                    // ignore unknown blocks but keep future extensibility
                    break;
            }
        });

        // Trim empties
        foreach (['broj_odluke','sud','pravomocnost','upisnik','vrsta_odluke','ecli','prethodna_odluka'] as $k) {
            if (isset($meta[$k])) {
                $meta[$k] = $meta[$k] !== null ? trim((string)$meta[$k]) : null;
                if ($meta[$k] === '') $meta[$k] = null;
            }
        }

        return $meta;
    }

    /**
     * @param string|null $s
     * @return string|null
     */
    protected function normalizeHrDate(?string $s): ?string
    {
        if (!$s) return null;
        $s = trim($s);
        // Match e.g. 12.5.2025. or 31.07.2025.
        if (preg_match('~^(\d{1,2})\.(\d{1,2})\.(\d{2,4})\.?$~u', $s, $m)) {
            $d = (int)$m[1];
            $mo = (int)$m[2];
            $y = (int)$m[3];
            if ($y < 100) $y += 2000;
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
        // ISO or other formats
        $ts = strtotime($s);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    /**
     * @param Crawler|null $node
     * @return string|null
     */
    protected function crawlerText(?Crawler $node): ?string
    {
        if (!$node || $node->count() === 0) return null;
        try {
            $t = $node->text();
        } catch (\Throwable $e) {
            return null;
        }
        $t = preg_replace('~\s+~u', ' ', $t);
        $t = trim((string)$t);
        return $t === '' ? null : $t;
    }

    /**
     * @param string $id
     * @return string
     */
    public function downloadPdfUrl(string $id): string
    {
        return $this->baseUrl . '/Document/Download?id=' . urlencode($id);
    }

    /**
     * @param string $id
     * @return string
     */
    public function downloadHtmlUrl(string $id): string
    {
        return $this->baseUrl . '/Document/Text?id=' . urlencode($id);
    }

    /**
     * @param string $id
     * @return array
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function downloadPdf(string $id): array
    {
        $url  = $this->downloadPdfUrl($id);
        $this->throttle();
        $resp = $this->http()->get($url);

        // fallback na legacy ako treba
        if (!$resp->ok() || stripos((string)$resp->header('Content-Type'), 'pdf') === false) {
            $url  = $this->baseUrl . '/decisionDownload?id=' . urlencode($id);
            $this->throttle();
            $resp = $this->http()->get($url);
        }

        return [
            'ok'           => $resp->ok(),
            'status'       => $resp->status(),
            'content_type' => (string) $resp->header('Content-Type'),
            'bytes'        => $resp->ok() ? $resp->body() : null,
            'url'          => $url,
        ];
    }

    /**
     * @param string $id
     * @return array
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function downloadHtml(string $id): array
    {
        $url  = $this->downloadHtmlUrl($id);
        $this->throttle();
        $resp = $this->http()->get($url);
        if (!$resp->ok()) {
            $url  = $this->baseUrl . '/decisionText?id=' . urlencode($id);
            $this->throttle();
            $resp = $this->http()->get($url);
        }

        $html = $resp->body();
        $hasHtmlTag = stripos($html, '<html') !== false || stripos($html, '<body') !== false;
        if (!$hasHtmlTag) {
            $html = '<!doctype html><meta charset="utf-8"><pre style="white-space:pre-wrap;font:14px/1.4 sans-serif">'
                . htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</pre>';
        }

        return [
            'ok'           => $resp->ok(),
            'status'       => $resp->status(),
            'content_type' => (string) $resp->header('Content-Type'),
            'bytes'        => $resp->ok() ? $html : null,
            'url'          => $url,
        ];
    }

    /**
     * @param array $meta
     * @param string $id
     * @return string
     */
    public function buildBaseFileName(array $meta, string $id): string
    {
        $alias = $this->guessCourtAlias($meta['sud'] ?? '');
        $broj  = $meta['broj_odluke'] ?? 'NEPOZNATO';
        $date  = !empty($meta['datum_odluke']) ? date('Y-m-d', strtotime($meta['datum_odluke'])) : '0000-00-00';
        $base  = sprintf('%s_%s_%s_%s', $alias, $this->slug($broj), $date, $id);
        return substr($base, 0, 220);
    }

    /**
     * @param string $court
     * @return string
     */
    protected function guessCourtAlias(string $court): string
    {
        $c = mb_strtolower($court);
        return match (true) {
            str_contains($c, 'vrhovni sud')         => 'VSRH',
            str_contains($c, 'visoki kazneni sud')  => 'VKSRH',
            str_contains($c, 'visoki prekr')        => 'VPSRH',
            str_contains($c, 'visoki trgova')       => 'VTSRH',
            str_contains($c, 'visoki upravni')      => 'VUSR',
            str_contains($c, 'županijski sud')      => 'ZUP',
            str_contains($c, 'općinski')            => 'OPS',
            str_contains($c, 'trgovački sud')       => 'TS',
            str_contains($c, 'upravni sud')         => 'US',
            default                                 => 'SUD',
        };
    }

    /**
     * @param string $s
     * @return string
     */
    protected function slug(string $s): string
    {
        $s = preg_replace('~[^\pL\pN]+~u', '-', $s);
        $s = trim($s, '-');
        $s = mb_strtolower($s);
        $s = preg_replace('~[^a-z0-9\-]+~', '', $s);
        return $s ?: 'x';
    }

    /**
     * @param string|null $href
     * @return string|null
     */
    protected function absolutize(?string $href): ?string
    {
        if (!$href) return null;
        return str_starts_with($href, 'http')
            ? $href
            : $this->baseUrl . '/' . ltrim($href, '/');
    }
}
