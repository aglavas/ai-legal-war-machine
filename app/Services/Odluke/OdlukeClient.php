<?php

namespace App\Services\Odluke;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class OdlukeClient
{
    public function __construct(
        protected string $baseUrl,
        protected int $timeout = 30,
        protected int $retry   = 2,
        protected int $delayMs = 700,
    ) {}

    public static function fromConfig(): self
    {
        $cfg = config('odluke');
        return new self(
            baseUrl: rtrim($cfg['base_url'] ?? 'https://odluke.sudovi.hr', '/'),
            timeout: (int) ($cfg['timeout'] ?? 30),
            retry:   (int) ($cfg['retry'] ?? 2),
            delayMs: (int) ($cfg['delay_ms'] ?? 700),
        );
    }

    public function withBaseUrl(?string $baseUrl): self
    {
        if (!$baseUrl) return $this;
        return new self($baseUrl, $this->timeout, $this->retry, $this->delayMs);
    }

    protected function http()
    {
        return Http::withHeaders([
            'User-Agent'      => 'Mozilla/5.0 (compatible; OdlukeMCP/1.0)',
            'Accept-Language' => 'hr-HR,hr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Referer'         => $this->baseUrl . '/',
        ])->timeout($this->timeout)->retry($this->retry, 500);
    }

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

    public function fetchDecisionMeta(string $id): ?array
    {
        $url  = $this->baseUrl . '/Document/View?id=' . urlencode($id);
        $resp = $this->http()->get($url);
        if (!$resp->ok()) return null;

        $html = $resp->body();
        $text = trim(strip_tags($html));

        $rx = fn (string $p) => (preg_match($p, $text, $m) ? trim(preg_replace('~\s+~u', ' ', $m[1])) : null);

        $meta = [
            'broj_odluke'   => $rx('~Broj odluke:\s*([^\r\n]+)~u'),
            'sud'           => $rx('~Sud:\s*([^\r\n]+)~u'),
            'datum_odluke'  => $rx('~Datum odluke:\s*([0-9.\-\/]+)~u'),
            'pravomocnost'  => $rx('~Pravomoćnost:\s*([^\r\n]+)~u'),
            'datum_objave'  => $rx('~Datum objave:\s*([0-9.\-\/]+)~u'),
            'upisnik'       => $rx('~Upisnik:\s*([^\r\n]+)~u'),
            'vrsta_odluke'  => $rx('~Vrsta odluke:\s*([^\r\n]+)~u'),
            'ecli'          => $rx('~ECLI broj:\s*([A-Z0-9:\.\-]+)~u'),
            'src'           => $url,
        ];

        // pokušaj dohvatiti link za preuzimanje
        try {
            if (class_exists(Crawler::class)) {
                $c = new Crawler($html);
                $dl = $c->filter('a[title*="Preuzmi"], a[aria-label*="Preuzmi"], a[href*="Download"], a[href*="decisionDownload"]');
                $meta['_download_href'] = $dl->count() ? $this->absolutize($dl->first()->attr('href')) : null;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $meta;
    }

    public function downloadPdfUrl(string $id): string
    {
        return $this->baseUrl . '/Document/Download?id=' . urlencode($id);
    }

    public function downloadHtmlUrl(string $id): string
    {
        return $this->baseUrl . '/Document/Text?id=' . urlencode($id);
    }

    public function downloadPdf(string $id): array
    {
        $url  = $this->downloadPdfUrl($id);
        $resp = $this->http()->get($url);

        // fallback na legacy ako treba
        if (!$resp->ok() || stripos((string)$resp->header('Content-Type'), 'pdf') === false) {
            $url  = $this->baseUrl . '/decisionDownload?id=' . urlencode($id);
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

    public function downloadHtml(string $id): array
    {
        $url  = $this->downloadHtmlUrl($id);
        $resp = $this->http()->get($url);
        if (!$resp->ok()) {
            $url  = $this->baseUrl . '/decisionText?id=' . urlencode($id);
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

    public function buildBaseFileName(array $meta, string $id): string
    {
        $alias = $this->guessCourtAlias($meta['sud'] ?? '');
        $broj  = $meta['broj_odluke'] ?? 'NEPOZNATO';
        $date  = !empty($meta['datum_odluke']) ? date('Y-m-d', strtotime($meta['datum_odluke'])) : '0000-00-00';
        $base  = sprintf('%s_%s_%s_%s', $alias, $this->slug($broj), $date, $id);
        return substr($base, 0, 220);
    }

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

    protected function slug(string $s): string
    {
        $s = preg_replace('~[^\pL\pN]+~u', '-', $s);
        $s = trim($s, '-');
        $s = mb_strtolower($s);
        $s = preg_replace('~[^a-z0-9\-]+~', '', $s);
        return $s ?: 'x';
    }

    protected function absolutize(?string $href): ?string
    {
        if (!$href) return null;
        return str_starts_with($href, 'http')
            ? $href
            : $this->baseUrl . '/' . ltrim($href, '/');
    }
}
