<?php

namespace App\Services\Pdf;

use App\Services\PdfRenderer;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Process;

class PdfArticleSplitter2
{
    public function __construct(private PdfRenderer $renderer)
    {
    }

    public function split(
        string $pdfPath,
        string $outDir,
        string $mode = 'pages',
        ?string $lawTitle = null,
        ?string $eli = null,
        ?string $pubDate = null,
        int $startPage = 1,
        array $opts = []
    ): array {
        if (!is_file($pdfPath)) {
            throw new \RuntimeException("PDF not found: $pdfPath");
        }
        @mkdir($outDir, 0775, true);

        $onlyNumbers = array_map([$this, 'normalizeArticleNumber'], $opts['only_numbers'] ?? []);
        $sidecar = (bool)($opts['sidecar'] ?? false);
        $embedXmp = (bool)($opts['embed_xmp'] ?? false);
        $extraAttrs = (array)($opts['extra_attrs'] ?? []);
        $dry = (bool)($opts['dry'] ?? false);

        $pageCount = $this->getPageCount($pdfPath);
        if ($pageCount < 1) {
            throw new \RuntimeException("Unable to read page count (pdfinfo).");
        }

        $articleStarts = $this->detectArticleStartsByPage($pdfPath, $startPage, $pageCount);

        if (empty($articleStarts)) {
            throw new \RuntimeException("Nisam pronašao oznake 'Članak N.' u PDF-u. Pokušaj s --start-page ili mode=render.");
        }

        // Izgradi raspon stranica za svaki članak; dupliciraj graničnu stranicu
        $ranges = $this->buildPageRanges($articleStarts, $pageCount);

        // Ako je specificiran subset članaka (onlyNumbers), filtriraj ranges
        if ($onlyNumbers) {
            $ranges = array_values(array_filter($ranges, fn($r) => in_array($r['number'], $onlyNumbers, true)));
        }

        $manifest = [];
        if ($mode === 'pages') {
            foreach ($ranges as $item) {
                $fileName = $this->buildArticleFileName($lawTitle, (string)$item['number']);
                $dest = rtrim($outDir, '/').'/'.$fileName;

                if (!$dry) {
                    $this->exportPageRange($pdfPath, $item['start_page'], $item['end_page'], $dest);
                }

                // Taggable attributes + optional XMP/sidecar
                $attr = $this->buildAttributes($lawTitle, (string)$item['number'], $eli, $pubDate, $item, $dest, $extraAttrs);
                if (!$dry) {
                    $this->maybeWriteSidecarAndXmp($dest, $attr, $sidecar, $embedXmp);
                }

                $manifest[] = $this->makeMeta($pdfPath, $dest, $item['number'], $item['start_page'], $item['end_page'], $lawTitle, $eli, $pubDate, 'pages');
            }
        } else {
            // render mode – izvući puni tekst i rasparčati po člancima
            $fullText = $this->getText($pdfPath, $startPage, $pageCount);
            $chunks = $this->splitTextIntoArticles($fullText);

            // Pokušaj upariti članke iz teksta s detektiranim brojevima i stranicama (po broju; fallback po redu)
            $byNumber = [];
            foreach ($ranges as $r) $byNumber[$r['number']] = $r;

            $i = 0;
            foreach ($chunks as $chunk) {
                $num = $chunk['number'];
                if ($onlyNumbers && !in_array($num, $onlyNumbers, true)) continue;

                $text = $chunk['text'];
                $range = $byNumber[$num] ?? $ranges[$i] ?? ['start_page'=>null,'end_page'=>null];
                $fileName = $this->buildArticleFileName($lawTitle, (string)$num);
                $dest = rtrim($outDir, '/').'/'.$fileName;

                // Napravi minimalistički HTML s očuvanim novim redovima + skriveni search tags
                $searchTags = $this->makeSearchTags($lawTitle, (string)$num, $eli, $pubDate, $extraAttrs);
                $articleHtml = '<div style="white-space:pre-wrap">'.e($text).'</div>';
                $articleHtml .= '<div style="display:none" aria-hidden="true" data-type="search-tags">'.e(implode(', ', $searchTags)).'</div>';

                $ctx = [
                    'law_title' => $lawTitle ?: 'Nepoznati naslov',
                    'law_eli' => $eli ?: '',
                    'law_pub_date' => $pubDate ?: '',
                    'article_number' => $num,
                    'article_html' => $articleHtml,
                    'generated_at' => gmdate('Y-m-d H:i:s').'Z',
                    'generator_version' => '1.0.0',
                    'search_tags' => $searchTags,
                ];

                if (!$dry) {
                    $this->renderer->renderArticle($ctx, $dest);
                }

                // Taggable attributes + optional XMP/sidecar
                $attr = $this->buildAttributes($lawTitle, (string)$num, $eli, $pubDate, $range, $dest, $extraAttrs);
                if (!$dry) {
                    $this->maybeWriteSidecarAndXmp($dest, $attr, $sidecar, $embedXmp);
                }

                $manifest[] = $this->makeMeta($pdfPath, $dest, (string)$num, $range['start_page'], $range['end_page'], $lawTitle, $eli, $pubDate, 'render');
                $i++;
            }
        }

        // Snimi manifest
        if (!$dry) {
            file_put_contents(rtrim($outDir, '/').'/manifest.json', json_encode([
                'source_pdf' => realpath($pdfPath),
                'mode' => $mode,
                'count' => count($manifest),
                'generated_at' => gmdate('c'),
                'articles' => $manifest,
            ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        }

        return $manifest;
    }

    private function getPageCount(string $pdfPath): int
    {
        $process = new Process(['pdfinfo', $pdfPath]);
        $process->setTimeout(20);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("pdfinfo error: ".$process->getErrorOutput());
        }

        $out = $process->getOutput();
        return preg_match('/Pages:\s+(\d+)/', $out, $m) ? (int)$m[1] : 0;
    }

    private function runPdftotext(array $args): string
    {
        // Sastavi i pokreni: pdftotext <args>
        $process = new Process(array_merge(['pdftotext'], $args));
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("pdftotext error: ".$process->getErrorOutput());
        }

        return $process->getOutput();
    }

    private function getText(string $pdfPath, int $from, int $to): string
    {
        // VAŽNO: ovdje je 'UTF-8' bez crte ispred
        return $this->runPdftotext([
            '-f', (string)$from,
            '-l', (string)$to,
            '-enc', 'UTF-8',
            '-layout',
            $pdfPath,
            '-', // output to stdout
        ]);
    }

    private function getPageText(string $pdfPath, int $page): string
    {
        return $this->runPdftotext([
            '-f', (string)$page,
            '-l', (string)$page,
            '-enc', 'UTF-8',
            '-layout',
            $pdfPath,
            '-',
        ]);
    }


    private function detectArticleStartsByPage(string $pdfPath, int $startPage, int $endPage): array
    {
        $starts = [];
        for ($p = $startPage; $p <= $endPage; $p++) {
            $txt = $this->getPageText($pdfPath, $p);
            // Članak na početku retka (ignoriraj pojavljivanja usred rečenica)
            if (preg_match_all('/(^|\R)\s*(Članak|CLANAK)\s+(\d+(?:\.[a-z])?)\s*\./iu', $txt, $m)) {
                foreach ($m[3] as $raw) {
                    $num = $this->normalizeArticleNumber($raw);
                    // izbjegni duplikate (ako isti članak više puta spomenut na istoj stranici)
                    if (!array_key_exists($num, array_column($starts, null, 'number') ?? [])) {
                        $starts[] = ['number' => $num, 'start_page' => $p];
                    }
                }
            }
        }
        // Sortiraj po start_page
        usort($starts, fn($a,$b) => $a['start_page'] <=> $b['start_page']);
        return $starts;
    }

    private function buildPageRanges(array $starts, int $lastPage): array
    {
        $ranges = [];
        $count = count($starts);
        for ($i = 0; $i < $count; $i++) {
            $start = $starts[$i]['start_page'];
            $end = ($i < $count - 1) ? $starts[$i+1]['start_page'] : $lastPage;
            // dupliciraj graničnu stranicu: prethodni završava na stranici početka sljedećeg
            $ranges[] = [
                'number' => $starts[$i]['number'],
                'start_page' => $start,
                'end_page' => $end
            ];
        }
        return $ranges;
    }

    private function exportPageRange(string $srcPdf, int $from, int $to, string $destPdf): void
    {
        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($srcPdf);

        $from = max(1, min($pageCount, $from));
        $to   = max(1, min($pageCount, $to));
        if ($to < $from) $to = $from;

        for ($p = $from; $p <= $to; $p++) {
            $tpl = $pdf->importPage($p);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl);
        }
        @mkdir(dirname($destPdf), 0775, true);
        $pdf->Output($destPdf, 'F');
    }

    private function splitTextIntoArticles(string $text): array
    {
        // Normaliziraj novačak
        $text = str_replace("\r\n", "\n", $text);
        // Ubaci razdjelnike prije "Članak N."
        $text = preg_replace('/(^|\R)\s*(Članak|CLANAK)\s+(\d+(?:\.[a-z])?)\s*\./iu', "\n<<BREAK>>Članak $3.\n", $text);

        $parts = array_values(array_filter(array_map('trim', explode("<<BREAK>>", $text))));
        $out = [];
        foreach ($parts as $part) {
            if (preg_match('/^Članak\s+(\d+(?:\.[a-z])?)\s*\./iu', $part, $m)) {
                $num = $this->normalizeArticleNumber($m[1]);
                $out[] = ['number' => $num, 'text' => $part];
            }
        }
        return $out;
    }

    private function normalizeArticleNumber(string $raw): string
    {
        // "8.a" -> "8a", "12"->"12"
        $raw = Str::of($raw)->lower()->toString();
        return str_replace('.', '', $raw);
    }

    private function makeMeta(
        string $srcPdf, string $destPdf, string $num, ?int $startPage, ?int $endPage,
        ?string $title, ?string $eli, ?string $pubDate, string $mode
    ): array {
        $bytes = @filesize($destPdf) ?: null;
        $sha256 = @hash_file('sha256', $destPdf) ?: null;

        return [
            'article_number' => (string)$num,
            'source_pdf' => realpath($srcPdf),
            'output_pdf' => realpath($destPdf),
            'pages' => ['start' => $startPage, 'end' => $endPage],
            'law_title' => $title,
            'eli' => $eli,
            'publication_date' => $pubDate,
            'mode' => $mode,
            'file' => ['bytes' => $bytes, 'sha256' => $sha256],
            'generated_at' => gmdate('c'),
        ];
    }

    private function buildArticleFileName(?string $lawTitle, string $num): string
    {
        $prefix = $lawTitle ? ($lawTitle . ' - ') : '';
        return $prefix . sprintf('clanak-%s.pdf', $num);
    }

    private function makeSearchTags(?string $lawTitle, string $num, ?string $eli, ?string $pubDate, array $extraAttrs): array
    {
        $tags = array_values(array_filter([
            $lawTitle,
            'Članak ' . $num,
            $eli,
            $pubDate,
        ]));
        foreach ($extraAttrs as $k => $v) {
            $tags[] = "{$k}:{$v}";
        }
        return array_unique($tags);
    }

    private function buildAttributes(?string $lawTitle, string $num, ?string $eli, ?string $pubDate, array $range, string $dest, array $extraAttrs): array
    {
        $attrs = array_merge([
            'title' => (string)($lawTitle ?? ''),
            'article' => (string)$num,
            'eli' => (string)($eli ?? ''),
            'publication_date' => (string)($pubDate ?? ''),
            'pages' => ($range['start_page'] ?? null) && ($range['end_page'] ?? null)
                ? (($range['start_page']).'-'.($range['end_page']))
                : '',
            'sha256' => @hash_file('sha256', $dest) ?: '',
            'file_name' => basename($dest),
        ], $extraAttrs);

        // keywords za pretragu
        $attrs['keywords'] = implode(', ', $this->makeSearchTags($lawTitle, $num, $eli, $pubDate, $extraAttrs));
        return array_filter($attrs, fn($v) => $v !== null && $v !== '');
    }

    private function maybeWriteSidecarAndXmp(string $pdfPath, array $attrs, bool $sidecar, bool $embedXmp): void
    {
        if ($sidecar) {
            $side = preg_replace('/\.pdf$/i', '.attrs.json', $pdfPath);
            file_put_contents($side, json_encode($attrs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
//        if ($embedXmp) {
//            $this->embedJsonToPdfXmpDescription($pdfPath, $attrs);
//        }
    }

    private function embedJsonToPdfXmpDescription(string $pdfIn, array $json): void
    {
        $bin = trim((string) shell_exec("which exiftool"));
        if ($bin === '') {
            return; // ignoriraj ako nema exiftool
        }
        $tmpJson = tempnam(sys_get_temp_dir(), 'attrs_') . '.json';
        file_put_contents($tmpJson, json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        try {
            $args = [
                $bin,
                '-charset', 'UTF8',
                '-P',
                'XMP:Label=AI_Metadata',
                'XMP:MetadataDate=now',
                'XMP-dc:Description<='.$tmpJson,
                '-overwrite_original',
                $pdfIn,
            ];
            $proc = new Process($args, null, null, null, 60);
            $proc->run();
            // tihi fallback – bez izbacivanja iznimke
        } finally {
            @unlink($tmpJson);
        }
    }
}
