<?php

namespace App\Mcp;

use App\Models\IngestedLaw;
use App\Models\Law;
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

    #[McpTool(name: 'law-articles-search', description: 'Pretraži zakone i članke zakona. Parametri: query (opcionalni text za pretragu), law_number, title, limit')]
    public function searchLawArticles(?string $query = null, ?string $law_number = null, ?string $title = null, ?int $limit = 10): array
    {
        $limit = min(max((int)($limit ?? 10), 1), 100);

        // Build query for IngestedLaw (parent laws)
        $ingestedQuery = IngestedLaw::query();

        if ($query !== null && trim($query) !== '') {
            $q = '%' . trim($query) . '%';
            $ingestedQuery->where(function ($qq) use ($q) {
                $qq->where('doc_id', 'like', $q)
                    ->orWhere('title', 'like', $q)
                    ->orWhere('law_number', 'like', $q)
                    ->orWhere('jurisdiction', 'like', $q)
                    ->orWhere('keywords_text', 'like', $q);
            });
        }

        if ($law_number !== null && trim($law_number) !== '') {
            $ingestedQuery->where('law_number', 'like', '%' . trim($law_number) . '%');
        }

        if ($title !== null && trim($title) !== '') {
            $ingestedQuery->where('title', 'like', '%' . trim($title) . '%');
        }

        $ingestedLaws = $ingestedQuery->limit($limit)->get();

        $results = [];

        foreach ($ingestedLaws as $ingestedLaw) {
            // Get law articles (chunks) for this ingested law
            $articles = Law::query()
                ->where('ingested_law_id', $ingestedLaw->id)
                ->orderBy('chunk_index')
                ->limit(50) // Limit articles per law
                ->get(['id', 'chunk_index', 'content', 'chapter', 'section', 'metadata'])
                ->map(function ($law) {
                    return [
                        'id' => $law->id,
                        'chunk_index' => $law->chunk_index,
                        'content' => $law->content,
                        'chapter' => $law->chapter,
                        'section' => $law->section,
                        'metadata' => $law->metadata,
                    ];
                })
                ->toArray();

            $results[] = [
                'ingested_law_id' => $ingestedLaw->id,
                'doc_id' => $ingestedLaw->doc_id,
                'title' => $ingestedLaw->title,
                'law_number' => $ingestedLaw->law_number,
                'jurisdiction' => $ingestedLaw->jurisdiction,
                'country' => $ingestedLaw->country,
                'language' => $ingestedLaw->language,
                'source_url' => $ingestedLaw->source_url,
                'keywords' => $ingestedLaw->keywords,
                'ingested_at' => $ingestedLaw->ingested_at?->toIso8601String(),
                'articles_count' => count($articles),
                'articles' => $articles,
            ];
        }

        if (empty($results)) {
            return [
                'content' => [[ 'type' => 'text', 'text' => 'Nema zakona za zadane kriterije pretrage.' ]],
                'isError' => false,
            ];
        }

        $output = [
            'count' => count($results),
            'results' => $results,
        ];

        return [
            'content' => [[ 'type' => 'text', 'text' => json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ]],
            'isError' => false,
        ];
    }

    #[McpTool(name: 'law-article-by-id', description: 'Dohvati jedan članak zakona po ID-u')]
    public function getLawArticleById(string $id): array
    {
        if (trim($id) === '') {
            return [
                'content' => [[ 'type' => 'text', 'text' => 'ID je obavezan parametar.' ]],
                'isError' => true,
            ];
        }

        $law = Law::find($id);

        if (!$law) {
            return [
                'content' => [[ 'type' => 'text', 'text' => "Članak zakona s ID '{$id}' nije pronađen." ]],
                'isError' => true,
            ];
        }

        $ingestedLaw = $law->ingestedLaw;

        $result = [
            'id' => $law->id,
            'doc_id' => $law->doc_id,
            'ingested_law_id' => $law->ingested_law_id,
            'chunk_index' => $law->chunk_index,
            'content' => $law->content,
            'title' => $law->title,
            'law_number' => $law->law_number,
            'jurisdiction' => $law->jurisdiction,
            'country' => $law->country,
            'language' => $law->language,
            'chapter' => $law->chapter,
            'section' => $law->section,
            'version' => $law->version,
            'source_url' => $law->source_url,
            'tags' => $law->tags,
            'metadata' => $law->metadata,
            'promulgation_date' => $law->promulgation_date?->toDateString(),
            'effective_date' => $law->effective_date?->toDateString(),
            'repeal_date' => $law->repeal_date?->toDateString(),
            'parent_law' => $ingestedLaw ? [
                'id' => $ingestedLaw->id,
                'title' => $ingestedLaw->title,
                'law_number' => $ingestedLaw->law_number,
                'jurisdiction' => $ingestedLaw->jurisdiction,
            ] : null,
        ];

        return [
            'content' => [[ 'type' => 'text', 'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ]],
            'isError' => false,
        ];
    }
}

