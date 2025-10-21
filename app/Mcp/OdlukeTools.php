<?php

namespace App\Mcp;

use App\Services\Odluke\OdlukeClient;
use PhpMcp\Server\Attributes\McpTool;

class OdlukeTools
{
    #[McpTool(name: 'odluke-search', description: 'Pretraži odluke i vrati ID-eve s /Document/DisplayList. Parametri: q, params, page, limit, base_url')]
    public function search(?string $q = null, ?string $params = null, ?int $limit = 100, ?int $page = 1, ?string $base_url = null): array
    {
        $client = OdlukeClient::fromConfig()->withBaseUrl($base_url);
        $out = $client->collectIdsFromList($q, $params, (int)($limit ?? 100), (int)($page ?? 1));

        $ok = ($out['ids'] ?? []) !== [];
        $text = $ok
            ? json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)
            : 'Nema ID-eva za zadane parametre ili dohvat nije uspio.';

        return [
            'content' => [[ 'type' => 'text', 'text' => $text ]],
            'isError' => ! $ok,
        ];
    }

    #[McpTool(name: 'odluke-meta', description: 'Dohvati metapodatke za jedan ili više ID-eva (Document/View?id=...)')]
    public function meta(?string $id = null, ?array $ids = null, ?string $base_url = null): array
    {
        $list = [];
        if ($id !== null && trim($id) !== '') {
            $list[] = trim($id);
        }
        if (is_array($ids)) {
            foreach ($ids as $x) {
                if (is_string($x) && trim($x) !== '') $list[] = trim($x);
            }
        }
        $list = array_values(array_unique($list));

        if (! count($list)) {
            return [
                'content' => [[ 'type' => 'text', 'text' => 'Predajte barem jedan id ili polje ids.' ]],
                'isError' => true,
            ];
        }

        $client = OdlukeClient::fromConfig()->withBaseUrl($base_url);
        $result = [];
        foreach ($list as $one) {
            $meta = $client->fetchDecisionMeta($one);
            if (! $meta) {
                $result[] = [ 'id' => $one, 'error' => 'Neuspješan dohvat' ];
                continue;
            }
            $basename = $client->buildBaseFileName($meta, $one);
            $result[] = [
                'id' => $one,
                'meta' => $meta,
                'basename' => $basename,
                'download_pdf_url' => $client->downloadPdfUrl($one),
                'download_html_url' => $client->downloadHtmlUrl($one),
            ];
        }

        return [
            'content' => [[ 'type' => 'text', 'text' => json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) ]],
            'isError' => false,
        ];
    }

    #[McpTool(name: 'odluke-download', description: 'Preuzmi odluku (PDF/HTML). Parametri: id (GUID), format {pdf|html|both}, save, base_url')]
    public function download(string $id, ?string $format = 'pdf', ?bool $save = false, ?string $base_url = null): array
    {
        $format = in_array($format, ['pdf','html','both'], true) ? $format : 'pdf';
        $client = OdlukeClient::fromConfig()->withBaseUrl($base_url);

        $outDir = config('odluke.out_dir') ?: storage_path('app/odluke');
        @is_dir($outDir) || @mkdir($outDir, 0775, true);

        $meta = $client->fetchDecisionMeta($id) ?? [];
        $basename = $client->buildBaseFileName($meta, $id);

        $result = [
            'id' => $id,
            'meta' => $meta,
            'download_pdf_url' => $client->downloadPdfUrl($id),
            'download_html_url' => $client->downloadHtmlUrl($id),
            'saved' => [],
            'errors' => [],
        ];

        if (in_array($format, ['pdf','both'], true)) {
            $pdf = $client->downloadPdf($id);
            if ($pdf['ok'] ?? false) {
                if ($save) {
                    $path = $outDir . '/' . $basename . '.pdf';
                    file_put_contents($path, $pdf['bytes']);
                    $result['saved']['pdf'] = $path;
                } else {
                    $result['pdf'] = [
                        'content_type' => $pdf['content_type'],
                        'bytes' => strlen($pdf['bytes'] ?? ''),
                    ];
                }
            } else {
                $result['errors']['pdf'] = 'HTTP ' . ($pdf['status'] ?? '??');
            }
        }

        if (in_array($format, ['html','both'], true)) {
            $html = $client->downloadHtml($id);
            if ($html['ok'] ?? false) {
                if ($save) {
                    $path = $outDir . '/' . $basename . '.html';
                    file_put_contents($path, $html['bytes']);
                    $result['saved']['html'] = $path;
                } else {
                    $result['html'] = [
                        'content_type' => $html['content_type'],
                        'bytes' => strlen($html['bytes'] ?? ''),
                    ];
                }
            } else {
                $result['errors']['html'] = 'HTTP ' . ($html['status'] ?? '??');
            }
        }

        $ok = empty($result['errors']);
        $text = json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);

        return [
            'content' => [[ 'type' => 'text', 'text' => $text ]],
            'isError' => ! $ok,
        ];
    }
}

