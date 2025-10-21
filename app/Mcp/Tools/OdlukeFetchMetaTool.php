<?php

namespace App\Mcp\Tools;

use App\Services\Odluke\OdlukeClient;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tool;
use Prism\Prism\ValueObjects\ToolResult;

class OdlukeFetchMetaTool extends Tool
{
    protected string $name = 'odluke-meta';
    protected string $title = 'Dohvati metapodatke odluke';
    protected string $description = 'Fetches and returns metadata for one or more decision IDs.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id'       => $schema->string()->description('Single decision ID (GUID)'),
            'ids'      => $schema->array()->items($schema->string())->description('Array of decision IDs'),
            'base_url' => $schema->string()->description('Custom base URL (default from config/odluke.php)'),
        ];
    }

    public function handle(array $arguments): ToolResult
    {
        // Collect unique, non-empty IDs from id and ids[]
        $list = [];
        if (!empty($arguments['id']) && is_string($arguments['id'])) {
            $list[] = trim($arguments['id']);
        }
        if (!empty($arguments['ids']) && is_array($arguments['ids'])) {
            foreach ($arguments['ids'] as $x) {
                if (is_string($x) && trim($x) !== '') {
                    $list[] = trim($x);
                }
            }
        }
        $list = array_values(array_unique(array_filter($list)));

        if (!$list) {
            return ToolResult::error('Provide at least one id or ids[].');
        }

        $client = OdlukeClient::fromConfig()->withBaseUrl($arguments['base_url'] ?? null);

        $result = [];
        foreach ($list as $one) {
            $meta = $client->fetchDecisionMeta($one);
            if (!$meta) {
                $result[] = ['id' => $one, 'error' => 'Fetch failed'];
                continue;
            }
            $basename = $client->buildBaseFileName($meta, $one);
            $result[] = [
                'id'                => $one,
                'meta'              => $meta,
                'basename'          => $basename,
                'download_pdf_url'  => $client->downloadPdfUrl($one),
                'download_html_url' => $client->downloadHtmlUrl($one),
            ];
        }

        return ToolResult::text(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
